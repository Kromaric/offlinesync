<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>offlineSync — Laravel Demo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        header { text-align: center; margin-bottom: 48px; }
        header h1 { font-size: 2.5rem; font-weight: 700; color: #fff; }
        header h1 span { color: #6366f1; }
        header p { color: #94a3b8; margin-top: 8px; font-size: 1.1rem; }

        .badge {
            display: inline-block;
            background: #1e293b;
            border: 1px solid #334155;
            color: #6366f1;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 24px;
        }
        .card h2 { font-size: 1rem; font-weight: 600; color: #94a3b8; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.05em; }

        .cta {
            display: block;
            background: #6366f1;
            color: white;
            text-decoration: none;
            text-align: center;
            padding: 14px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 20px;
            transition: background 0.2s;
        }
        .cta:hover { background: #4f46e5; }

        .credentials { background: #0f172a; border-radius: 8px; padding: 16px; }
        .credential-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid #1e293b; }
        .credential-row:last-child { border-bottom: none; }
        .credential-row .label { color: #64748b; font-size: 0.85rem; }
        .credential-row .value { font-family: monospace; color: #a5f3fc; font-size: 0.9rem; }

        .endpoint-list { list-style: none; }
        .endpoint-list li { padding: 8px 0; border-bottom: 1px solid #0f172a; display: flex; align-items: center; gap: 10px; }
        .endpoint-list li:last-child { border-bottom: none; }
        .method {
            font-family: monospace;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            min-width: 50px;
            text-align: center;
        }
        .method.get { background: #166534; color: #4ade80; }
        .method.post { background: #1e3a8a; color: #93c5fd; }
        .method.put { background: #713f12; color: #fbbf24; }
        .method.delete { background: #7f1d1d; color: #fca5a5; }
        .endpoint-path { font-family: monospace; font-size: 0.85rem; color: #e2e8f0; }

        .plugin-features { list-style: none; }
        .plugin-features li { padding: 6px 0; color: #94a3b8; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .plugin-features li::before { content: '✓'; color: #4ade80; font-weight: 700; }

        .status-bar {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #4ade80; }
        .status-text { color: #94a3b8; font-size: 0.9rem; }
        .status-text strong { color: #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="badge">Demo v1.0</div>
            <h1>offline<span>Sync</span></h1>
            <p>NativePHP plugin — offline-first synchronization for Laravel</p>
        </header>

        <div class="status-bar">
            <div class="status-dot"></div>
            <div class="status-text">
                Server running — <strong>{{ config('app.url') }}</strong> &nbsp;·&nbsp;
                Laravel <strong>{{ app()->version() }}</strong> &nbsp;·&nbsp;
                SQLite &nbsp;·&nbsp;
                <strong>{{ \App\Models\User::count() }}</strong> user(s) &nbsp;·&nbsp;
                <strong>{{ \App\Models\Task::count() }}</strong> task(s)
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Test client (simulated mobile)</h2>
                <a href="/mobile" class="cta" target="_blank">
                    Open Todo client
                </a>
                <div class="credentials">
                    <div class="credential-row">
                        <span class="label">Email</span>
                        <span class="value">test@example.com</span>
                    </div>
                    <div class="credential-row">
                        <span class="label">Password</span>
                        <span class="value">password</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Features demonstrated</h2>
                <ul class="plugin-features">
                    <li><code>Syncable</code> trait on the <code>Task</code> model</li>
                    <li>Automatic queue (create / update / delete)</li>
                    <li>Push sync <code>POST /api/sync/push</code></li>
                    <li>Pull sync <code>GET /api/sync/pull/tasks</code></li>
                    <li>Conflict detection</li>
                    <li>Sanctum authentication (Bearer token)</li>
                    <li>Artisan command <code>sync:status</code></li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2>Available API routes</h2>
            <ul class="endpoint-list">
                <li><span class="method post">POST</span><span class="endpoint-path">/api/register</span></li>
                <li><span class="method post">POST</span><span class="endpoint-path">/api/login</span></li>
                <li><span class="method get">GET</span><span class="endpoint-path">/api/tasks — list tasks (auth required)</span></li>
                <li><span class="method post">POST</span><span class="endpoint-path">/api/tasks — create task → Syncable queue</span></li>
                <li><span class="method put">PUT</span><span class="endpoint-path">/api/tasks/{id} — update → Syncable queue</span></li>
                <li><span class="method delete">DELETE</span><span class="endpoint-path">/api/tasks/{id} — delete → Syncable queue</span></li>
                <li><span class="method post">POST</span><span class="endpoint-path">/api/sync/push — push local changes to server</span></li>
                <li><span class="method get">GET</span><span class="endpoint-path">/api/sync/pull/tasks — fetch server changes</span></li>
                <li><span class="method get">GET</span><span class="endpoint-path">/api/sync/status — server sync status</span></li>
                <li><span class="method get">GET</span><span class="endpoint-path">/api/sync/ping — connectivity check</span></li>
            </ul>
        </div>

        <div style="text-align:center; margin-top: 32px; color: #475569; font-size: 0.85rem;">
            Start: <code style="color:#a5f3fc">php artisan serve</code> inside <code style="color:#a5f3fc">demo/</code>
            &nbsp;·&nbsp;
            <code style="color:#a5f3fc">php artisan sync:status</code> to inspect the queue
        </div>
    </div>
</body>
</html>
