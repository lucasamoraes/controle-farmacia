@extends('layouts.app', ['pageTitle' => 'Contas a pagar'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Contas a pagar</h1>
            <p class="subtitle">Controle boletos, despesas e pagamentos com revisao rapida.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('boletos.create') }}">Ler boleto PDF</a>
            <a class="btn" href="{{ route('contas-a-pagar.create') }}">Nova conta</a>
        </div>
    </div>

    <div style="margin-top:22px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Vencimento</th><th>Descricao</th><th>Fornecedor</th><th>Categoria</th><th>Valor</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($payables as $payable)
                @php $isOverdue = $payable->status === 'open' && $payable->due_date->isPast() && ! $payable->due_date->isToday(); @endphp
                <tr>
                    <td>{{ $payable->due_date->format('d/m/Y') }}</td>
                    <td><strong>{{ $payable->description }}</strong><br><span style="color:var(--muted);">{{ $payable->document_number }}</span></td>
                    <td>{{ $payable->supplier->name ?? '-' }}</td>
                    <td>{{ $payable->category->name ?? '-' }}</td>
                    <td>R$ {{ number_format($payable->amount, 2, ',', '.') }}</td>
                    <td><span class="status {{ $isOverdue ? 'overdue' : $payable->status }}">{{ $isOverdue ? 'Vencido' : ['open' => 'Aberto', 'paid' => 'Pago', 'cancelled' => 'Cancelado'][$payable->status] ?? ucfirst($payable->status) }}</span></td>
                    <td class="actions">
                        @if ($payable->status === 'open')
                            <button class="btn small" type="button" data-pay-modal data-pay-url="{{ route('payables.mark-paid', $payable) }}" data-line="{{ $payable->digitable_line }}" data-description="{{ $payable->description }}" data-amount="R$ {{ number_format($payable->amount, 2, ',', '.') }}">Pagar</button>
                        @endif
                        <a class="btn small secondary" href="{{ route('contas-a-pagar.edit', $payable) }}">Editar</a>
                        @if ($payable->status !== 'cancelled')
                            <form method="post" action="{{ route('contas-a-pagar.destroy', $payable) }}">
                                @csrf
                                @method('delete')
                                <button class="btn small danger" type="submit">Cancelar</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('payables.delete', $payable) }}" onsubmit="return confirm('Excluir definitivamente esta conta?');">
                            @csrf
                            @method('delete')
                            <button class="btn small secondary" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Nenhuma conta cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="pagination">{{ $payables->links() }}</div>
    </div>

    <div class="modal-backdrop" data-modal-backdrop hidden></div>
    <div class="modal" data-pay-dialog hidden role="dialog" aria-modal="true" aria-labelledby="pay-modal-title">
        <div class="modal-panel">
            <div class="actions" style="justify-content:space-between; align-items:flex-start;">
                <div>
                    <h2 id="pay-modal-title" class="panel-title">Pagar boleto</h2>
                    <p class="subtitle" data-pay-summary></p>
                </div>
                <button class="btn small secondary" type="button" data-close-pay-modal>Fechar</button>
            </div>
            <label style="margin-top:16px;">Linha digitavel
                <textarea data-pay-line readonly style="font-family:Consolas, monospace; min-height:86px;"></textarea>
            </label>
            <div class="actions" style="margin-top:14px;">
                <button class="btn secondary" type="button" data-copy-pay-line>Copiar linha</button>
                <form method="post" data-pay-form>
                    @csrf
                    @method('patch')
                    <button class="btn" type="submit">Marcar como pago</button>
                </form>
            </div>
            <p class="subtitle" data-copy-feedback style="margin-top:10px;"></p>
        </div>
    </div>
@endsection
