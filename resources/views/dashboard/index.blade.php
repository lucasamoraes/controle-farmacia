@extends('layouts.app', ['pageTitle' => 'Dashboard'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Dashboard financeiro</h1>
            <p class="subtitle">Resumo das contas e proximos vencimentos.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="{{ route('fornecedores.create') }}">Novo fornecedor</a>
            <a class="btn secondary" href="{{ route('boletos.create') }}">Ler boleto PDF</a>
            <a class="btn" href="{{ route('contas-a-pagar.create') }}">Nova conta</a>
        </div>
    </div>

    <div class="grid stats">
        <div class="card"><div class="metric-label">Aberto</div><div class="metric-value">R$ {{ number_format($openTotal, 2, ',', '.') }}</div></div>
        <div class="card"><div class="metric-label">Vencido</div><div class="metric-value" style="color:var(--danger);">R$ {{ number_format($overdueTotal, 2, ',', '.') }}</div></div>
        <div class="card"><div class="metric-label">Vence no mes</div><div class="metric-value">R$ {{ number_format($monthTotal, 2, ',', '.') }}</div></div>
        <div class="card"><div class="metric-label">Pago no mes</div><div class="metric-value">R$ {{ number_format($paidMonthTotal, 2, ',', '.') }}</div></div>
    </div>

    <div class="card">
        <h2 class="panel-title">Proximos vencimentos</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Vencimento</th><th>Descricao</th><th>Fornecedor</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($upcomingPayables as $payable)
                @php $isOverdue = $payable->due_date->isPast() && ! $payable->due_date->isToday(); @endphp
                <tr>
                    <td>{{ $payable->due_date->format('d/m/Y') }}</td>
                    <td>{{ $payable->description }}</td>
                    <td>{{ $payable->supplier->name ?? '-' }}</td>
                    <td>R$ {{ number_format($payable->amount, 2, ',', '.') }}</td>
                    <td><span class="status {{ $isOverdue ? 'overdue' : 'open' }}">{{ $isOverdue ? 'Vencido' : 'Aberto' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhuma conta em aberto.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
@endsection



