@extends('layouts.app', ['pageTitle' => 'Faturamento mensal'])

@section('content')
    @php $canWriteFinance = auth()->user()->canWriteFinance($company); @endphp
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Faturamento mensal</h1>
            <p class="subtitle">Registre faturamento, vendas, CMV, ticket medio e informacoes importantes.</p>
        </div>
        @if ($canWriteFinance)
            <a class="btn" href="{{ route('faturamento-mensal.create') }}">Novo mes</a>
        @endif
    </div>

    <form class="filter-bar" method="get" action="{{ route('faturamento-mensal.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(220px, 1fr) auto;">
            <label>Buscar faturamento
                <input type="search" name="busca" value="{{ $search }}" placeholder="Mes 07/2026, 2026-07, observacoes">
            </label>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Buscar</button>
                @if ($search !== '')
                    <a class="btn secondary" href="{{ route('faturamento-mensal.index') }}">Limpar</a>
                @endif
            </div>
        </div>
    </form>

    <div style="margin-top:22px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Mes</th><th>Faturamento</th><th>Delivery</th><th>Balcao</th><th>Vendas</th><th>Ticket medio</th><th>CMV</th><th></th></tr></thead>
            <tbody>
            @forelse ($revenues as $revenue)
                <tr>
                    <td><strong>{{ $revenue->reference_month->format('m/Y') }}</strong></td>
                    <td>R$ {{ number_format($revenue->gross_revenue, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($revenue->delivery_revenue ?? 0, 2, ',', '.') }}<br><span style="color:var(--muted);">{{ number_format($revenue->delivery_sales_count ?? 0, 0, ',', '.') }} vendas</span></td>
                    <td>R$ {{ number_format($revenue->counter_revenue ?? 0, 2, ',', '.') }}<br><span style="color:var(--muted);">{{ number_format($revenue->counter_sales_count ?? 0, 0, ',', '.') }} vendas</span></td>
                    <td>{{ number_format($revenue->sales_count, 0, ',', '.') }}</td>
                    <td>R$ {{ number_format($revenue->average_ticket, 2, ',', '.') }}</td>
                    <td>{{ number_format($revenue->cmv_percentage, 2, ',', '.') }}%</td>
                    <td class="actions">
                        <a class="btn small secondary" href="{{ route('resumo.index', ['mes' => $revenue->reference_month->format('Y-m')]) }}">Resumo</a>
                        @if ($canWriteFinance)
                        <a class="btn small secondary" href="{{ route('faturamento-mensal.edit', $revenue) }}">Editar</a>
                        <form method="post" action="{{ route('faturamento-mensal.destroy', $revenue) }}" data-confirm-title="Remover faturamento" data-confirm-message="Deseja remover o faturamento deste mês? Os indicadores do resumo serão recalculados." data-confirm-button="Remover" data-confirm-danger="1">
                            @csrf
                            @method('delete')
                            <button class="btn small danger" type="submit">Remover</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Nenhum mes cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table></div><div class="pagination">{{ $revenues->links() }}</div>
    </div>
@endsection

