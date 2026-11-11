<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações ao vivo · Travel Request Service</title>
    <style>
        :root {
            --bg: #f5f6f8;
            --surface: #ffffff;
            --surface-muted: #f0f2f5;
            --border: #e2e5ea;
            --text: #16181d;
            --text-muted: #5d6573;
            --accent: #4f46e5;
            --accent-soft: #eef0ff;
            --approved: #0f8a5f;
            --approved-soft: #e3f5ee;
            --cancelled: #c2410c;
            --cancelled-soft: #fdeee6;
            --live: #16a34a;
            --warning: #b45309;
            --shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 4px 16px rgba(16, 24, 40, .06);
            --radius: 14px;
            color-scheme: light;
        }

        :root[data-theme="dark"] {
            --bg: #0f1115;
            --surface: #171a21;
            --surface-muted: #1f232c;
            --border: #2a2f3a;
            --text: #e8eaef;
            --text-muted: #9aa3b2;
            --accent: #8b85ff;
            --accent-soft: #24233d;
            --approved: #34d399;
            --approved-soft: #12302a;
            --cancelled: #fb923c;
            --cancelled-soft: #352218;
            --live: #4ade80;
            --warning: #fbbf24;
            --shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 8px 24px rgba(0, 0, 0, .3);
            color-scheme: dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 15px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        code, kbd, .mono { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: .86em; }

        .shell { max-width: 1120px; margin: 0 auto; padding: 24px 16px 48px; }

        header.top {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            margin-bottom: 24px; flex-wrap: wrap;
        }

        .brand { display: flex; align-items: center; gap: 12px; }

        .brand-mark {
            width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--accent), #06b6d4); color: #fff; font-size: 20px;
        }

        .brand h1 { font-size: 18px; margin: 0; letter-spacing: -.01em; }
        .brand p { margin: 0; color: var(--text-muted); font-size: 13px; }

        .top-actions { display: flex; align-items: center; gap: 10px; }

        .pill {
            display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px;
            background: var(--surface); border: 1px solid var(--border); font-size: 13px; color: var(--text-muted);
        }

        .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--text-muted); }
        .pill[data-state="live"] { color: var(--live); }
        .pill[data-state="live"] .dot { background: var(--live); box-shadow: 0 0 0 0 var(--live); animation: pulse 2s infinite; }
        .pill[data-state="connecting"] .dot { background: var(--warning); }
        .pill[data-state="error"] { color: var(--cancelled); }
        .pill[data-state="error"] .dot { background: var(--cancelled); }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--live) 60%, transparent); }
            70% { box-shadow: 0 0 0 8px transparent; }
            100% { box-shadow: 0 0 0 0 transparent; }
        }

        .icon-button {
            width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text); cursor: pointer; display: grid; place-items: center;
        }

        .icon { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .meta .icon { width: 14px; height: 14px; vertical-align: -2px; margin-right: 4px; }
        :root[data-theme="dark"] .theme-dark, :root:not([data-theme="dark"]) .theme-light { display: none; }
        .status-icon .icon { width: 20px; height: 20px; stroke-width: 2.5; }
        .feed-actions { display: flex; align-items: center; gap: 10px; }

        .layout { display: grid; grid-template-columns: 340px 1fr; gap: 20px; align-items: start; }

        @media (max-width: 860px) { .layout { grid-template-columns: 1fr; } }

        .card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 20px;
        }

        .card + .card { margin-top: 20px; }
        .card h2 { font-size: 15px; margin: 0 0 4px; }
        .card .hint { color: var(--text-muted); font-size: 13px; margin: 0 0 16px; }

        label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 6px; }

        input {
            width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--border);
            background: var(--surface-muted); color: var(--text); font: inherit;
        }

        input:focus { outline: 2px solid var(--accent); outline-offset: 1px; }

        .button {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 14px; border-radius: 10px; border: 1px solid transparent; cursor: pointer;
            font: inherit; font-weight: 600; background: var(--accent); color: #fff; width: 100%; margin-top: 16px;
        }

        .button[disabled] { opacity: .6; cursor: wait; }
        .button.secondary { background: var(--surface-muted); color: var(--text); border-color: var(--border); }
        .button.small { width: auto; margin: 0; padding: 6px 10px; font-size: 13px; }

        .accounts { display: grid; gap: 8px; }

        .account {
            display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 10px; cursor: pointer;
            border: 1px solid var(--border); background: var(--surface-muted); color: var(--text); font: inherit; text-align: left;
        }

        .account:hover { border-color: var(--accent); }

        .avatar {
            width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; flex: none;
            background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 13px;
        }

        .account small { display: block; color: var(--text-muted); font-size: 12px; }

        .role {
            margin-left: auto; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
            padding: 2px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent);
        }

        .error { color: var(--cancelled); font-size: 13px; margin-top: 10px; min-height: 1em; }

        .profile { display: flex; align-items: center; gap: 12px; }
        .profile .avatar { width: 44px; height: 44px; font-size: 16px; }
        .profile strong { display: block; }
        .profile span { color: var(--text-muted); font-size: 13px; }

        ol.steps { margin: 0; padding: 0; list-style: none; counter-reset: step; }

        ol.steps li {
            counter-increment: step; position: relative; padding: 0 0 14px 36px; color: var(--text-muted); font-size: 13px;
        }

        ol.steps li::before {
            content: counter(step); position: absolute; left: 0; top: -1px; width: 24px; height: 24px; border-radius: 50%;
            display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: 12px;
        }

        ol.steps strong { color: var(--text); }

        .command {
            display: flex; align-items: center; gap: 8px; margin-top: 6px; padding: 8px 10px; border-radius: 8px;
            background: var(--surface-muted); border: 1px solid var(--border); color: var(--text);
        }

        .command code { flex: 1; overflow-x: auto; white-space: nowrap; }

        .copy {
            border: 0; background: transparent; color: var(--accent); cursor: pointer; font: inherit; font-size: 12px; font-weight: 600;
        }

        .feed-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .feed-header h2 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 17px; }

        .badge {
            min-width: 22px; padding: 1px 8px; border-radius: 999px; background: var(--accent); color: #fff;
            font-size: 12px; font-weight: 700; text-align: center;
        }

        .badge[hidden] { display: none; }

        .tabs { display: inline-flex; padding: 3px; border-radius: 10px; background: var(--surface-muted); border: 1px solid var(--border); }

        .tab {
            border: 0; background: transparent; color: var(--text-muted); padding: 6px 12px; border-radius: 8px;
            font: inherit; font-size: 13px; font-weight: 600; cursor: pointer;
        }

        .tab[aria-selected="true"] { background: var(--surface); color: var(--text); box-shadow: var(--shadow); }

        .feed { display: grid; gap: 12px; }

        .notification {
            display: grid; grid-template-columns: auto 1fr auto; gap: 14px; align-items: start; padding: 16px;
            border-radius: 12px; border: 1px solid var(--border); background: var(--surface);
        }

        .notification.unread { border-left: 4px solid var(--accent); padding-left: 13px; }

        .notification.fresh { animation: arrive 1.8s ease-out; }

        @keyframes arrive {
            0% { transform: translateY(-8px); opacity: 0; box-shadow: 0 0 0 0 transparent; }
            15% { transform: none; opacity: 1; box-shadow: 0 0 0 6px var(--accent-soft); }
            100% { box-shadow: 0 0 0 0 transparent; }
        }

        .status-icon {
            width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; font-weight: 700;
        }

        .approved .status-icon { background: var(--approved-soft); color: var(--approved); }
        .cancelled .status-icon { background: var(--cancelled-soft); color: var(--cancelled); }

        .notification p { margin: 0; }
        .notification .message { font-weight: 600; }

        .meta { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 6px; color: var(--text-muted); font-size: 13px; }

        .tag {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 2px 8px; border-radius: 999px;
        }

        .approved .tag { background: var(--approved-soft); color: var(--approved); }
        .cancelled .tag { background: var(--cancelled-soft); color: var(--cancelled); }

        .side { display: grid; gap: 8px; justify-items: end; }
        .when { color: var(--text-muted); font-size: 12px; white-space: nowrap; }

        .empty {
            text-align: center; padding: 48px 16px; color: var(--text-muted); border: 1px dashed var(--border); border-radius: 12px;
        }

        .empty .big { font-size: 32px; margin-bottom: 8px; }

        .toast {
            position: fixed; right: 20px; bottom: 20px; max-width: 360px; padding: 14px 16px; border-radius: 12px;
            background: var(--text); color: var(--bg); box-shadow: var(--shadow); transform: translateY(140%);
            transition: transform .3s ease; font-size: 14px;
        }

        .toast.show { transform: none; }
        .toast strong { display: block; margin-bottom: 2px; }

        footer { margin-top: 32px; color: var(--text-muted); font-size: 12px; text-align: center; }

        [hidden] { display: none !important; }
    </style>
