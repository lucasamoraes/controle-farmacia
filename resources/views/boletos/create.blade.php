@extends('layouts.app', ['pageTitle' => 'Upload de boleto'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">Enviar boleto</h1>
            <p class="subtitle">Envie PDF ou imagem do boleto. O sistema tenta ler os dados e abre uma revisao antes de criar a conta.</p>
        </div>
        <a class="btn secondary" href="{{ route('contas-a-pagar.index') }}">Contas a pagar</a>
    </div>

    <form class="form" method="post" action="{{ route('boletos.store') }}" enctype="multipart/form-data" style="margin-top:22px;">
        @csrf
        <label>Arquivo do boleto
            <input type="file" name="boleto_pdf" accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required>
            @error('boleto_pdf') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions">
            <button class="btn" type="submit">Ler boleto</button>
            <a class="btn secondary" href="{{ route('dashboard') }}">Cancelar</a>
        </div>
    </form>
@endsection
