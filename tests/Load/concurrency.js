import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Counter } from 'k6/metrics';
import { API, adminEmail, ADMINS, createTravelRequest, decide, headers, login, userEmail } from './lib/api.js';
import { summaryFor } from './lib/summary.js';

const ADMIN_VUS = Number(__ENV.ADMIN_VUS || 20);
const TRAVEL_REQUESTS = Number(__ENV.TRAVEL_REQUESTS || 50);
const ROUND_MS = 500;
const START_DELAY_MS = 3000;

const wins = new Counter('decision_wins');
const conflicts = new Counter('decision_conflicts');
const unexpected = new Counter('decision_unexpected');
const badFinalStates = new Counter('decision_bad_final_states');

export const options = {
    scenarios: {
        concurrency: {
            executor: 'per-vu-iterations',
            vus: ADMIN_VUS,
            iterations: TRAVEL_REQUESTS,
            maxDuration: '5m',
        },
    },
    thresholds: {
        decision_wins: [`count==${TRAVEL_REQUESTS}`],
        decision_unexpected: ['count==0'],
        decision_bad_final_states: ['count==0'],
        checks: ['rate==1'],
    },
};

export function setup() {
    const requester = login(userEmail(1));
    const admins = [];

    for (let number = 1; number <= ADMINS; number++) {
        admins.push(login(adminEmail(number)));
    }

    const ids = [];

    for (let index = 0; index < TRAVEL_REQUESTS; index++) {
        ids.push(createTravelRequest(requester));
    }

    return { admins, ids, startAt: Date.now() + START_DELAY_MS };
}

/**
 * Every admin VU fires at the same request at the same wall-clock moment, one request per round.
 */
export default function (data) {
    const round = exec.vu.iterationInScenario;
    const waitMs = data.startAt + round * ROUND_MS - Date.now();

    if (waitMs > 0) {
        sleep(waitMs / 1000);
    }

    const token = data.admins[exec.vu.idInTest % data.admins.length];
    const response = decide(token, data.ids[round], 1);

    if (response.status === 200) {
        wins.add(1);
    } else if (response.status === 409 || response.status === 412) {
        conflicts.add(1);
    } else {
        unexpected.add(1);
    }

    check(response, { 'only 200, 409 or 412': (decided) => [200, 409, 412].includes(decided.status) });
}

/**
 * One decision per request means version 2 and a final status: a second winner would have made it 3.
 */
export function teardown(data) {
    for (const id of data.ids) {
        const response = http.get(`${API}/travel-requests/${id}`, { headers: headers(data.admins[0]), tags: { name: 'show' } });
        const isSettledOnce = response.status === 200 && response.json('data.version') === 2 && response.json('data.status') !== 'requested';

        if (!isSettledOnce) {
            badFinalStates.add(1);
        }
    }
}

export function handleSummary(data) {
    return summaryFor('concurrency', data, { adminVus: ADMIN_VUS, travelRequests: TRAVEL_REQUESTS });
}
