import { textSummary } from 'https://jslib.k6.io/k6-summary/0.1.0/index.js';

export function summaryFor(scenario, data, extra = {}) {
    const label = __ENV.RUN_LABEL || 'local';
    const path = `/results/${scenario}-${label}.json`;

    return {
        stdout: textSummary(data, { indent: ' ', enableColors: true }) + '\n',
        [path]: JSON.stringify({ scenario, label, finishedAt: new Date().toISOString(), ...extra, metrics: data.metrics }, null, 2),
    };
}
