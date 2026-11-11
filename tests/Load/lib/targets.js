export const READ = 'read';
export const WRITE = 'write';

export const READ_P95_MS = 150;
export const READ_P99_MS = 300;
export const WRITE_P95_MS = 250;
export const WRITE_P99_MS = 500;
export const MAX_ERROR_RATE = 0.001;
export const MIN_CHECK_RATE = 0.99;

export const READ_OPERATIONS = ['list', 'list next page', 'show'];
export const WRITE_OPERATIONS = ['create', 'edit', 'approve', 'cancel'];

const READ_LIMITS = [`p(95)<=${READ_P95_MS}`, `p(99)<=${READ_P99_MS}`];
const WRITE_LIMITS = [`p(95)<=${WRITE_P95_MS}`, `p(99)<=${WRITE_P99_MS}`];
const READ_OPERATION_LIMITS = [`p(95)<=${READ_P95_MS}`];
const WRITE_OPERATION_LIMITS = [`p(95)<=${WRITE_P95_MS}`];

/**
 * The latency targets as k6 thresholds. Each operation is held to the p95 target, so a slow write cannot
 * hide behind fast reads; the p99 target applies to all reads and all writes together, because a rare
 * operation such as an edit has too few requests for its own p99 to be stable. The summary still reports
 * every operation's p99.
 */
export function latencyThresholds() {
    const thresholds = {
        [`http_req_duration{kind:${READ}}`]: READ_LIMITS,
        [`http_req_duration{kind:${WRITE}}`]: WRITE_LIMITS,
    };

    for (const operation of READ_OPERATIONS) {
        thresholds[`http_req_duration{name:${operation}}`] = READ_OPERATION_LIMITS;
    }

    for (const operation of WRITE_OPERATIONS) {
        thresholds[`http_req_duration{name:${operation}}`] = WRITE_OPERATION_LIMITS;
    }

    return thresholds;
}

/**
 * Registers the per-operation trends without failing the run, so the summary still reports them.
 */
export function recordedOperations() {
    const recorded = {
        [`http_req_duration{kind:${READ}}`]: ['max>=0'],
        [`http_req_duration{kind:${WRITE}}`]: ['max>=0'],
    };

    for (const operation of [...READ_OPERATIONS, ...WRITE_OPERATIONS]) {
        recorded[`http_req_duration{name:${operation}}`] = ['max>=0'];
    }

    return recorded;
}
