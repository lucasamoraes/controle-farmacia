@extends('layouts.app', ['pageTitle' => 'Contas a pagar'])

@section('content')
    @php $canWriteFinance = auth()->user()->canWriteFinance($company); @endphp
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Contas a pagar</h1>
            <p class="subtitle">Controle boletos, despesas e pagamentos com revisao rapida.</p>
        </div>
        @if ($canWriteFinance)
        <div class="actions">
            <a class="btn secondary" href="{{ route('boletos.create') }}">Ler boleto PDF</a>
            <a class="btn" href="{{ route('contas-a-pagar.create') }}">Nova conta</a>
        </div>
        @endif
    </div>

    @php
        $baseFilters = array_filter([
            'busca' => $search,
            'status' => $statusFilter,
            'categoria' => $categoryFilter,
        ], fn ($value) => $value !== null && $value !== '');
        $periodLink = fn ($value) => route('contas-a-pagar.index', array_merge($baseFilters, ['periodo' => $value]));
    @endphp

    <form class="filter-bar" method="get" action="{{ route('contas-a-pagar.index') }}">
        <div class="filter-grid">
            <label>Buscar conta
                <input type="search" name="busca" value="{{ $search }}" placeholder="Fornecedor, descricao, categoria, documento">
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
            <label>Categoria
                <select name="categoria">
                    <option value="">Todas</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $categoryFilter === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Periodo
                <select name="periodo">
                    <option value="" @selected($period === '')>Todo periodo</option>
                    <option value="next7" @selected($period === 'next7')>Proximos 7 dias</option>
                    <option value="7" @selected($period === '7')>Ultimos 7 dias</option>
                    <option value="30" @selected($period === '30')>Ultimos 30 dias</option>
                    <option value="month" @selected($period === 'month')>Mes atual</option>
                    <option value="custom" @selected($period === 'custom')>Intervalo</option>
                </select>
            </label>
            <label>Inicio
                <input type="date" name="inicio" value="{{ $dateStart }}">
            </label>
            <label>Fim
                <input type="date" name="fim" value="{{ $dateEnd }}">
            </label>
        </div>
        <div class="filter-actions" style="justify-content:space-between;">
            <div class="quick-filters">
                <a class="quick-filter {{ $period === 'next7' ? 'active' : '' }}" href="{{ $periodLink('next7') }}">Proximos 7 dias</a>
                <a class="quick-filter {{ $period === '7' ? 'active' : '' }}" href="{{ $periodLink('7') }}">Ultimos 7 dias</a>
                <a class="quick-filter {{ $period === '30' ? 'active' : '' }}" href="{{ $periodLink('30') }}">Ultimos 30 dias</a>
                <a class="quick-filter {{ $statusFilter === 'overdue' ? 'active' : '' }}" href="{{ route('contas-a-pagar.index', array_merge($baseFilters, ['status' => 'overdue'])) }}">Vencidos</a>
            </div>
            <div class="filter-actions">
                <button class="btn secondary" type="submit">Filtrar</button>
                @if ($search !== '' || $statusFilter || $period !== 'next7' || $categoryFilter || $dateStart || $dateEnd)
                    <a class="btn secondary" href="{{ route('contas-a-pagar.index', ['periodo' => '']) }}">Limpar</a>
                @endif
            </div>
        </div>
    </form>

    <div class="grid stats" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <div class="card">
            <div class="metric-label">Total do filtro</div>
            <div class="metric-value">R$ {{ number_format($filteredTotal, 2, ',', '.') }}</div>
        </div>
        <div class="card">
            <div class="metric-label">{{ $period === 'next7' ? 'Primeira visualizacao' : 'Periodo' }}</div>
            <div class="metric-value" style="font-size:18px;">{{ $dateStart ? \Illuminate\Support\Carbon::parse($dateStart)->format('d/m/Y') : 'Inicio' }} - {{ $dateEnd ? \Illuminate\Support\Carbon::parse($dateEnd)->format('d/m/Y') : 'Fim' }}</div>
        </div>
        <div class="card">
            <div class="metric-label">Status</div>
            <div class="metric-value" style="font-size:18px;">{{ ['open' => 'Aberto', 'paid' => 'Pago', 'overdue' => 'Vencido', 'cancelled' => 'Cancelado', '' => 'Todos'][$statusFilter ?? ''] ?? 'Todos' }}</div>
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
                        @if ($canWriteFinance)
                        @if ($payable->status === 'open')
                            <button class="btn small" type="button" data-pay-modal data-pay-url="{{ route('payables.mark-paid', $payable) }}" data-line="{{ $payable->digitable_line }}" data-description="{{ $payable->description }}" data-amount="R$ {{ number_format($payable->amount, 2, ',', '.') }}">Pagar</button>
                        @endif
                        <a class="btn small secondary" href="{{ route('contas-a-pagar.edit', $payable) }}">Editar</a>
                        @if ($payable->status !== 'cancelled')
                            <form method="post" action="{{ route('contas-a-pagar.destroy', $payable) }}" data-confirm-title="Cancelar conta" data-confirm-message="Deseja cancelar esta conta a pagar? Ela sairá dos totais ativos, mas continuará no histórico." data-confirm-button="Cancelar conta" data-confirm-danger="1">
                                @csrf
                                @method('delete')
                                <button class="btn small danger" type="submit">Cancelar</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('payables.delete', $payable) }}" data-confirm-title="Excluir conta" data-confirm-message="Deseja excluir definitivamente esta conta? Esta ação não pode ser desfeita." data-confirm-button="Excluir conta" data-confirm-danger="1">
                            @csrf
                            @method('delete')
                            <button class="btn small secondary" type="submit">Excluir</button>
                        </form>
                        @endif
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
