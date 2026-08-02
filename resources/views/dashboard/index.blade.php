@extends('layouts.app', ['pageTitle' => 'Dashboard'])

@php
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $maxStatus = max(collect($statusTotals)->max('value') ?? 0, 1);
    $maxSupplier = max((float) $topSuppliers->max('total'), 1);
    $maxCategory = max((float) $categoryTotals->max('total'), 1);
    $maxRevenue = max(collect($monthlyRevenueChart)->max('value') ?? 0, 1);
    $maxWeekday = max(collect($weekdayAverageChart)->max('value') ?? 0, 1);
    $maxChannel = max(collect($channelRevenueChart)->map(fn ($row) => ($row['delivery'] ?? 0) + ($row['counter'] ?? 0))->max() ?? 0, 1);
    $maxExpense = max(collect($monthlyExpenseChart)->max('value') ?? 0, 1);
    $canWriteFinance = auth()->user()->canWriteFinance($company);
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

    <form class="filter-bar" method="get" action="{{ route('dashboard') }}">
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

    <div class="grid stats">
        <div class="card"><div class="metric-label">Total filtrado</div><div class="metric-value">{{ $fmtMoney($totalFiltered) }}</div></div>
        <div class="card"><div class="metric-label">Aberto no mes</div><div class="metric-value">{{ $fmtMoney($openTotal) }}</div></div>
        <div class="card"><div class="metric-label">Vencido no mes</div><div class="metric-value" style="color:var(--danger);">{{ $fmtMoney($overdueTotal) }}</div></div>
        <div class="card"><div class="metric-label">Pago no mes</div><div class="metric-value">{{ $fmtMoney($paidMonthTotal) }}</div></div>
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

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px;">
        <section class="card">
            <h2 class="panel-title">Faturamento mensal</h2>
            <div class="bar-list">
                @forelse ($monthlyRevenueChart as $row)
                    @php $growth = $row['growth']; @endphp
                    <div class="bar-row">
                        <div class="bar-meta">
                            <span>{{ $row['label'] }}</span>
                            <span>{{ $fmtMoney($row['value']) }} @if($growth !== null)<small style="color:{{ $growth >= 0 ? 'var(--brand)' : 'var(--danger)' }};">({{ $growth >= 0 ? '+' : '' }}{{ number_format($growth, 1, ',', '.') }}%)</small>@endif</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($row['value'] / $maxRevenue) * 100) }}%; --c:#2563eb;"></div></div>
                    </div>
                @empty
                    <p class="subtitle">Nenhum faturamento mensal cadastrado.</p>
                @endforelse
            </div>
        </section>

        <section class="card">
            <h2 class="panel-title">Media por dia da semana</h2>
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

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px;">
        <section class="card">
            <h2 class="panel-title">Faturamento por canal</h2>
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

        <section class="card">
            <h2 class="panel-title">Despesas mensais</h2>
            <div class="bar-list">
                @forelse ($monthlyExpenseChart as $row)
                    <div class="bar-row">
                        <div class="bar-meta"><span>{{ $row['label'] }}</span><span>{{ $fmtMoney($row['value']) }}</span></div>
                        <div class="bar-track"><div class="bar-fill" style="--w:{{ min(100, ($row['value'] / $maxExpense) * 100) }}%; --c:var(--danger);"></div></div>
                    </div>
                @empty
                    <p class="subtitle">Nenhuma despesa cadastrada.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="grid" style="grid-template-columns:1fr; align-items:start;">
        <section class="card">
            <h2 class="panel-title">Top categorias</h2>
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
@endsection



