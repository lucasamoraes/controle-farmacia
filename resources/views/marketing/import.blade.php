@extends('layouts.app', ['pageTitle' => 'Marketing'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Dados do Meta Ads</h1>
            <p class="subtitle">Importe a planilha exportada do Gerenciador de Anuncios para atualizar os indicadores de marketing.</p>
        </div>
        <a class="btn secondary" href="{{ route('relatorios.marketing.index') }}">Ver relatorio</a>
    </div>

    @if (session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="alert" style="margin-top:18px;">
            <strong>Importacao concluida</strong>
            Criados: {{ $result['created'] ?? 0 }} | Atualizados: {{ $result['updated'] ?? 0 }} | Ignorados: {{ $result['skipped'] ?? 0 }}
            @foreach (array_slice($result['errors'] ?? [], 0, 5) as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="stats" style="margin-top:22px;">
        <div class="stat"><span>LINHAS CONSOLIDADAS</span><strong>{{ number_format($metricsCount, 0, ',', '.') }}</strong></div>
        <div class="stat"><span>DADOS ATE</span><strong>{{ $lastPeriod ? \Illuminate\Support\Carbon::parse($lastPeriod)->format('d/m/Y') : '-' }}</strong></div>
    </div>

    <div class="grid" style="grid-template-columns:minmax(0, 1.1fr) minmax(320px, .9fr); align-items:start; margin-top:22px;">
        <form class="form" method="post" action="{{ route('marketing.import.store') }}" enctype="multipart/form-data">
            @csrf
            <h2 class="panel-title">Importar relatorio</h2>
            <label>Planilha do Meta Ads
                <input type="file" name="spreadsheet" accept=".xlsx,.xls,.csv" required>
                @error('spreadsheet') <span class="error">{{ $message }}</span> @enderror
            </label>
            <div class="alert info" style="margin:0;">Exporte os dados por mes no Meta Ads. Reimportar o mesmo periodo atualiza os registros existentes sem duplicar campanhas.</div>
            <div class="actions"><button class="btn" type="submit">Importar e analisar</button></div>
        </form>

        <section class="card">
            <h2 class="panel-title">Colunas reconhecidas</h2>
            <p class="subtitle">Periodo, nome do conjunto de anuncios, resultados, alcance, frequencia, valor usado, impressoes, CPM, cliques no link, CPC, CTR e visualizacoes da pagina de destino.</p>
            <p class="subtitle" style="margin-top:12px;">Os dados originais de cada linha tambem ficam preservados para auditoria.</p>
        </section>
    </div>

    <section class="card" style="margin-top:22px;">
        <h2 class="panel-title">Historico de importacoes</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Importado em</th><th>Arquivo</th><th>Periodo</th><th>Criados</th><th>Atualizados</th><th>Ignorados</th></tr></thead>
            <tbody>
                @forelse ($imports as $import)
                    <tr>
                        <td>{{ $import->created_at->format('d/m/Y H:i') }}</td><td>{{ $import->original_filename }}</td>
                        <td>{{ $import->period_start?->format('d/m/Y') ?? '-' }} a {{ $import->period_end?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $import->rows_created }}</td><td>{{ $import->rows_updated }}</td><td>{{ $import->rows_skipped }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhuma planilha de marketing importada.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:14px;">{{ $imports->links() }}</div>
    </section>
@endsection
