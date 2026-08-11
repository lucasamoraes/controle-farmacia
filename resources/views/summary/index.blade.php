@extends('layouts.app', ['pageTitle' => 'Resumo'])

@php
    $monthInput = $selectedMonth->format('Y-m');
    $maxCategory = max((float) $categoryTotals->max('total'), 1);
    $maxSupplier = max((float) $supplierTotals->max('total'), 1);
    $fmtMoney = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
    $fmtPercent = fn ($value) => $value === null ? '-' : number_format((float) $value, 1, ',', '.') . '%';
    $canWriteFinance = auth()->user()->canWriteFinance($company);
@endphp

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Resumo financeiro</h1>
            <p class="subtitle">Visao mensal inspirada na aba Resumo da planilha.</p>
        </div>
        <form class="actions" method="get" action="{{ route('resumo.index') }}">
            <input type="month" name="mes" value="{{ $monthInput }}" style="width:160px;">
            <input type="date" name="inicio" value="{{ $dateStart->toDateString() }}" style="width:150px;">
            <input type="date" name="fim" value="{{ $dateEnd->toDateString() }}" style="width:150px;">
            <button class="btn secondary" type="submit">Filtrar</button>
            @if ($canWriteFinance)
                <a class="btn" href="{{ route('faturamento-mensal.create', ['mes' => $monthInput]) }}">Registrar faturamento</a>
            @endif
        </form>
    </div>

    <div class="grid stats">
        <div class="card"><div class="metric-label">Faturamento</div><div class="metric-value">{{ $fmtMoney($grossRevenue) }}</div></div>
        <div class="card"><div class="metric-label">Despesas do mes</div><div class="metric-value">{{ $fmtMoney($totalExpenses) }}</div></div>
        <div class="card"><div class="metric-label">Compras mercadoria</div><div class="metric-value">{{ $fmtMoney($stockPurchases) }}</div></div>
        <div class="card"><div class="metric-label">Resultado estimado</div><div class="metric-value" style="color:{{ $profitEstimate >= 0 ? 'var(--brand)' : 'var(--danger)' }};">{{ $fmtMoney($profitEstimate) }}</div></div>
    </div>

    <div class="grid" style="grid-template-columns:repeat(4, minmax(0, 1fr)); margin-bottom:18px;">
        <div class="card"><div class="metric-label">Despesas / faturamento atual</div><div class="metric-value">{{ $fmtPercent($expensesVsCurrentRevenue) }}</div></div>
        <div class="card"><div class="metric-label">Despesas / fat. anterior</div><div class="metric-value">{{ $fmtPercent($expensesVsPreviousRevenue) }}</div><p class="subtitle" style="margin-top:6px;">Base: {{ $previousMonth->format('m/Y') }}</p></div>
        <div class="card"><div class="metric-label">Vendas</div><div class="metric-value">{{ number_format((int) ($monthlyRevenue->sales_count ?? 0), 0, ',', '.') }}</div></div>
        <div class="card"><div class="metric-label">Ticket medio</div><div class="metric-value">{{ $fmtMoney($monthlyRevenue->average_ticket ?? 0) }}</div></div>
    </div>

    <section class="card" style="margin-bottom:18px;">
        <h2 class="panel-title">Balcao x Delivery</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Canal</th><th>Faturamento</th><th>% do valor</th><th>Vendas</th><th>% das vendas</th><th>Ticket medio</th></tr></thead>
            <tbody>
                @foreach ($channelSummary as $channel)
                    <tr>
                        <td><strong>{{ $channel['label'] }}</strong></td>
                        <td>{{ $fmtMoney($channel['revenue']) }}</td>
                        <td>{{ $fmtPercent($channel['revenue_percent']) }}</td>
                        <td>{{ number_format($channel['sales_count'], 0, ',', '.') }}</td>
                        <td>{{ $fmtPercent($channel['sales_percent']) }}</td>
                        <td>{{ $fmtMoney($channel['average_ticket']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start;">
        <section class="card">
            <h2 class="panel-title">Despesas por categoria</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Categoria</th><th>Valor</th><th>% do mes</th></tr></thead>
                <tbody>
                @forelse ($categoryTotals as $row)
                    @php $total = (float) $row->total; @endphp
                    <tr>
                        <td>
                            <strong>{{ $row->name }}</strong>
                            <div style="height:7px; background:#e8eef5; border-radius:999px; margin-top:7px; overflow:hidden;"><div style="height:7px; width:{{ min(100, ($total / $maxCategory) * 100) }}%; background:var(--brand);"></div></div>
                        </td>
                        <td>{{ $fmtMoney($total) }}</td>
                        <td>{{ $totalExpenses > 0 ? number_format(($total / $totalExpenses) * 100, 1, ',', '.') . '%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Nenhuma despesa no mes selecionado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <section class="card">
            <h2 class="panel-title">Compras de mercadoria por fornecedor</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>Fornecedor</th><th>Valor</th><th>% compras</th></tr></thead>
                <tbody>
                @forelse ($supplierTotals as $row)
                    @php $total = (float) $row->total; @endphp
                    <tr>
                        <td>
                            <strong>{{ $row->name }}</strong>
                            <div style="height:7px; background:#e8eef5; border-radius:999px; margin-top:7px; overflow:hidden;"><div style="height:7px; width:{{ min(100, ($total / $maxSupplier) * 100) }}%; background:#2563eb;"></div></div>
                        </td>
                        <td>{{ $fmtMoney($total) }}</td>
                        <td>{{ $stockPurchases > 0 ? number_format(($total / $stockPurchases) * 100, 1, ',', '.') . '%' : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Nenhuma compra de mercadoria no mes selecionado.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>

    <section class="card" style="margin-top:18px;">
        <h2 class="panel-title">Evolucao dos ultimos meses</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Mes</th><th>Faturamento</th><th>Delivery</th><th>Balcao</th><th>Despesas</th><th>Despesas/Faturamento</th><th>Vendas</th><th>CMV</th></tr></thead>
            <tbody>
            @foreach ($monthlyEvolution as $row)
                <tr>
                    <td><a href="{{ route('resumo.index', ['mes' => $row['month']]) }}"><strong>{{ $row['label'] }}</strong></a></td>
                    <td>{{ $fmtMoney($row['gross_revenue']) }}</td>
                    <td>{{ $fmtMoney($row['delivery_revenue']) }}<br><span style="color:var(--muted);">{{ number_format($row['delivery_sales_count'], 0, ',', '.') }} vendas</span></td>
                    <td>{{ $fmtMoney($row['counter_revenue']) }}<br><span style="color:var(--muted);">{{ number_format($row['counter_sales_count'], 0, ',', '.') }} vendas</span></td>
                    <td>{{ $fmtMoney($row['expenses']) }}</td>
                    <td>{{ $fmtPercent($row['gross_revenue'] > 0 ? ($row['expenses'] / $row['gross_revenue']) * 100 : null) }}</td>
                    <td>{{ number_format($row['sales_count'], 0, ',', '.') }}</td>
                    <td>{{ $fmtPercent($row['cmv_percentage']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="card" style="margin-top:18px;">
        <h2 class="panel-title">Dados da farmacia</h2>
        <div class="grid" style="grid-template-columns:repeat(4, minmax(0,1fr));">
            <div><div class="metric-label">CMV</div><div class="metric-value">{{ $fmtPercent($monthlyRevenue->cmv_percentage ?? null) }}</div></div>
            <div><div class="metric-label">CMV valor</div><div class="metric-value">{{ $fmtMoney($monthlyRevenue->cost_of_goods_sold ?? 0) }}</div></div>
            <div><div class="metric-label">Itens por ticket</div><div class="metric-value">{{ number_format((float) ($monthlyRevenue->items_per_ticket ?? 0), 2, ',', '.') }}</div></div>
            <div><div class="metric-label">A receber</div><div class="metric-value">{{ $fmtMoney($monthlyRevenue->revenue_to_receive ?? 0) }}</div></div>
        </div>
        @if ($monthlyRevenue?->important_info || $monthlyRevenue?->notes)
            <div style="margin-top:16px; color:var(--muted); white-space:pre-wrap;">{{ $monthlyRevenue->important_info ?: $monthlyRevenue->notes }}</div>
        @endif
    </section>
@endsection

