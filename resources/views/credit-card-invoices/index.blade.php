@extends('layouts.app', ['pageTitle' => 'Faturas cartao'])

@section('content')
    @php $canWriteFinance = auth()->user()->canWriteFinance($company); @endphp
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Faturas cartao</h1>
            <p class="subtitle">Cadastre a fatura total e detalhe os gastos por categoria para analise.</p>
        </div>
        @if ($canWriteFinance)
            <a class="btn" href="{{ route('faturas-cartao.create') }}">Nova fatura</a>
        @endif
    </div>

    <form class="filter-bar" method="get" action="{{ route('faturas-cartao.index') }}">
        <div class="filter-grid" style="grid-template-columns:minmax(240px, 1fr) auto;">
            <label>Buscar fatura
                <input type="search" name="busca" value="{{ $search }}" placeholder="Cartao, observacao">
            </label>
            <button class="btn secondary" type="submit">Buscar</button>
        </div>
    </form>

    <div style="margin-top:22px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Mes</th><th>Cartao</th><th>Vencimento</th><th>Total</th><th>Status</th><th>Categorias</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->reference_month->format('m/Y') }}</td>
                    <td><strong>{{ $invoice->card_name }}</strong></td>
                    <td>{{ $invoice->due_date->format('d/m/Y') }}</td>
                    <td>R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                    <td><span class="status {{ $invoice->status }}">{{ ['open' => 'Aberta', 'paid' => 'Paga', 'cancelled' => 'Cancelada'][$invoice->status] ?? $invoice->status }}</span></td>
                    <td>
                        @foreach ($invoice->items->groupBy(fn ($item) => $item->category->name ?? 'Sem categoria') as $name => $rows)
                            <div>{{ $name }}: R$ {{ number_format($rows->sum('amount'), 2, ',', '.') }}</div>
                        @endforeach
                    </td>
                    <td class="actions">
                        @if ($canWriteFinance)
                            @if ($invoice->status === 'open')
                                <form method="post" action="{{ route('faturas-cartao.mark-paid', $invoice) }}">
                                    @csrf
                                    @method('patch')
                                    <button class="btn small" type="submit">Pagar</button>
                                </form>
                            @endif
                            <a class="btn small secondary" href="{{ route('faturas-cartao.edit', $invoice) }}">Editar</a>
                            <form method="post" action="{{ route('faturas-cartao.destroy', $invoice) }}" data-confirm-title="Excluir fatura" data-confirm-message="Deseja excluir esta fatura e a conta a pagar relacionada?" data-confirm-button="Excluir" data-confirm-danger="1">
                                @csrf
                                @method('delete')
                                <button class="btn small danger" type="submit">Excluir</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Nenhuma fatura cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="pagination">{{ $invoices->links() }}</div>
    </div>
@endsection
