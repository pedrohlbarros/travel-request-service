import { loginEveryone } from './lib/api.js';
import { userIteration } from './lib/flow.js';
import { summaryFor } from './lib/summary.js';
import { latencyThresholds, MAX_ERROR_RATE, MIN_CHECK_RATE } from './lib/targets.js';

/**
 * The design load: a fixed arrival rate, so latency is measured at the expected traffic instead of whatever
 * a closed pool of VUs manages to push. Each iteration makes about 1.57 requests on average, so the default
 * 64 iterations/s lands near 100 requests/s.
 */
const ITERATIONS_PER_SECOND = Number(__ENV.LOAD_RATE || 64);
const MIN_REQUESTS_PER_SECOND = Number(__ENV.LOAD_MIN_RPS || 95);

export const options = {
    scenarios: {
        load: {
            executor: 'constant-arrival-rate',
            rate: ITERATIONS_PER_SECOND,
            timeUnit: '1s',
            duration: __ENV.DURATION || '5m',
            preAllocatedVUs: 50,
            maxVUs: 200,
        },
    },
    thresholds: {
        ...latencyThresholds(),
        http_req_failed: [`rate<${MAX_ERROR_RATE}`],
        checks: [`rate>${MIN_CHECK_RATE}`],
        http_reqs: [`rate>=${MIN_REQUESTS_PER_SECOND}`],
        dropped_iterations: ['count==0'],
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
    return summaryFor('load', data, { iterationsPerSecond: ITERATIONS_PER_SECOND });
}