</head>
<body>
<div class="shell">
    <header class="top">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">✈</div>
            <div>
                <h1>Notificações ao vivo</h1>
                <p>Travel Request Service · decisões de pedidos chegando pelo Reverb</p>
            </div>
        </div>
        <div class="top-actions">
            <span class="pill" id="connection" data-state="idle"><span class="dot"></span><span id="connection-text">Desconectado</span></span>
            <button class="icon-button" id="theme" type="button" title="Alternar tema" aria-label="Alternar tema">
                <svg class="icon theme-dark" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>
                <svg class="icon theme-light" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
        </div>
    </header>

    <div class="layout">
        <aside>
            <section class="card" id="login-card">
                <h2>Entrar</h2>
                <p class="hint">Escolha uma conta de demonstração ou entre com e-mail e senha.</p>
                <div class="accounts" id="accounts"></div>
                <form id="login-form" autocomplete="on">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" required placeholder="luffy@example.com">
                    <label for="password">Senha</label>
                    <input id="password" name="password" type="password" required placeholder="••••••••">
                    <button class="button" id="login-button" type="submit">Entrar e conectar</button>
                    <div class="error" id="login-error" role="alert"></div>
                </form>
            </section>

            <section class="card" id="profile-card" hidden>
                <div class="profile">
                    <div class="avatar" id="profile-avatar"></div>
                    <div>
                        <strong id="profile-name"></strong>
                        <span id="profile-email"></span>
                    </div>
                    <span class="role" id="profile-role"></span>
                </div>
                <button class="button secondary" id="logout" type="button">Sair</button>
            </section>

            <section class="card">
                <h2>Como provocar uma notificação</h2>
                <p class="hint">Um admin decide um pedido seu, e a notificação chega aqui sem recarregar a página.</p>
                <ol class="steps">
                    <li><strong>Entre como Luffy</strong> nesta página.</li>
                    <li>
                        <strong>Rode a demo</strong>, que cria um pedido do Luffy e o aprova como admin:
                        <div class="command"><code>make demo</code><button class="copy" type="button" data-copy="make demo">Copiar</button></div>
                    </li>
                    <li>
                        <strong>Ou use a API:</strong> crie um pedido como Luffy e aprove como admin com
                        <code>POST /api/v1/travel-requests/{id}/approve</code> e <code>If-Match: "1"</code>.
                    </li>
                    <li><strong>Veja a decisão chegar</strong> ao vivo, e o e-mail no Mailpit.</li>
                </ol>
            </section>
        </aside>

        <main class="card">
            <div class="feed-header">
                <h2>Notificações <span class="badge" id="unread-badge" hidden>0</span></h2>
                <div class="feed-actions">
                    <button class="button secondary small" id="read-all" type="button" hidden>Marcar todas como lidas</button>
                    <div class="tabs" role="tablist">
                        <button class="tab" type="button" role="tab" aria-selected="true" data-filter="all">Todas</button>
                        <button class="tab" type="button" role="tab" aria-selected="false" data-filter="unread">Não lidas</button>
                    </div>
                </div>
            </div>
            <div class="feed" id="feed" aria-live="polite"></div>
        </main>
    </div>

    <footer>Página de demonstração, disponível só com <code>APP_ENV=local</code>. Canal privado <code>users.{id}</code>, autorizado com o JWT.</footer>
