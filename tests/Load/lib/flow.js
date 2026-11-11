import http from 'k6/http';
import { check } from 'k6';
import exec from 'k6/execution';
import { API, CONFLICT_TOLERANT_STATUSES, createTravelRequest, decide, headers, pick } from './api.js';
import { READ, WRITE } from './targets.js';

const knownIds = [];
const createdIds = [];

function listOwn(token, tags) {
    const filter = Math.random() < 0.3 ? '&status=requested' : '';
    const response = http.get(`${API}/travel-requests?per_page=20${filter}`, { headers: headers(token), tags: { name: 'list', kind: READ, ...tags } });
    check(response, { 'list is 200': (listed) => listed.status === 200 });

    if (response.status !== 200) {
        return;
    }

    for (const travelRequest of response.json('data') || []) {
        knownIds.push(travelRequest.id);
    }

    knownIds.splice(0, Math.max(0, knownIds.length - 100));
    const next = response.json('links.next');

    if (next) {
        const nextPage = http.get(next, { headers: headers(token), tags: { name: 'list next page', kind: READ, ...tags } });
        check(nextPage, { 'next page is 200': (listed) => listed.status === 200 });
    }
}

function showOne(token, tags) {
    if (knownIds.length === 0) {
        listOwn(token, tags);

        return;
    }

    const response = http.get(`${API}/travel-requests/${pick(knownIds)}`, { headers: headers(token), tags: { name: 'show', kind: READ, ...tags } });
    check(response, { 'show is 200': (shown) => shown.status === 200 });
}

function create(token, tags) {
    const id = createTravelRequest(token, tags);

    if (id) {
        createdIds.push(id);
        knownIds.push(id);
    }
}

function editDestination(token, tags) {
    const id = createdIds.pop();

    if (!id) {
        create(token, tags);

        return;
    }

    const shown = http.get(`${API}/travel-requests/${id}`, { headers: headers(token), tags: { name: 'show', kind: READ, ...tags } });

    if (shown.status !== 200 || shown.json('data.status') !== 'requested') {
        return;
    }

    const response = http.patch(`${API}/travel-requests/${id}`, JSON.stringify({ destination: pick(['Porto', 'Kyoto', 'Lyon']) }), {
        headers: headers(token, { 'If-Match': shown.headers.Etag || shown.headers.ETag }),
        tags: { name: 'edit', kind: WRITE, ...tags },
        responseCallback: CONFLICT_TOLERANT_STATUSES,
    });
    check(response, { 'edit is 200, or 409/412 after a concurrent decision': (edited) => [200, 409, 412].includes(edited.status) });
}

function decideOne(adminToken, tags) {
    const pending = http.get(`${API}/travel-requests?status=requested&per_page=20`, { headers: headers(adminToken), tags: { name: 'list', kind: READ, ...tags } });
    const candidates = pending.status === 200 ? pending.json('data') || [] : [];

    if (candidates.length === 0) {
        return;
    }

    const target = pick(candidates);
    const response = decide(adminToken, target.id, target.version, tags);
    check(response, { 'decision is 200, 409 or 412': (decided) => [200, 409, 412].includes(decided.status) });
}

/**
 * One iteration of the realistic mix: half lists, a fifth reads a detail, the rest writes.
 */
export function userIteration(tokens, tags = {}) {
    const vu = exec.vu.idInTest;
    const token = tokens.users[vu % tokens.users.length];
    const adminToken = tokens.admins[vu % tokens.admins.length];
    const roll = Math.random() * 100;

    if (roll < 50) {
        listOwn(token, tags);
    } else if (roll < 70) {
        showOne(token, tags);
    } else if (roll < 85) {
        create(token, tags);
    } else if (roll < 90) {
        editDestination(token, tags);
    } else {
        decideOne(adminToken, tags);
    }
}
