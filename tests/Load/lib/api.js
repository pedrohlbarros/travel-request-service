import http from 'k6/http';
import { check, fail } from 'k6';
import { WRITE } from './targets.js';

export const BASE_URL = __ENV.BASE_URL || 'http://app:8000';
export const API = `${BASE_URL}/api/v1`;
export const PASSWORD = __ENV.LOAD_PASSWORD || 'load-password-123';
export const USERS = Number(__ENV.LOAD_USERS || 50);
export const ADMINS = Number(__ENV.LOAD_ADMINS || 5);

const JSON_HEADERS = { Accept: 'application/json', 'Content-Type': 'application/json' };
export const CONFLICT_TOLERANT_STATUSES = http.expectedStatuses(200, 409, 412);

export function userEmail(number) {
    return `load-user-${String(number).padStart(2, '0')}@example.com`;
}

export function adminEmail(number) {
    return `load-admin-${String(number).padStart(2, '0')}@example.com`;
}

export function login(email) {
    const response = http.post(`${API}/auth/login`, JSON.stringify({ email, password: PASSWORD }), {
        headers: JSON_HEADERS,
        tags: { name: 'login' },
    });

    if (response.status !== 200) {
        fail(`Login of ${email} failed with ${response.status}; run make load-seed first.`);
    }

    return response.json('access_token');
}

export function loginEveryone() {
    const users = [];
    const admins = [];

    for (let number = 1; number <= USERS; number++) {
        users.push(login(userEmail(number)));
    }

    for (let number = 1; number <= ADMINS; number++) {
        admins.push(login(adminEmail(number)));
    }

    return { users, admins };
}

export function headers(token, extra = {}) {
    return { ...JSON_HEADERS, Authorization: `Bearer ${token}`, ...extra };
}

export function futureDate(daysFromToday) {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() + daysFromToday);

    return date.toISOString().slice(0, 10);
}

export function createTravelRequest(token, tags = {}) {
    const departureInDays = 10 + Math.floor(Math.random() * 150);
    const trip = {
        destination: pick(['Lisbon', 'Tokyo', 'Paris', 'Wano', 'Berlin', 'Lima']),
        departure_date: futureDate(departureInDays),
        return_date: futureDate(departureInDays + Math.floor(Math.random() * 10)),
    };
    const response = http.post(`${API}/travel-requests`, JSON.stringify(trip), {
        headers: headers(token),
        tags: { name: 'create', kind: WRITE, ...tags },
    });
    check(response, { 'create is 201': (created) => created.status === 201 });

    return response.status === 201 ? response.json('data.id') : null;
}

export function decide(token, id, version, tags = {}) {
    const action = Math.random() < 0.5 ? 'approve' : 'cancel';

    return http.post(`${API}/travel-requests/${id}/${action}`, null, {
        headers: headers(token, { 'If-Match': `"${version}"` }),
        tags: { name: action, kind: WRITE, ...tags },
        responseCallback: CONFLICT_TOLERANT_STATUSES,
    });
}

export function pick(items) {
    return items[Math.floor(Math.random() * items.length)];
}
