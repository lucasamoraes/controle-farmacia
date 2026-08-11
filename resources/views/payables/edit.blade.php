@extends('layouts.app', ['pageTitle' => 'Editar conta'])

@section('content')
    <h1 class="title">Editar conta a pagar</h1>
    <p class="subtitle" style="margin-bottom:22px;">Ajuste valor, vencimento, fornecedor ou classificacao.</p>

    <form class="form" method="post" action="{{ route('contas-a-pagar.update', $payable) }}" data-confirm-title="Salvar conta" data-confirm-message="Deseja salvar as alteracoes desta conta a pagar?" data-confirm-button="Salvar">
        @csrf
        @method('put')
        @include('payables._form')
    </form>
@endsection
