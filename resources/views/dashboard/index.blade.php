@extends('layouts.app', ['pageTitle' => 'Dashboard'])

@php
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $fmtPercent = fn ($value) => $value === null ? '-' : number_format((float) $value, 1, ',', '.') . '%';
    $maxStatus = max(collect($statusTotals)->max('value') ?? 0, 1);
    $maxSupplier = max((float) $topSuppliers->max('total'), 1);
    $maxCategory = max((float) $categoryTotals->max('total'), 1);
    $maxRevenue = max(collect($monthlyRevenueChart)->max('value') ?? 0, 1);
    $maxWeekday = max(collect($weekdayAverageChart)->max('value') ?? 0, 1);
    $maxChannel = max(collect($channelRevenueChart)->map(fn ($row) => ($row['delivery'] ?? 0) + ($row['counter'] ?? 0))->max() ?? 0, 1);
    $maxExpense = max(collect($monthlyExpenseChart)->map(fn ($row) => max($row['value'] ?? 0, $row['revenue'] ?? 0))->max() ?? 0, 1);
    $maxExpensePercent = max(collect($monthlyExpenseChart)->max('percent') ?? 0, 1);
    $maxMovementType = max(collect($employeeDashboard['movementTypes'])->max('total') ?? 0, 1);
    $maxEmployeeMovement = max(collect($employeeDashboard['employeeMovementDetails'])->max('total') ?? 0, 1);
    $lastRevenueRow = collect($monthlyRevenueChart)->first();
    $lastChannelRow = collect($channelRevenueChart)->first();
    $deliveryTicket = (($lastChannelRow['delivery_count'] ?? 0) > 0)
        ? (($lastChannelRow['delivery'] ?? 0) / max(1, ($lastChannelRow['delivery_count'] ?? 0)))
        : 0;
    $counterTicket = (($lastChannelRow['counter_count'] ?? 0) > 0)
        ? (($lastChannelRow['counter'] ?? 0) / max(1, ($lastChannelRow['counter_count'] ?? 0)))
        : 0;
    $maxEmployeeTop = max(collect($employeeDashboard['topEmployees'])->max('total') ?? 0, 1);
    $canWriteFinance = auth()->user()->canWriteFinance($company);
    $tabFilters = array_filter([
        'periodo' => $period,
        'inicio' => $dateStart,
        'fim' => $dateEnd,
        'status' => $statusFilter,
        'busca' => $search,
        'funcionario' => $employeeDashboard['selectedEmployeeId'] ?? null,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Dashboard financeiro</h1>
            <p class="subtitle">Visao gerencial de despesas, vencimentos e fornecedores.</p>
        </div>
        @if ($canWriteFinance)
        <div class="actions">
            <a class="btn secondary" href="{{ route('fornecedores.create') }}">Novo fornecedor</a>
            <a class="btn secondary" href="{{ route('boletos.create') }}">Ler boleto PDF</a>
            <a class="btn" href="{{ route('contas-a-pagar.create') }}">Nova conta</a>
        </div>
        @endif
    </div>

    @if (! empty($financialAlerts))
        <div class="modal-backdrop" data-daily-alert-backdrop hidden></div>
        <div class="modal" data-daily-alert-dialog data-alert-key="financial-alerts-{{ auth()->id() }}-{{ now()->toDateString() }}" hidden role="dialog" aria-modal="true" aria-labelledby="daily-alert-title">
            <div class="modal-panel">
                <h2 id="daily-alert-title" class="panel-title">Avisos importantes de hoje</h2>
                <div style="display:grid; gap:10px;">
                    @foreach ($financialAlerts as $alert)
                        <div class="alert {{ $alert['level'] ?? 'info' }}" style="margin:0;">
                            <strong>{{ $alert['title'] }}</strong>
                            {{ $alert['message'] }}
                        </div>
                    @endforeach
                </div>
                <div class="actions" style="justify-content:flex-end; margin-top:18px;">
                    <button class="btn" type="button" data-daily-alert-close>Entendi</button>
                </div>
            </div>
        </div>
    @endif

    <div class="quick-filters" style="margin:20px 0 0;">
        <a class="quick-filter {{ $dashboardTab === 'financeiro' ? 'active' : '' }}" href="{{ route('dashboard', array_merge($tabFilters, ['aba' => 'financeiro'])) }}">Financeiro</a>
        <a class="quick-filter {{ $dashboardTab === 'funcionarios' ? 'active' : '' }}" href="{{ route('dashboard', array_merge($tabFilters, ['aba' => 'funcionarios'])) }}">Funcionarios</a>
        <a class="quick-filter {{ $dashboardTab === 'vendas' ? 'active' : '' }}" href="{{ route('dashboard', array_merge($tabFilters, ['aba' => 'vendas'])) }}">Vendas</a>
    </div>

    <form class="filter-bar" method="get" action="{{ route('dashboard') }}">
        <input type="hidden" name="aba" value="{{ $dashboardTab }}">
        <div class="filter-grid" style="grid-template-columns:180px 170px 170px 170px auto;">
            <label>Periodo
                <select name="periodo">
                    <option value="month" @selected($period === 'month')>Mes atual</option>
                    <option value="next7" @selected($period === 'next7')>Proximos 7 dias</option>
                    <option value="7" @selected($period === '7')>Ultimos 7 dias</option>
                    <option value="30" @selected($period === '30')>Ultimos 30 dias</option>
                    <option value="all" @selected($period === 'all')>Todo periodo</option>
                    <option value="custom" @selected($period === 'custom')>Intervalo</option>
                </select>
            </label>
            <label>Inicio
                <input type="date" name="inicio" value="{{ $dateStart }}">
            </label>
            <label>Fim
                <input type="date" name="fim" value="{{ $dateEnd }}">
            </label>
            <label>Status
                <select name="status">
                    <option value="">Todos</option>
                    <option value="open" @selected($statusFilter === 'open')>Aberto</option>
                    <option value="overdue" @selected($statusFilter === 'overdue')>Vencido</option>
                    <option value="paid" @selected($statusFilter === 'paid')>Pago</option>
                    <option value="cancelled" @selected($statusFilter === 'cancelled')>Cancelado</option>
                </select>
            </label>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Filtrar</button>
                @if ($search !== '' || $statusFilter !== '' || $period !== 'month')
                    <a class="btn secondary" href="{{ route('dashboard') }}">Limpar</a>
                @endif
            </div>
        </div>
        <div class="filter-grid" style="grid-template-columns:minmax(260px, 1fr) auto;">
            <label>Buscar
                <input type="search" name="busca" value="{{ $search }}" placeholder="Fornecedor, descricao, documento">
            </label>
            <div class="filter-actions">
                <a class="quick-filter {{ $statusFilter === 'overdue' ? 'active' : '' }}" href="{{ route('dashboard', array_filter(['periodo' => $period, 'busca' => $search, 'status' => 'overdue'])) }}">Vencidos</a>
                <a class="quick-filter {{ $statusFilter === 'open' ? 'active' : '' }}" href="{{ route('dashboard', array_filter(['periodo' => $period, 'busca' => $search, 'status' => 'open'])) }}">Abertos</a>
                <a class="quick-filter {{ $statusFilter === 'paid' ? 'active' : '' }}" href="{{ route('dashboard', array_filter(['periodo' => $period, 'busca' => $search, 'status' => 'paid'])) }}">Pagos</a>
            </div>
        </div>
    </form>

    @if ($dashboardTab === 'financeiro')
        <div class="grid stats">
            <div class="card"><div class="metric-label">Projecao {{ $financeSummary['monthLabel'] }}</div><div class="metric-value">{{ $fmtMoney($financeSummary['projectedRevenue']) }}</div><p class="subtitle" style="margin-top:6px;">Realizado {{ $fmtMoney($financeSummary['grossRevenue']) }}</p></div>
            <div class="card"><div class="metric-label">Despesas do mes</div><div class="metric-value">{{ $fmtMoney($financeSummary['expenses']) }}</div></div>
            <div class="card"><div class="metric-label">Resultado estimado</div><div class="metric-value" style="color:{{ $financeSummary['profitEstimate'] >= 0 ? 'var(--brand)' : 'var(--danger)' }};">{{ $fmtMoney($financeSummary['profitEstimate']) }}</div></div>
            <div class="card"><div class="metric-label">Despesas / faturamento</div><div class="metric-value">{{ $fmtPercent($financeSummary['expensesVsRevenue']) }}</div></div>
        </div>

        <div class="grid" style="grid-template-columns:repeat(4, minmax(0, 1fr)); margin-bottom:18px;">
            <div class="card"><div class="metric-label">Aberto no periodo</div><div class="metric-value">{{ $fmtMoney($openTotal) }}</div></div>
            <div class="card"><div class="metric-label">Vencido no periodo</div><div class="metric-value" style="color:var(--danger);">{{ $fmtMoney($overdueTotal) }}</div></div>
            <div class="card"><div class="metric-label">Previsao restante</div><div class="metric-value">{{ $fmtMoney($financeSummary['projectedRemainingRevenue']) }}</div><p class="subtitle" style="margin-top:6px;">{{ $financeSummary['projectionDaysRecorded'] ?? 0 }} dias informados | {{ $financeSummary['projectionDaysRemaining'] ?? 0 }} faltam</p></div>
            <div class="card"><div class="metric-label">Projecao prox. mes</div><div class="metric-value">{{ $fmtMoney($revenueProjection['projectedNextRevenue']) }}</div><p class="subtitle" style="margin-top:6px;">{{ $revenueProjection['nextMonthLabel'] }} | media {{ $fmtPercent($revenueProjection['averageGrowth']) }}</p></div>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px;">
            <section class="card">
                <h2 class="panel-title">Despesas mensais x faturamento</h2>
                <div class="chart-box"><canvas id="expenseBarChart"></canvas></div>
                <div class="bar-list">
                    @forelse ($monthlyExpenseChart as $row)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ $row['label'] }}</span>
                                <span>Fat. {{ $fmtMoney($row['revenue']) }} | Desp. {{ $fmtMoney($row['value']) }}</span>
                            </div>
                            <div class="bar-track" style="display:flex; height:12px;">
                                <div style="height:100%; width:{{ min(100, ($row['revenue'] / $maxExpense) * 100) }}%; background:#2563eb;"></div>
                                <div style="height:100%; width:{{ min(100, ($row['value'] / $maxExpense) * 100) }}%; background:#b42318;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="subtitle">Nenhuma despesa cadastrada.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h2 class="panel-title">Top 5 categorias</h2>
                <div class="chart-box"><canvas id="categoryBarChart"></canvas></div>
                <div class="bar-list">
                    @forelse ($categoryTotals as $row)
                        @php $value = (float) $row->total; @endphp
                        <div class="bar-row">
                            <div class="bar-meta"><span>{{ $row->name }}</span><span>{{ $fmtMoney($value) }}</span></div>
                            <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($value / $maxCategory) * 100) }}%; --c:var(--brand);"></div></div>
                        </div>
                    @empty
                        <p class="subtitle">Nenhuma categoria no filtro selecionado.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px;">
            <section class="card">
                <h2 class="panel-title">Status das contas</h2>
                <div class="bar-list">
                    @foreach ($statusTotals as $row)
                        @php $value = (float) $row['value']; @endphp
                        <div class="bar-row">
                            <div class="bar-meta"><span>{{ $row['label'] }}</span><span>{{ $fmtMoney($value) }}</span></div>
                            <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($value / $maxStatus) * 100) }}%; --c:{{ $row['color'] }};"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <h2 class="panel-title">Top 5 fornecedores</h2>
                <div class="bar-list">
                    @forelse ($topSuppliers as $row)
                        @php $value = (float) $row->total; @endphp
                        <div class="bar-row">
                            <div class="bar-meta"><span>{{ $row->name }}</span><span>{{ $fmtMoney($value) }}</span></div>
                            <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($value / $maxSupplier) * 100) }}%; --c:#2563eb;"></div></div>
                        </div>
                    @empty
                        <p class="subtitle">Nenhum gasto no filtro selecionado.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif

    @if ($dashboardTab === 'vendas')
        <div class="grid stats">
            <div class="card"><div class="metric-label">Meses cadastrados</div><div class="metric-value">{{ count($monthlyRevenueChart) }}</div></div>
            <div class="card"><div class="metric-label">Maior fat. fechado</div><div class="metric-value">{{ $fmtMoney($revenueProjection['maxClosedRevenue']) }}</div><p class="subtitle" style="margin-top:6px;">{{ $revenueProjection['closedMonthsCount'] }} meses fechados</p></div>
            <div class="card"><div class="metric-label">Crescimento ultimo mes</div><div class="metric-value" style="color:{{ ($lastRevenueRow['growth'] ?? 0) >= 0 ? 'var(--brand)' : 'var(--danger)' }};">{{ $lastRevenueRow && $lastRevenueRow['growth'] !== null ? (($lastRevenueRow['growth'] ?? 0) >= 0 ? '+' : '') . number_format($lastRevenueRow['growth'], 1, ',', '.') . '%' : '-' }}</div></div>
            <div class="card"><div class="metric-label">Projecao mes atual</div><div class="metric-value">{{ $fmtMoney($revenueProjection['currentMonthProjection']) }}</div></div>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr; margin-bottom:18px;">
            <div class="card"><div class="metric-label">Ticket estimado delivery</div><div class="metric-value">{{ $fmtMoney($deliveryTicket) }}</div></div>
            <div class="card"><div class="metric-label">Ticket estimado balcao</div><div class="metric-value">{{ $fmtMoney($counterTicket) }}</div></div>
            <div class="card"><div class="metric-label">Crescimento medio fechado</div><div class="metric-value" style="color:{{ $revenueProjection['averageGrowth'] >= 0 ? 'var(--brand)' : 'var(--danger)' }};">{{ $fmtPercent($revenueProjection['averageGrowth']) }}</div></div>
            <div class="card"><div class="metric-label">Projecao prox. mes</div><div class="metric-value">{{ $fmtMoney($revenueProjection['projectedNextRevenue']) }}</div></div>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px;">
            <section class="card">
                <h2 class="panel-title">Faturamento mensal</h2>
                <div class="chart-box"><canvas id="revenueBarChart"></canvas></div>
                <div class="bar-list">
                    @forelse ($monthlyRevenueChart as $row)
                        @php $growth = $row['growth']; @endphp
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ $row['label'] }}</span>
                                <span>
                                    {{ $fmtMoney($row['projected_total']) }}
                                    @if($row['is_current'] && $row['projected_remaining'] > 0)
                                        <small style="color:var(--muted);">(real {{ $fmtMoney($row['actual']) }} + prev. {{ $fmtMoney($row['projected_remaining']) }})</small>
                                    @elseif($growth !== null)
                                        <small style="color:{{ $growth >= 0 ? 'var(--brand)' : 'var(--danger)' }};">({{ $growth >= 0 ? '+' : '' }}{{ number_format($growth, 1, ',', '.') }}%)</small>
                                    @endif
                                </span>
                            </div>
                            <div class="bar-track" style="display:flex;">
                                <div style="height:100%; width:{{ min(100, ($row['actual'] / $maxRevenue) * 100) }}%; background:#2563eb;"></div>
                                <div style="height:100%; width:{{ min(100, ($row['projected_remaining'] / $maxRevenue) * 100) }}%; background:#93c5fd;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="subtitle">Nenhum faturamento mensal cadastrado.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h2 class="panel-title">Media por dia da semana</h2>
                <div class="chart-box"><canvas id="weekdayBarChart"></canvas></div>
                <div class="bar-list">
                    @foreach ($weekdayAverageChart as $row)
                        <div class="bar-row">
                            <div class="bar-meta"><span>{{ $row['label'] }}</span><span>{{ $fmtMoney($row['value']) }} <small style="color:var(--muted);">({{ $row['count'] }} dias)</small></span></div>
                            <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($row['value'] / $maxWeekday) * 100) }}%; --c:var(--brand);"></div></div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="card">
            <h2 class="panel-title">Faturamento por canal</h2>
            <div class="chart-box"><canvas id="channelBarChart"></canvas></div>
            <div class="bar-list">
                @forelse ($channelRevenueChart as $row)
                    @php $totalChannel = $row['delivery'] + $row['counter']; @endphp
                    <div class="bar-row">
                        <div class="bar-meta"><span>{{ $row['label'] }}</span><span>Delivery {{ $fmtMoney($row['delivery']) }} | Balcao {{ $fmtMoney($row['counter']) }}</span></div>
                        <div class="bar-track" style="height:12px; display:flex;">
                            <div style="height:100%; width:{{ $totalChannel > 0 ? ($row['delivery'] / $maxChannel) * 100 : 0 }}%; background:#2563eb;"></div>
                            <div style="height:100%; width:{{ $totalChannel > 0 ? ($row['counter'] / $maxChannel) * 100 : 0 }}%; background:var(--brand);"></div>
                        </div>
                    </div>
                @empty
                    <p class="subtitle">Nenhum canal cadastrado.</p>
                @endforelse
            </div>
        </section>
    @endif

    @if ($dashboardTab === 'funcionarios')
        <div class="grid stats">
            <div class="card"><div class="metric-label">Funcionarios ativos</div><div class="metric-value">{{ $employeeDashboard['activeCount'] }}</div></div>
            <div class="card"><div class="metric-label">Folha fixa ativa</div><div class="metric-value">{{ $fmtMoney($employeeDashboard['fixedTotal']) }}</div></div>
            <div class="card"><div class="metric-label">Variavel previsto</div><div class="metric-value">{{ $fmtMoney($employeeDashboard['variableTotal']) }}</div></div>
            <div class="card"><div class="metric-label">Aberto no periodo</div><div class="metric-value">{{ $fmtMoney($employeeDashboard['openTotal']) }}</div></div>
        </div>

        <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px;">
            <section class="card">
                <h2 class="panel-title">Tipos de movimento</h2>
                <div class="bar-list">
                    @forelse ($employeeDashboard['movementTypes'] as $row)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ $row['label'] }}</span>
                                <span>{{ $row['kind'] === 'debit' ? 'Desconto' : 'Acrescimo' }} {{ $fmtMoney($row['total']) }}</span>
                            </div>
                            <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($row['total'] / $maxMovementType) * 100) }}%; --c:{{ $row['kind'] === 'debit' ? 'var(--danger)' : 'var(--brand)' }};"></div></div>
                        </div>
                    @empty
                        <p class="subtitle">Nenhum movimento de funcionario no periodo.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h2 class="panel-title">Top funcionarios</h2>
                <div class="bar-list">
                    @forelse ($employeeDashboard['topEmployees'] as $row)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <a href="{{ route('dashboard', array_merge($tabFilters, ['aba' => 'funcionarios', 'funcionario' => $row['id']])) }}">{{ $row['name'] }}</a>
                                <span>{{ $fmtMoney($row['total']) }}</span>
                            </div>
                            <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($row['total'] / $maxEmployeeTop) * 100) }}%; --c:#2563eb;"></div></div>
                        </div>
                    @empty
                        <p class="subtitle">Nenhum funcionario com despesa no periodo.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="card">
            <h2 class="panel-title">Movimentos por funcionario</h2>
            <p class="subtitle" style="margin-bottom:14px;">{{ $employeeDashboard['selectedEmployeeName'] ? 'Funcionario selecionado: '.$employeeDashboard['selectedEmployeeName'] : 'Selecione um funcionario no ranking.' }}</p>
            <div class="chart-box"><canvas id="employeeMovementChart"></canvas></div>
            <div class="bar-list">
                @forelse ($employeeDashboard['employeeMovementDetails'] as $row)
                    <div class="bar-row">
                        <div class="bar-meta">
                            <span>{{ $row['label'] }}</span>
                            <span>{{ $row['kind'] === 'debit' ? 'Debito' : 'Credito' }} {{ $fmtMoney($row['total']) }}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($row['total'] / $maxEmployeeMovement) * 100) }}%; --c:{{ $row['kind'] === 'debit' ? 'var(--danger)' : 'var(--brand)' }};"></div></div>
                    </div>
                @empty
                    <p class="subtitle">Nenhum movimento avulso para este funcionario no periodo.</p>
                @endforelse
            </div>
        </section>
    @endif

    @if (in_array($dashboardTab, ['financeiro', 'funcionarios', 'vendas'], true))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (() => {
                if (!window.Chart) return;
                const money = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                const commonOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { boxWidth: 10, font: { family: 'Arial' } } },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${money(ctx.parsed.y ?? ctx.parsed.x)}` } }
                    },
                    scales: { y: { beginAtZero: true, ticks: { callback: money } } }
                };
                const revenue = @json($monthlyRevenueChart);
                const weekdays = @json($weekdayAverageChart);
                const channels = @json($channelRevenueChart);
                const expenses = @json($monthlyExpenseChart);
                const categories = @json($categoryTotals->values());
                const employeeMovements = @json($employeeDashboard['employeeMovementDetails']);

                const revenueCanvas = document.getElementById('revenueBarChart');
                if (revenueCanvas) new Chart(revenueCanvas, {
                    type: 'bar',
                    data: {
                        labels: revenue.map((row) => row.label),
                        datasets: [
                            { type: 'bar', label: 'Realizado', data: revenue.map((row) => row.actual), backgroundColor: '#2563eb', borderRadius: 4, stack: 'revenue' },
                            { type: 'bar', label: 'Previsao restante', data: revenue.map((row) => row.projected_remaining), backgroundColor: '#93c5fd', borderRadius: 4, stack: 'revenue' },
                            { type: 'line', label: 'Total projetado', data: revenue.map((row) => row.projected_total), borderColor: '#0f766e', backgroundColor: '#0f766e', tension: .3 }
                        ]
                    },
                    options: { ...commonOptions, scales: { x: { stacked: true }, y: { ...commonOptions.scales.y, stacked: true } } }
                });

                const weekdayCanvas = document.getElementById('weekdayBarChart');
                if (weekdayCanvas) new Chart(weekdayCanvas, {
                    type: 'bar',
                    data: { labels: weekdays.map((row) => row.label), datasets: [{ label: 'Media diaria', data: weekdays.map((row) => row.value), backgroundColor: '#0f766e', borderRadius: 4 }] },
                    options: commonOptions
                });

                const channelCanvas = document.getElementById('channelBarChart');
                if (channelCanvas) new Chart(channelCanvas, {
                    type: 'bar',
                    data: {
                        labels: channels.map((row) => row.label),
                        datasets: [
                            { label: 'Delivery', data: channels.map((row) => row.delivery), backgroundColor: '#2563eb', borderRadius: 4 },
                            { label: 'Balcao', data: channels.map((row) => row.counter), backgroundColor: '#0f766e', borderRadius: 4 }
                        ]
                    },
                    options: { ...commonOptions, scales: { x: { stacked: false }, y: commonOptions.scales.y } }
                });

                const expenseCanvas = document.getElementById('expenseBarChart');
                if (expenseCanvas) new Chart(expenseCanvas, {
                    type: 'bar',
                    data: {
                        labels: expenses.map((row) => row.label),
                        datasets: [
                            { label: 'Faturamento', data: expenses.map((row) => row.revenue), backgroundColor: '#2563eb', borderRadius: 4 },
                            { label: 'Despesas', data: expenses.map((row) => row.value), backgroundColor: '#b42318', borderRadius: 4 }
                        ]
                    },
                    options: commonOptions
                });

                const categoryCanvas = document.getElementById('categoryBarChart');
                if (categoryCanvas) new Chart(categoryCanvas, {
                    type: 'bar',
                    data: { labels: categories.map((row) => row.name), datasets: [{ label: 'Despesa', data: categories.map((row) => row.total), backgroundColor: '#0f766e', borderRadius: 4 }] },
                    options: commonOptions
                });

                const employeeMovementCanvas = document.getElementById('employeeMovementChart');
                if (employeeMovementCanvas) new Chart(employeeMovementCanvas, {
                    type: 'bar',
                    data: {
                        labels: employeeMovements.map((row) => row.label),
                        datasets: [
                            { label: 'Credito', data: employeeMovements.map((row) => row.kind === 'credit' ? row.total : 0), backgroundColor: '#0f766e', borderRadius: 4 },
                            { label: 'Debito', data: employeeMovements.map((row) => row.kind === 'debit' ? row.total : 0), backgroundColor: '#b42318', borderRadius: 4 }
                        ]
                    },
                    options: { ...commonOptions, indexAxis: 'y' }
                });
            })();
        </script>
    @endif
@endsection



