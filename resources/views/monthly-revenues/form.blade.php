@extends('layouts.app', ['pageTitle' => 'Dados mensais'])

@php
    $editing = isset($revenue) && $revenue;
    $action = $editing ? route('faturamento-mensal.update', $revenue) : route('faturamento-mensal.store');
@endphp

@section('content')
    <h1 class="title">{{ $editing ? 'Editar dados mensais' : 'Registrar dados mensais' }}</h1>
    <p class="subtitle" style="margin-bottom:22px;">Esses dados alimentam os blocos de faturamento, vendas e CMV do resumo.</p>

    <form class="form" method="post" action="{{ $action }}">
        @csrf
        @if ($editing)
            @method('put')
        @endif

        <div class="field-grid">
            <label>Mes de referencia
                <input type="month" name="reference_month" value="{{ old('reference_month', $referenceMonth->format('Y-m')) }}" required>
                @error('reference_month') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Faturamento bruto
                <input type="number" step="0.01" min="0" name="gross_revenue" value="{{ old('gross_revenue', $revenue->gross_revenue ?? '') }}" required>
                @error('gross_revenue') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Faturamento a receber
                <input type="number" step="0.01" min="0" name="revenue_to_receive" value="{{ old('revenue_to_receive', $revenue->revenue_to_receive ?? 0) }}">
                @error('revenue_to_receive') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Quantidade de vendas
                <input type="number" min="0" name="sales_count" value="{{ old('sales_count', $revenue->sales_count ?? 0) }}">
                @error('sales_count') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>CMV (%)
                <input type="number" step="0.01" min="0" max="100" name="cmv_percentage" value="{{ old('cmv_percentage', $revenue->cmv_percentage ?? 0) }}">
                @error('cmv_percentage') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>CMV em valor
                <input type="number" step="0.01" min="0" name="cost_of_goods_sold" value="{{ old('cost_of_goods_sold', $revenue->cost_of_goods_sold ?? 0) }}">
                @error('cost_of_goods_sold') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Itens por ticket
                <input type="number" step="0.01" min="0" name="items_per_ticket" value="{{ old('items_per_ticket', $revenue->items_per_ticket ?? 0) }}">
                @error('items_per_ticket') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Ticket medio
                <input value="Calculado automaticamente pelo faturamento / vendas" disabled>
            </label>
        </div>

        <label>Informacoes importantes
            <textarea name="important_info">{{ old('important_info', $revenue->important_info ?? '') }}</textarea>
            @error('important_info') <span class="error">{{ $message }}</span> @enderror
        </label>

        <label>Observacoes
            <textarea name="notes">{{ old('notes', $revenue->notes ?? '') }}</textarea>
            @error('notes') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions">
            <button class="btn" type="submit">Salvar dados</button>
            <a class="btn secondary" href="{{ route('faturamento-mensal.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
