@extends('layouts.app', ['pageTitle' => 'Nova conta'])

@section('content')
    <h1 class="title">Nova conta a pagar</h1>
    <p class="subtitle" style="margin-bottom:22px;">Cadastre boletos e despesas manualmente nesta primeira versao.</p>

    <form class="form" method="post" action="{{ route('contas-a-pagar.store') }}" data-confirm-title="Salvar conta" data-confirm-message="Deseja cadastrar esta conta a pagar?" data-confirm-button="Salvar">
        @csrf
        @include('payables._form')
    </form>
@endsection
