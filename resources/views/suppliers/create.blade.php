@extends('layouts.app', ['pageTitle' => 'Novo fornecedor'])

@section('content')
    <h1 class="title">Novo fornecedor</h1>
    <p class="subtitle" style="margin-bottom:22px;">Cadastre fornecedores para facilitar boletos e contas a pagar.</p>

    <form class="form" method="post" action="{{ route('fornecedores.store') }}">
        @csrf
        @include('suppliers._form')
    </form>
@endsection
