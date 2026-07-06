@extends('layouts.app', ['pageTitle' => 'Faturamento mensal'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Faturamento mensal</h1>
            <p class="subtitle">Registre faturamento, vendas, CMV, ticket medio e informacoes importantes.</p>
        </div>
        <a class="btn" href="{{ route('faturamento-mensal.create') }}">Novo mes</a>
    </div>

    <div style="margin-top:22px;">
        <div class="table-wrap"><table>
            <thead><tr><th>Mes</th><th>Faturamento</th><th>Vendas</th><th>Ticket medio</th><th>CMV</th><th></th></tr></thead>
            <tbody>
            @forelse ($revenues as $revenue)
                <tr>
                    <td><strong>{{ $revenue->reference_month->format('m/Y') }}</strong></td>
                    <td>R$ {{ number_format($revenue->gross_revenue, 2, ',', '.') }}</td>
                    <td>{{ number_format($revenue->sales_count, 0, ',', '.') }}</td>
                    <td>R$ {{ number_format($revenue->average_ticket, 2, ',', '.') }}</td>
                    <td>{{ number_format($revenue->cmv_percentage, 2, ',', '.') }}%</td>
                    <td class="actions">
                        <a class="btn small secondary" href="{{ route('resumo.index', ['mes' => $revenue->reference_month->format('Y-m')]) }}">Resumo</a>
                        <a class="btn small secondary" href="{{ route('faturamento-mensal.edit', $revenue) }}">Editar</a>
                        <form method="post" action="{{ route('faturamento-mensal.destroy', $revenue) }}">
                            @csrf
                            @method('delete')
                            <button class="btn small danger" type="submit">Remover</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhum mes cadastrado.</td></tr>
            @endforelse
            </tbody>
        </table></div><div class="pagination">{{ $revenues->links() }}</div>
    </div>
@endsection

