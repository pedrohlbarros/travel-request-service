import { loginEveryone, USERS } from './lib/api.js';
import { userIteration } from './lib/flow.js';
import { summaryFor } from './lib/summary.js';

export const options = {
    scenarios: {
        smoke: { executor: 'constant-vus', vus: 1, duration: __ENV.DURATION || '30s' },
    },
    thresholds: {
        checks: ['rate==1'],
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<500'],
    },
};

export function setup() {
    return loginEveryone();
}

export default function (tokens) {
    userIteration(tokens);
}

export function handleSummary(data) {
    return summaryFor('smoke', data, { users: USERS });
}