</div>

<div class="toast" id="toast" role="status"><strong id="toast-title"></strong><span id="toast-body"></span></div>

<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
    const reverb = @json($reverb);
</script>
@verbatim
<script>
    const API = '/api/v1';
    const EVENT = '.travel_request.decided';
    const STORAGE_KEY = 'travel-request-playground';
    const DEMO_PASSWORD = 'password123';
    const DEMO_ACCOUNTS = [
        { name: 'Monkey D. Luffy', email: 'luffy@example.com', role: 'user' },
        { name: 'Roronoa Zoro', email: 'zoro@example.com', role: 'user' },
        { name: 'Admin', email: 'admin@example.com', role: 'admin' },
    ];
    const STATUS_LABELS = { approved: 'Aprovado', cancelled: 'Cancelado' };

    const elements = {
        accounts: document.getElementById('accounts'),
        loginCard: document.getElementById('login-card'),
        loginForm: document.getElementById('login-form'),
        loginButton: document.getElementById('login-button'),
        loginError: document.getElementById('login-error'),
        email: document.getElementById('email'),
        password: document.getElementById('password'),
        profileCard: document.getElementById('profile-card'),
        connection: document.getElementById('connection'),
        connectionText: document.getElementById('connection-text'),
        feed: document.getElementById('feed'),
        unreadBadge: document.getElementById('unread-badge'),
        toast: document.getElementById('toast'),
    };

    const state = { session: null, user: null, notifications: [], filter: 'all', echo: null, toastTimer: null };

    function readStorage() {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null');
        } catch {
            return null;
        }
    }

    function writeStorage(session) {
        try {
            session ? sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session)) : sessionStorage.removeItem(STORAGE_KEY);
        } catch {
        }
    }

    const ICONS = {
        approved: '<path d="M20 6 9 17l-5-5"/>',
        cancelled: '<path d="M18 6 6 18M6 6l12 12"/>',
        place: '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
    };

    function icon(name) {
        const element = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        element.setAttribute('viewBox', '0 0 24 24');
        element.setAttribute('class', 'icon');
        element.setAttribute('aria-hidden', 'true');
        element.innerHTML = ICONS[name];

        return element;
    }

    function initialsOf(name) {
        const parts = name.split(' ').filter(Boolean);
        const edges = parts.length > 1 ? [parts[0], parts[parts.length - 1]] : parts;

        return edges.map((part) => part[0].toUpperCase()).join('');
    }

    function formatDate(isoDate) {
        const [year, month, day] = isoDate.split('-');

        return `${day}/${month}/${year}`;
    }

    function relativeTime(isoMoment) {
        const seconds = Math.round((Date.now() - new Date(isoMoment).getTime()) / 1000);

        if (seconds < 45) return 'agora';
        if (seconds < 3600) return `há ${Math.round(seconds / 60)} min`;
        if (seconds < 86400) return `há ${Math.round(seconds / 3600)} h`;

        return new Date(isoMoment).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function setConnection(connectionState, text) {
        elements.connection.dataset.state = connectionState;
        elements.connectionText.textContent = text;
    }

    function showToast(title, body) {
        document.getElementById('toast-title').textContent = title;
        document.getElementById('toast-body').textContent = body;
        elements.toast.classList.add('show');
        clearTimeout(state.toastTimer);
        state.toastTimer = setTimeout(() => elements.toast.classList.remove('show'), 4500);
    }

    async function request(path, options = {}, isRetry = false) {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json', ...(options.headers || {}) };

        if (state.session) {
            headers.Authorization = `Bearer ${state.session.accessToken}`;
        }

        const response = await fetch(API + path, { ...options, headers });

        if (response.status === 401 && state.session && !isRetry && await refreshTokens()) {
            return request(path, options, true);
        }

        return response;
    }

    async function refreshTokens() {
        const response = await fetch(`${API}/auth/refresh`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: state.session.refreshToken }),
        });

        if (!response.ok) {
            signOut();

            return false;
        }

        storeTokens(await response.json());

        return true;
    }

    function storeTokens(tokenPair) {
        state.session = { accessToken: tokenPair.access_token, refreshToken: tokenPair.refresh_token };
        writeStorage(state.session);

        if (state.echo) {
            state.echo.connector.options.auth.headers.Authorization = `Bearer ${state.session.accessToken}`;
        }
    }

    async function signIn(email, password) {
        elements.loginButton.disabled = true;
        elements.loginError.textContent = '';

        try {
            const response = await fetch(`${API}/auth/login`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password }),
            });

            if (!response.ok) {
                const problem = await response.json().catch(() => ({}));
                elements.loginError.textContent = problem.detail || problem.title || 'Não foi possível entrar.';

                return;
            }

            storeTokens(await response.json());
            await start();
        } catch {
            elements.loginError.textContent = 'A API não respondeu. O app está no ar (make up)?';
        } finally {
            elements.loginButton.disabled = false;
        }
    }

    async function start() {
        const meResponse = await request('/auth/me');

        if (!meResponse.ok) {
            signOut();

            return;
        }

        const me = await meResponse.json();
        state.user = me.data || me;
        showProfile();
        await loadNotifications();
        connect();
    }

    function showProfile() {
        elements.loginCard.hidden = true;
        elements.profileCard.hidden = false;
        document.getElementById('profile-avatar').textContent = initialsOf(state.user.name);
        document.getElementById('profile-name').textContent = state.user.name;
        document.getElementById('profile-email').textContent = state.user.email;
        document.getElementById('profile-role').textContent = state.user.role;
    }

    async function loadNotifications() {
        const response = await request('/notifications?per_page=50');

        if (!response.ok) {
            return;
        }

        const page = await response.json();
        state.notifications = page.data;
        render();
    }

    function connect() {
        setConnection('connecting', 'Conectando…');
        state.echo = new Echo({
            broadcaster: 'reverb',
            key: reverb.key,
            wsHost: reverb.host,
            wsPort: reverb.port,
            wssPort: reverb.port,
            forceTLS: reverb.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { Authorization: `Bearer ${state.session.accessToken}`, Accept: 'application/json' } },
        });

        const pusher = state.echo.connector.pusher;
        pusher.connection.bind('unavailable', () => setConnection('error', 'Reverb indisponível'));
        pusher.connection.bind('failed', () => setConnection('error', 'Sem WebSocket'));

        state.echo.private(`users.${state.user.id}`)
            .subscribed(() => setConnection('live', 'Ao vivo'))
            .error(() => setConnection('error', 'Inscrição recusada'))
            .listen(EVENT, (notification) => arrive(notification));
    }

    function arrive(notification) {
        if (state.notifications.some((known) => known.id === notification.id)) {
            return;
        }

        state.notifications.unshift({ ...notification, read_at: null, fresh: true });
        render();
        const status = STATUS_LABELS[notification.data.status] || notification.data.status;
        showToast(`Pedido ${status.toLowerCase()}`, notification.message);
    }

    async function markAllRead() {
        const unread = state.notifications.filter((notification) => !notification.read_at);
        await Promise.all(unread.map((notification) => markRead(notification.id)));
    }

    async function markRead(id) {
        const response = await request(`/notifications/${id}/read`, { method: 'POST' });

        if (!response.ok) {
            return;
        }

        const notification = state.notifications.find((known) => known.id === id);
        notification.read_at = new Date().toISOString();
        notification.fresh = false;
        render();
    }

    function signOut() {
        if (state.session) {
            fetch(`${API}/auth/logout`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${state.session.accessToken}`,
                },
                body: JSON.stringify({ refresh_token: state.session.refreshToken }),
            }).catch(() => {});
        }

        state.echo?.disconnect();
        Object.assign(state, { session: null, user: null, notifications: [], echo: null });
        writeStorage(null);
        elements.loginCard.hidden = false;
        elements.profileCard.hidden = true;
        setConnection('idle', 'Desconectado');
        render();
    }

    function notificationElement(notification) {
        const details = notification.data;
        const status = details.status;
        const item = document.createElement('article');
        item.className = `notification ${status}`;
        item.classList.toggle('unread', !notification.read_at);
        item.classList.toggle('fresh', Boolean(notification.fresh));

        const statusIcon = document.createElement('div');
        statusIcon.className = 'status-icon';
        statusIcon.append(icon(status === 'approved' ? 'approved' : 'cancelled'));

        const body = document.createElement('div');
        const message = document.createElement('p');
        message.className = 'message';
        message.textContent = notification.message;
        const meta = document.createElement('div');
        meta.className = 'meta';
        const tag = document.createElement('span');
        tag.className = 'tag';
        tag.textContent = STATUS_LABELS[status] || status;
        const destination = document.createElement('span');
        destination.append(icon('place'), details.destination);
        const dates = document.createElement('span');
        dates.append(icon('calendar'), `${formatDate(details.departure_date)} → ${formatDate(details.return_date)}`);
        const reference = document.createElement('span');
        reference.className = 'mono';
        reference.title = details.travel_request_id;
        reference.textContent = `#${details.travel_request_id.slice(-8)}`;
        meta.append(tag, destination, dates, reference);
        body.append(message, meta);

        const side = document.createElement('div');
        side.className = 'side';
        const when = document.createElement('span');
        when.className = 'when';
        when.textContent = relativeTime(notification.created_at);
        when.title = new Date(notification.created_at).toLocaleString('pt-BR');
        side.append(when);

        if (!notification.read_at) {
            const read = document.createElement('button');
            read.className = 'button secondary small';
            read.type = 'button';
            read.textContent = 'Marcar como lida';
            read.addEventListener('click', () => markRead(notification.id));
            side.append(read);
        }

        item.append(statusIcon, body, side);

        return item;
    }

    function emptyState() {
        const empty = document.createElement('div');
        empty.className = 'empty';
        const isSignedIn = Boolean(state.user);
        const isUnreadFilter = state.filter === 'unread';
        const icon = isSignedIn ? '🔔' : '🔐';
        let text = 'Entre com uma conta para ver as notificações dela.';

        if (isSignedIn) {
            text = isUnreadFilter ? 'Nenhuma notificação não lida.' : 'Nenhuma notificação ainda. Rode make demo e veja a decisão chegar.';
        }

        empty.innerHTML = `<div class="big">${icon}</div>`;
        empty.append(text);

        return empty;
    }

    function render() {
        const unread = state.notifications.filter((notification) => !notification.read_at).length;
        const visible = state.filter === 'unread'
            ? state.notifications.filter((notification) => !notification.read_at)
            : state.notifications;

        elements.unreadBadge.hidden = unread === 0;
        document.getElementById('read-all').hidden = unread === 0;
        elements.unreadBadge.textContent = String(unread);
        document.title = unread > 0 ? `(${unread}) Notificações ao vivo` : 'Notificações ao vivo · Travel Request Service';
        elements.feed.replaceChildren(...(visible.length > 0 ? visible.map(notificationElement) : [emptyState()]));
        state.notifications.forEach((notification) => { notification.fresh = false; });
    }

    function renderAccounts() {
        for (const account of DEMO_ACCOUNTS) {
            const button = document.createElement('button');
            button.className = 'account';
            button.type = 'button';
            button.innerHTML = `<span class="avatar">${initialsOf(account.name)}</span><span><span class="name"></span><small></small></span><span class="role">${account.role}</span>`;
            button.querySelector('.name').textContent = account.name;
            button.querySelector('small').textContent = account.email;
            button.addEventListener('click', () => {
                elements.email.value = account.email;
                elements.password.value = DEMO_PASSWORD;
                signIn(account.email, DEMO_PASSWORD);
            });
            elements.accounts.append(button);
        }
    }

    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;

        try {
            localStorage.setItem(`${STORAGE_KEY}-theme`, theme);
        } catch {
        }
    }

    function initialTheme() {
        try {
            const saved = localStorage.getItem(`${STORAGE_KEY}-theme`);

            if (saved) return saved;
        } catch {
        }

        return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    elements.loginForm.addEventListener('submit', (event) => {
        event.preventDefault();
        signIn(elements.email.value.trim(), elements.password.value);
    });

    document.getElementById('logout').addEventListener('click', signOut);
    document.getElementById('read-all').addEventListener('click', markAllRead);
    document.getElementById('theme').addEventListener('click', () => {
        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
    });

    document.querySelectorAll('.tab').forEach((tab) => tab.addEventListener('click', () => {
        state.filter = tab.dataset.filter;
        document.querySelectorAll('.tab').forEach((other) => other.setAttribute('aria-selected', String(other === tab)));
        render();
    }));

    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        await navigator.clipboard?.writeText(button.dataset.copy);
        button.textContent = 'Copiado';
        setTimeout(() => { button.textContent = 'Copiar'; }, 1500);
    }));

    setInterval(() => state.notifications.length && render(), 60000);

    applyTheme(initialTheme());
    renderAccounts();
    render();

    const storedSession = readStorage();

    if (storedSession) {
        state.session = storedSession;
        start().catch(() => signOut());
    }
</script>
@endverbatim
</body>
</html>
