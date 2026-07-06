@extends('layouts.app', ['pageTitle' => 'Importar boletos'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Importar aba BOLETOS</h1>
            <p class="subtitle">Importe a planilha antiga para criar fornecedores e contas a pagar sem retrabalho.</p>
        </div>
        <div class="actions"><a class="btn secondary" href="{{ route('imports.boletos.template') }}">Baixar modelo</a><a class="btn secondary" href="{{ route('contas-a-pagar.index') }}">Ver contas</a></div>
    </div>

    @if (session('import_result'))
        @php $result = session('import_result'); @endphp
        <div class="alert">
            Importacao concluida: {{ $result['created'] }} contas criadas, {{ $result['suppliers_created'] }} fornecedores criados, {{ $result['skipped'] }} linhas ignoradas.
        </div>
        @if (! empty($result['errors']))
            <div class="card" style="margin-bottom:18px; border-color:#fed7aa; background:#fff7ed;">
                <strong>Linhas ignoradas</strong>
                <ul style="margin-bottom:0; color:var(--muted);">
                    @foreach (array_slice($result['errors'], 0, 12) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    <form class="form" method="post" action="{{ route('imports.boletos.store') }}" enctype="multipart/form-data" style="margin-top:22px;">
        @csrf

        <label>Planilha Excel
            <input type="file" name="spreadsheet" accept=".xlsx,.xls" required>
            @error('spreadsheet') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="card" style="background:#f8fafc;">
            <strong>Leitura da aba BOLETOS</strong>
            <p class="subtitle" style="margin-top:8px;">O importador usa as colunas: vencimento, fornecedor, CNPJ, conta de pagamento, categoria, valor e nota fiscal.</p>
            <p class="subtitle" style="margin-top:8px;">O CNPJ e opcional. Quando vier preenchido, o sistema vincula pelo CNPJ; quando nao vier, cria ou usa o fornecedor pelo nome.</p>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Importar boletos</button>
            <a class="btn secondary" href="{{ route('dashboard') }}">Cancelar</a>
        </div>
    </form>
@endsection

