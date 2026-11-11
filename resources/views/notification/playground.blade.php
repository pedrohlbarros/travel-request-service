<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações ao vivo</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { max-width: 760px; margin: 2rem auto; padding: 0 1rem; }
        textarea { width: 100%; min-height: 5rem; font-family: ui-monospace, monospace; }
        button { margin-top: .5rem; padding: .4rem 1rem; }
        #status { margin: 1rem 0; font-weight: 600; }
        pre { background: rgba(127, 127, 127, .12); padding: .75rem; border-radius: 6px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>Notificações ao vivo</h1>
<p>Cole o <code>access_token</code> de um login e conecte. Cada aprovação ou cancelamento de um pedido seu aparece abaixo.</p>
<textarea id="token" placeholder="access_token"></textarea>
<button id="connect" type="button">Conectar</button>
<div id="status">Desconectado.</div>
<div id="events"></div>

<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
    const reverb = @json($reverb);
    const statusLine = document.getElementById('status');
    const eventList = document.getElementById('events');

    function subjectOf(token) {
        const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');

        return JSON.parse(atob(payload)).sub;
    }

    document.getElementById('connect').addEventListener('click', () => {
        const token = document.getElementById('token').value.trim();
        const userId = subjectOf(token);
        const echo = new Echo({
            broadcaster: 'reverb',
            key: reverb.key,
            wsHost: reverb.host,
            wsPort: reverb.port,
            wssPort: reverb.port,
            forceTLS: reverb.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' } },
        });

        echo.private('users.' + userId)
            .subscribed(() => { statusLine.textContent = 'Inscrito em users.' + userId + '.'; })
            .error((error) => { statusLine.textContent = 'Falha na inscrição: ' + JSON.stringify(error); })
            .listen('.travel_request.decided', (notification) => {
                const entry = document.createElement('pre');
                entry.textContent = JSON.stringify(notification, null, 2);
                eventList.prepend(entry);
            });
    });
</script>
</body>
</html>
