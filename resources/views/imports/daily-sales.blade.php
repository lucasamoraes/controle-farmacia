@extends('layouts.app', ['pageTitle' => 'Importar vendas diarias'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Importar vendas diarias</h1>
            <p class="subtitle">Suba uma planilha com data, valor vendido no dia e dia da semana.</p>
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

    <form class="form" method="post" action="{{ route('imports.vendas-diarias.store') }}" enctype="multipart/form-data" style="margin-top:22px;">
        @csrf
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
@endsection
