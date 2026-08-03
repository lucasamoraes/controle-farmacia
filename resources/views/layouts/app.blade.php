<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#102a43">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Controle Farmacia">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ config('app.name', 'Controle Farmacia') }}</title>
    <style>
        :root { --bg:#f5f7fb; --panel:#ffffff; --ink:#17202a; --muted:#697586; --line:#d9e1ec; --brand:#0f766e; --brand-dark:#115e59; --danger:#b42318; --warn:#b7791f; --sidebar:#102a43; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial, Helvetica, sans-serif; background:var(--bg); color:var(--ink); font-size:14px; }
        body.nav-open { overflow:hidden; }
        a { color:inherit; text-decoration:none; }
        .app { min-height:100vh; display:grid; grid-template-columns:240px minmax(0,1fr); }
        .sidebar { background:var(--sidebar); color:#eef6ff; padding:22px 18px; display:flex; flex-direction:column; gap:22px; min-height:100vh; position:sticky; top:0; }
        .brand { font-size:18px; font-weight:700; line-height:1.2; }
        .brand span { display:block; color:#a8c5da; font-size:12px; font-weight:400; margin-top:4px; }
        .nav { display:grid; gap:6px; }
        .nav a { padding:10px 12px; border-radius:6px; color:#d9e7f2; }
        .nav a:hover, .nav a.active { background:#1d4966; color:#fff; }
        .sidebar-footer { margin-top:auto; color:#bdd0df; font-size:12px; }
        .main { min-width:0; }
        .topbar { min-height:64px; background:var(--panel); border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; padding:0 28px; gap:14px; position:sticky; top:0; z-index:20; }
        .topbar-left, .topbar-actions { display:flex; align-items:center; gap:12px; min-width:0; }
        .menu-toggle { display:none; border:1px solid var(--line); background:#fff; color:var(--ink); width:40px; height:40px; border-radius:6px; font-size:22px; line-height:1; cursor:pointer; }
        .install-btn { display:none; }
        .content { padding:28px; max-width:1180px; }
        .title { margin:0 0 4px; font-size:24px; }
        .subtitle { margin:0; color:var(--muted); }
        .actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .btn { border:0; background:var(--brand); color:#fff; padding:10px 14px; border-radius:6px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; min-height:38px; white-space:nowrap; }
        .btn:hover { background:var(--brand-dark); }
        .btn.secondary { background:#e8eef5; color:#243b53; }
        .btn.danger { background:var(--danger); }
        .btn.small { padding:7px 10px; min-height:32px; font-size:12px; }
        .grid { display:grid; gap:16px; }
        .stats { grid-template-columns:repeat(4, minmax(0,1fr)); margin:22px 0; }
        .card { background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:18px; }
        .metric-label { color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        .metric-value { font-size:24px; font-weight:800; margin-top:8px; }
        .panel-title { margin:0 0 14px; font-size:17px; }
        .table-wrap { width:100%; overflow-x:auto; border:1px solid var(--line); border-radius:8px; background:var(--panel); }
        table { width:100%; border-collapse:collapse; background:var(--panel); }
        th, td { padding:12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; }
        th { background:#eef3f8; color:#4a5568; font-size:12px; text-transform:uppercase; }
        tr:last-child td { border-bottom:0; }
        .status { display:inline-flex; border-radius:999px; padding:4px 9px; font-size:12px; font-weight:700; background:#e8eef5; color:#334e68; }
        .status.paid { background:#def7ec; color:#03543f; }
        .status.open { background:#e1effe; color:#1e429f; }
        .status.overdue { background:#fde8e8; color:#9b1c1c; }
        .status.cancelled { background:#f1f5f9; color:#64748b; }
        .role-pill { display:inline-flex; border-radius:999px; padding:5px 9px; font-size:12px; font-weight:700; background:#e8eef5; color:#243b53; }
        .form { max-width:760px; background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:20px; display:grid; gap:16px; }
        .filter-bar { margin-top:18px; background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:14px; display:grid; gap:12px; }
        .filter-grid { display:grid; grid-template-columns:2fr repeat(4, minmax(130px, 1fr)); gap:10px; align-items:end; }
        .filter-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .quick-filters { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .quick-filter { display:inline-flex; align-items:center; min-height:32px; padding:7px 10px; border:1px solid var(--line); border-radius:6px; color:#243b53; background:#fff; font-weight:700; font-size:12px; }
        .quick-filter.active { background:var(--brand); color:#fff; border-color:var(--brand); }
        .bar-list { display:grid; gap:12px; }
        .bar-row { display:grid; gap:6px; }
        .bar-meta { display:flex; justify-content:space-between; gap:12px; color:#334e68; font-weight:700; }
        .bar-track { height:9px; background:#e8eef5; border-radius:999px; overflow:hidden; }
        .bar-fill { height:100%; width:var(--w, 0%); background:var(--c, var(--brand)); border-radius:999px; }
        .field-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        label { display:grid; gap:6px; font-weight:700; color:#344054; }
        input, select, textarea { width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:10px 11px; font:inherit; background:#fff; color:var(--ink); min-height:40px; }
        textarea { min-height:90px; resize:vertical; }
        .error { color:var(--danger); font-size:12px; margin-top:4px; }
        .alert { border:1px solid #b7ead8; background:#ecfdf5; color:#065f46; padding:12px 14px; border-radius:6px; margin:18px 0; }
        .alert.warning { border-color:#fed7aa; background:#fff7ed; color:#9a3412; }
        .alert.danger { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
        .alert.info { border-color:#bfdbfe; background:#eff6ff; color:#1e40af; }
        .alert strong { display:block; margin-bottom:4px; color:inherit; }
        .auth-shell { min-height:100vh; display:grid; place-items:center; padding:24px; background:#eef3f8; }
        .auth-box { width:min(460px, 100%); background:#fff; border:1px solid var(--line); border-radius:8px; padding:26px; }
        .auth-box h1 { margin:0 0 6px; font-size:24px; }
        .auth-box p { margin:0 0 20px; color:var(--muted); }
        .pagination { margin-top:16px; }
        .pager { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:13px; color:var(--muted); }
        .pager-actions { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .pager-btn { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 10px; border:1px solid var(--line); border-radius:6px; background:#fff; color:#243b53; font-weight:700; }
        .pager-btn.active { background:var(--brand); border-color:var(--brand); color:#fff; }
        .pager-btn.disabled { opacity:.45; cursor:not-allowed; }
        .pagination svg, .pager svg { width:16px !important; height:16px !important; max-width:16px !important; max-height:16px !important; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.42); z-index:80; }
        .modal { position:fixed; inset:0; z-index:90; display:grid; place-items:center; padding:18px; }
        .modal[hidden], .modal-backdrop[hidden] { display:none; }
        .modal-panel { width:min(560px, 100%); background:var(--panel); border:1px solid var(--line); border-radius:8px; padding:20px; box-shadow:0 24px 70px rgba(15,23,42,.24); }
        .mobile-backdrop { display:none; }
        @media (max-width: 980px) { .stats { grid-template-columns:repeat(2, minmax(0,1fr)); } .content { padding:22px; } }
        @media (max-width: 760px) {
            .app { display:block; }
            .sidebar { position:fixed; inset:0 auto 0 0; width:min(82vw, 300px); min-height:100vh; transform:translateX(-105%); transition:transform .18s ease; z-index:50; box-shadow:20px 0 40px rgba(16,42,67,.22); }
            body.nav-open .sidebar { transform:translateX(0); }
            .mobile-backdrop { display:none; position:fixed; inset:0; background:rgba(15,23,42,.38); z-index:40; border:0; }
            body.nav-open .mobile-backdrop { display:block; }
            .menu-toggle { display:inline-flex; align-items:center; justify-content:center; }
            .topbar { padding:12px 14px; align-items:center; }
            .topbar-actions { margin-left:auto; }
            .topbar-actions form { display:none; }
            .content { padding:16px; max-width:none; }
            .stats, .field-grid { grid-template-columns:1fr; }
            .filter-grid { grid-template-columns:1fr; }
            .filter-actions .btn, .quick-filter { flex:1 1 auto; }
            .grid[style*="grid-template-columns"] { grid-template-columns:1fr !important; }
            .title { font-size:22px; }
            .metric-value { font-size:22px; }
            .actions { align-items:stretch; }
            .actions .btn { flex:1 1 auto; }
            .form { max-width:none; padding:16px; }
            th, td { padding:10px; white-space:nowrap; }
            .pager { justify-content:center; }
            .pager-summary { width:100%; text-align:center; }
        }
        @media (max-width: 420px) { .stats { grid-template-columns:1fr; } .btn { width:100%; } .topbar .btn { width:auto; } }
    </style>
</head>
<body>
    @auth
        <div class="app">
            <button class="mobile-backdrop" type="button" data-close-menu aria-label="Fechar menu"></button>
            <aside class="sidebar" id="app-sidebar">
                <div class="brand">Controle Farmacia<span>{{ $company->trade_name ?? $company->name ?? 'Financeiro' }}</span></div>
                <nav class="nav">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                    <a href="{{ route('resumo.index') }}" class="{{ request()->routeIs('resumo.*') ? 'active' : '' }}">Resumo</a>
                    <a href="{{ route('faturamento-mensal.index') }}" class="{{ request()->routeIs('faturamento-mensal.*') ? 'active' : '' }}">Faturamento</a>
                    <a href="{{ route('fornecedores.index') }}" class="{{ request()->routeIs('fornecedores.*') ? 'active' : '' }}">Fornecedores</a>
                    <a href="{{ route('funcionarios.index') }}" class="{{ request()->routeIs('funcionarios.*') ? 'active' : '' }}">Funcionarios</a>
                    <a href="{{ route('contas-a-pagar.index') }}" class="{{ request()->routeIs('contas-a-pagar.*') ? 'active' : '' }}">Contas a pagar</a>
                    @if (auth()->user()->canWriteFinance($company))
                        <a href="{{ route('boletos.create') }}" class="{{ request()->routeIs('boletos.*') ? 'active' : '' }}">Boletos PDF</a>
                        <a href="{{ route('imports.boletos.create') }}" class="{{ request()->routeIs('imports.boletos.*') ? 'active' : '' }}">Importar</a>
                        <a href="{{ route('imports.vendas-diarias.create') }}" class="{{ request()->routeIs('imports.vendas-diarias.*') ? 'active' : '' }}">Vendas diarias</a>
                    @endif
                    @if (auth()->user()->canManageUsers($company))
                        <a href="{{ route('usuarios.index') }}" class="{{ request()->routeIs('usuarios.*') ? 'active' : '' }}">Usuarios</a>
                    @endif
                </nav>
                <div class="sidebar-footer">{{ auth()->user()->name }}<br>{{ ['owner' => 'Dono', 'finance' => 'Financeiro', 'viewer' => 'Consulta'][auth()->user()->roleForCompany($company)] ?? 'Usuario' }}</div>
            </aside>
            <main class="main">
                <header class="topbar">
                    <div class="topbar-left">
                        <button class="menu-toggle" type="button" data-menu-toggle aria-controls="app-sidebar" aria-expanded="false">=</button>
                        <div>
                            <strong>{{ $pageTitle ?? 'Financeiro' }}</strong>
                            <div style="color:var(--muted); font-size:12px; margin-top:3px;">{{ now()->format('d/m/Y') }}</div>
                        </div>
                    </div>
                    <div class="topbar-actions">
                        <button class="btn secondary install-btn" type="button" data-install-app>Instalar app</button>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn secondary" type="submit">Sair</button>
                        </form>
                    </div>
                </header>
                <section class="content">
                    @if (session('status'))
                        <div class="alert">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert danger">
                            <strong>Revise as informacoes</strong>
                            {{ $errors->first() }}
                        </div>
                    @endif
                    @if (session('app_alert'))
                        @php $appAlert = session('app_alert'); @endphp
                        <div class="alert {{ $appAlert['level'] ?? 'info' }}">
                            <strong>{{ $appAlert['title'] ?? 'Aviso' }}</strong>
                            {{ $appAlert['message'] ?? '' }}
                        </div>
                    @endif
                    @yield('content')
                </section>
            </main>
        </div>
    @else
        @yield('content')
    @endauth
    <div class="modal-backdrop" data-confirm-backdrop hidden></div>
    <div class="modal" data-confirm-dialog hidden role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <div class="modal-panel">
            <h2 id="confirm-modal-title" class="panel-title" data-confirm-title>Confirmar acao</h2>
            <p class="subtitle" data-confirm-message></p>
            <div class="actions" style="justify-content:flex-end; margin-top:18px;">
                <button class="btn secondary" type="button" data-confirm-cancel>Voltar</button>
                <button class="btn danger" type="button" data-confirm-ok>Confirmar</button>
            </div>
        </div>
    </div>
    <script>
        const body = document.body;
        const menuButton = document.querySelector('[data-menu-toggle]');
        const closeButtons = document.querySelectorAll('[data-close-menu], .nav a');
        if (menuButton) {
            menuButton.addEventListener('click', () => {
                const open = body.classList.toggle('nav-open');
                menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        closeButtons.forEach((item) => item.addEventListener('click', () => {
            body.classList.remove('nav-open');
            menuButton?.setAttribute('aria-expanded', 'false');
        }));

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js').catch(() => {}));
        }

        let deferredPrompt;
        const installButton = document.querySelector('[data-install-app]');
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredPrompt = event;
            if (installButton) installButton.style.display = 'inline-flex';
        });
        installButton?.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            await deferredPrompt.userChoice;
            deferredPrompt = null;
            installButton.style.display = 'none';
        });

        const payDialog = document.querySelector('[data-pay-dialog]');
        const payBackdrop = document.querySelector('[data-modal-backdrop]');
        const payLine = document.querySelector('[data-pay-line]');
        const payForm = document.querySelector('[data-pay-form]');
        const paySummary = document.querySelector('[data-pay-summary]');
        const payFeedback = document.querySelector('[data-copy-feedback]');
        const closePayModal = () => {
            if (!payDialog || !payBackdrop) return;
            payDialog.hidden = true;
            payBackdrop.hidden = true;
        };
        document.querySelectorAll('[data-pay-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const line = button.dataset.line || '';
                if (payLine) payLine.value = line || 'Sem linha digitavel cadastrada para esta conta.';
                if (payForm) payForm.action = button.dataset.payUrl || '';
                if (paySummary) paySummary.textContent = `${button.dataset.description || 'Conta'} - ${button.dataset.amount || ''}`;
                if (payFeedback) payFeedback.textContent = '';
                if (payDialog && payBackdrop) {
                    payDialog.hidden = false;
                    payBackdrop.hidden = false;
                }
            });
        });
        document.querySelector('[data-close-pay-modal]')?.addEventListener('click', closePayModal);
        payBackdrop?.addEventListener('click', closePayModal);
        document.querySelector('[data-copy-pay-line]')?.addEventListener('click', async () => {
            const text = payLine?.value || '';
            if (!text || text.startsWith('Sem linha')) {
                if (payFeedback) payFeedback.textContent = 'Nao ha linha digitavel cadastrada.';
                return;
            }
            try {
                await navigator.clipboard.writeText(text);
                if (payFeedback) payFeedback.textContent = 'Linha digitavel copiada.';
            } catch (_) {
                payLine?.select();
                document.execCommand('copy');
                if (payFeedback) payFeedback.textContent = 'Linha digitavel copiada.';
            }
        });

        const confirmDialog = document.querySelector('[data-confirm-dialog]');
        const confirmBackdrop = document.querySelector('[data-confirm-backdrop]');
        const confirmTitle = confirmDialog?.querySelector('[data-confirm-title]');
        const confirmMessage = confirmDialog?.querySelector('[data-confirm-message]');
        const confirmOk = confirmDialog?.querySelector('[data-confirm-ok]');
        const confirmCancel = confirmDialog?.querySelector('[data-confirm-cancel]');
        let pendingConfirmForm = null;
        const closeConfirm = () => {
            pendingConfirmForm = null;
            if (confirmDialog) confirmDialog.hidden = true;
            if (confirmBackdrop) confirmBackdrop.hidden = true;
        };
        document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;
                event.preventDefault();
                pendingConfirmForm = form;
                if (confirmTitle) confirmTitle.textContent = form.dataset.confirmTitle || 'Confirmar acao';
                if (confirmMessage) confirmMessage.textContent = form.dataset.confirmMessage || 'Deseja continuar?';
                if (confirmOk) {
                    confirmOk.textContent = form.dataset.confirmButton || 'Confirmar';
                    confirmOk.className = `btn ${form.dataset.confirmDanger === '1' ? 'danger' : ''}`.trim();
                }
                if (confirmDialog) confirmDialog.hidden = false;
                if (confirmBackdrop) confirmBackdrop.hidden = false;
            });
        });
        confirmOk?.addEventListener('click', () => {
            if (!pendingConfirmForm) return;
            pendingConfirmForm.dataset.confirmed = '1';
            pendingConfirmForm.submit();
        });
        confirmCancel?.addEventListener('click', closeConfirm);
        confirmBackdrop?.addEventListener('click', closeConfirm);

        const dailyAlertDialog = document.querySelector('[data-daily-alert-dialog]');
        const dailyAlertBackdrop = document.querySelector('[data-daily-alert-backdrop]');
        const dailyAlertClose = document.querySelector('[data-daily-alert-close]');
        if (dailyAlertDialog && dailyAlertBackdrop) {
            const key = dailyAlertDialog.dataset.alertKey;
            if (!key || localStorage.getItem(key) !== '1') {
                dailyAlertDialog.hidden = false;
                dailyAlertBackdrop.hidden = false;
                if (key) localStorage.setItem(key, '1');
            }
        }
        const closeDailyAlert = () => {
            if (dailyAlertDialog) dailyAlertDialog.hidden = true;
            if (dailyAlertBackdrop) dailyAlertBackdrop.hidden = true;
        };
        dailyAlertClose?.addEventListener('click', closeDailyAlert);
        dailyAlertBackdrop?.addEventListener('click', closeDailyAlert);
    </script>
</body>
</html>

