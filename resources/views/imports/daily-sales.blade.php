@extends('layouts.app', ['pageTitle' => 'Vendas diarias'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Vendas diarias</h1>
            <p class="subtitle">Registre as vendas do dia ou importe uma planilha. O faturamento mensal e atualizado automaticamente.</p>
        </div>
        <a class="btn secondary" href="{{ route('imports.vendas-diarias.template') }}">Baixar modelo</a>
    </div>

    @if (session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="alert" style="margin-top:18px;">
            <strong>Importacao concluida</strong>
            Criados: {{ $result['created'] ?? 0 }} |
            Atualizados: {{ $result['updated'] ?? 0 }} |
            Ignorados: {{ $result['skipped'] ?? 0 }}
            @if (! empty($result['errors']))
                <div style="margin-top:8px;">
                    @foreach (array_slice($result['errors'], 0, 6) as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <div class="grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-top:22px;">
        <form class="form" method="post" action="{{ route('imports.vendas-diarias.manual') }}">
            @csrf
            <h2 class="panel-title">Registrar venda do dia</h2>
            <div class="field-grid">
                <label>Data
                    <input type="date" name="sale_date" value="{{ old('sale_date', now()->toDateString()) }}" required>
                    @error('sale_date') <span class="error">{{ $message }}</span> @enderror
                </label>
                <label>Valor vendido
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                    @error('amount') <span class="error">{{ $message }}</span> @enderror
                </label>
            </div>
            <label>Dia da semana
                <input name="weekday" value="{{ old('weekday') }}" placeholder="Opcional, o sistema preenche pela data">
                @error('weekday') <span class="error">{{ $message }}</span> @enderror
            </label>
            <div class="alert info" style="margin:0;">
                Se a data ja existir, o valor daquele dia sera atualizado e o faturamento do mes sera recalculado.
            </div>
            <div class="actions">
                <button class="btn" type="submit">Salvar venda</button>
                <a class="btn secondary" href="{{ route('dashboard', ['aba' => 'vendas']) }}">Ver dashboard</a>
            </div>
        </form>

        <form class="form" method="post" action="{{ route('imports.vendas-diarias.store') }}" enctype="multipart/form-data">
            @csrf
            <h2 class="panel-title">Importar planilha</h2>
            <label>Planilha de vendas
                <input type="file" name="spreadsheet" accept=".xlsx,.xls,.csv" required>
                @error('spreadsheet') <span class="error">{{ $message }}</span> @enderror
            </label>

            <div class="card" style="padding:14px;">
                <h2 class="panel-title">Colunas aceitas</h2>
                <p class="subtitle">Use os nomes: DATA, VALOR e DIA DA SEMANA. Exemplo: 01/02/2026, 1500,75, domingo.</p>
                <p class="subtitle" style="margin-top:8px;">Se a data ja informar o dia, o campo dia da semana pode ficar em branco.</p>
            </div>

            <div class="actions">
                <button class="btn" type="submit">Importar vendas</button>
                <a class="btn secondary" href="{{ route('dashboard') }}">Voltar</a>
            </div>
        </form>
    </div>

    <section class="card" style="margin-top:22px;">
        <h2 class="panel-title">Ultimos lancamentos</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Data</th><th>Dia</th><th>Valor</th></tr></thead>
            <tbody>
                @forelse ($recentSales as $sale)
                    <tr>
                        <td>{{ $sale->sale_date->format('d/m/Y') }}</td>
                        <td>{{ $sale->weekday ?: '-' }}</td>
                        <td>R$ {{ number_format($sale->amount, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Nenhuma venda diaria cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
