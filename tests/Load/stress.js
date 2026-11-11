import exec from 'k6/execution';
import { loginEveryone } from './lib/api.js';
import { userIteration } from './lib/flow.js';
import { summaryFor } from './lib/summary.js';
import { MAX_ERROR_RATE, READ, READ_P95_MS, WRITE } from './lib/targets.js';

const STAGE_SECONDS = Number(__ENV.STAGE_SECONDS || 120);
const TARGETS = [50, 100, 200, 300, 400];

const perStage = {};

for (const target of TARGETS) {
    perStage[`http_req_duration{stage:${target}}`] = ['max>=0'];
    perStage[`http_req_duration{stage:${target},kind:${READ}}`] = ['max>=0'];
    perStage[`http_req_duration{stage:${target},kind:${WRITE}}`] = ['max>=0'];
    perStage[`http_req_failed{stage:${target}}`] = ['rate>=0'];
    perStage[`http_reqs{stage:${target}}`] = ['count>=0'];
}

export const options = {
    scenarios: {
        stress: {
            executor: 'ramping-vus',
            startVUs: 10,
            stages: TARGETS.map((target) => ({ duration: `${STAGE_SECONDS}s`, target })),
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        http_req_failed: [{ threshold: 'rate<0.2', abortOnFail: true, delayAbortEval: '30s' }],
        ...perStage,
    },
    summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

export function setup() {
    return loginEveryone();
}

function currentStage() {
    const elapsedSeconds = exec.instance.currentTestRunDuration / 1000;
    const index = Math.min(TARGETS.length - 1, Math.floor(elapsedSeconds / STAGE_SECONDS));

    return String(TARGETS[index]);
}

export default function (tokens) {
    userIteration(tokens, { stage: currentStage() });
}

/**
 * The ceiling is the last stage that still met the read latency target with errors under the limit; past it
 * the service degrades.
 */
export function handleSummary(data) {
    const stages = TARGETS.map((target) => {
        const duration = data.metrics[`http_req_duration{stage:${target}}`];
        const reads = data.metrics[`http_req_duration{stage:${target},kind:${READ}}`];
        const writes = data.metrics[`http_req_duration{stage:${target},kind:${WRITE}}`];
        const failed = data.metrics[`http_req_failed{stage:${target}}`];
        const requests = data.metrics[`http_reqs{stage:${target}}`];

        return {
            vus: target,
            requests: requests ? requests.values.count : 0,
            rps: requests ? Math.round(requests.values.count / STAGE_SECONDS) : 0,
            p95Ms: duration ? Math.round(duration.values['p(95)']) : null,
            p99Ms: duration ? Math.round(duration.values['p(99)']) : null,
            readP95Ms: reads ? Math.round(reads.values['p(95)']) : null,
            writeP95Ms: writes ? Math.round(writes.values['p(95)']) : null,
            errorRate: failed ? failed.values.rate : null,
        };
    });
    const healthy = stages.filter((stage) => stage.requests > 0 && stage.readP95Ms <= READ_P95_MS && stage.errorRate < MAX_ERROR_RATE);
    const ceiling = healthy.length > 0 ? healthy[healthy.length - 1] : null;

    console.log(`Stages: ${JSON.stringify(stages)}`);
    console.log(`Ceiling (read p95 <= ${READ_P95_MS} ms, errors < ${MAX_ERROR_RATE * 100}%): ${ceiling ? `${ceiling.vus} VUs, ${ceiling.rps} req/s` : 'none'}`);

    return summaryFor('stress', data, { stages, ceiling });
}
