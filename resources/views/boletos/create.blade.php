@extends('layouts.app', ['pageTitle' => 'Upload de boleto'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Enviar boleto PDF</h1>
            <p class="subtitle">O sistema vai tentar ler os dados principais e abrir uma revisao antes de criar a conta.</p>
        </div>
        <a class="btn secondary" href="{{ route('contas-a-pagar.index') }}">Contas a pagar</a>
    </div>

    <form class="form" method="post" action="{{ route('boletos.store') }}" enctype="multipart/form-data" style="margin-top:22px;">
        @csrf
        <label>Arquivo PDF do boleto
            <input type="file" name="boleto_pdf" accept="application/pdf,.pdf" required>
            @error('boleto_pdf') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="card" style="background:#f8fafc;">
            <strong>Primeira versao da automacao</strong>
            <p class="subtitle" style="margin-top:8px;">A leitura funciona melhor com PDF gerado digitalmente. PDF escaneado ou foto vai precisar de OCR em uma etapa futura.</p>
        </div>

        <div class="actions">
            <button class="btn" type="submit">Ler boleto</button>
            <a class="btn secondary" href="{{ route('dashboard') }}">Cancelar</a>
        </div>
    </form>
@endsection
