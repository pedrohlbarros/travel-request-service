import { loginEveryone } from './lib/api.js';
import { userIteration } from './lib/flow.js';
import { summaryFor } from './lib/summary.js';
import { MAX_ERROR_RATE, MIN_CHECK_RATE, recordedOperations } from './lib/targets.js';

const VUS = Number(__ENV.VUS || 50);

/**
 * A closed pool of VUs pushing as fast as the service answers: it measures how much the service delivers,
 * so only errors and checks fail the run, and throughput and latency are recorded for comparison.
 */
export const options = {
    scenarios: {
        capacity: {
            executor: 'constant-vus',
            vus: VUS,
            duration: __ENV.DURATION || '5m',
        },
    },
    thresholds: {
        ...recordedOperations(),
        http_req_failed: [`rate<${MAX_ERROR_RATE}`],
        checks: [`rate>${MIN_CHECK_RATE}`],
    },
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

export function setup() {
    return loginEveryone();
}

export default function (tokens) {
    userIteration(tokens);
}

export function handleSummary(data) {
    return summaryFor('capacity', data, { vus: VUS });
}
