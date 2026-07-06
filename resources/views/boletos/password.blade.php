@extends('layouts.app', ['pageTitle' => 'Senha do boleto'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Boleto protegido por senha</h1>
            <p class="subtitle">Digite a senha do PDF para tentar extrair os dados do boleto.</p>
        </div>
        <a class="btn secondary" href="{{ route('boletos.create') }}">Enviar outro PDF</a>
    </div>

    <form class="form" method="post" action="{{ route('boletos.unlock', $boleto) }}" style="margin-top:22px;">
        @csrf

        <div class="card" style="background:#f8fafc;">
            <strong>{{ $boleto->original_file_name }}</strong>
            <p class="subtitle" style="margin-top:8px;">Alguns fornecedores usam CNPJ/CPF sem pontuacao, ultimos digitos do documento ou data como senha.</p>
            @if ($boleto->error_message)
                <p class="error" style="margin-bottom:0;">{{ str($boleto->error_message)->limit(180) }}</p>
            @endif
        </div>

        <label>Senha do PDF
            <input type="password" name="password" required autofocus>
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions">
            <button class="btn" type="submit">Abrir boleto</button>
            <a class="btn secondary" href="{{ route('contas-a-pagar.create') }}">Cadastrar manualmente</a>
        </div>
    </form>
@endsection

