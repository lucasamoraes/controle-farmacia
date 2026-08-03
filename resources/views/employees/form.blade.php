@extends('layouts.app', ['pageTitle' => $employee->exists ? 'Editar funcionario' : 'Novo funcionario'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">{{ $employee->exists ? 'Editar funcionario' : 'Novo funcionario' }}</h1>
            <p class="subtitle">Informe o salario fixo recorrente e o variavel previsto para o mes.</p>
        </div>
        <a class="btn secondary" href="{{ route('funcionarios.index') }}">Voltar</a>
    </div>

    <form class="form" method="post" action="{{ $employee->exists ? route('funcionarios.update', $employee) : route('funcionarios.store') }}" style="margin-top:22px;">
        @csrf
        @if ($employee->exists)
            @method('put')
        @endif

        <label>Nome
            <input name="name" value="{{ old('name', $employee->name) }}" required autofocus>
            @error('name') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="field-grid">
            <label>CPF ou documento
                <input name="document" value="{{ old('document', $employee->document) }}" inputmode="numeric">
                @error('document') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Cargo
                <input name="role" value="{{ old('role', $employee->role) }}" placeholder="Balconista, caixa, gerente">
                @error('role') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Salario fixo
                <input type="number" step="0.01" min="0" name="fixed_salary" value="{{ old('fixed_salary', $employee->fixed_salary ?? $employee->salary ?? 0) }}" required>
                @error('fixed_salary') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Salario variavel
                <input type="number" step="0.01" min="0" name="variable_salary" value="{{ old('variable_salary', $employee->variable_salary ?? 0) }}">
                @error('variable_salary') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Dia de pagamento
                <input type="number" min="1" max="31" name="payment_day" value="{{ old('payment_day', $employee->payment_day ?? 5) }}" required>
                @error('payment_day') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Total previsto
                <input value="R$ {{ number_format((float) old('fixed_salary', $employee->fixed_salary ?? $employee->salary ?? 0) + (float) old('variable_salary', $employee->variable_salary ?? 0), 2, ',', '.') }}" readonly>
            </label>
        </div>

        <div class="field-grid">
            <label>Inicio
                <input type="date" name="starts_on" value="{{ old('starts_on', optional($employee->starts_on)->format('Y-m-d')) }}">
                @error('starts_on') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Fim
                <input type="date" name="ends_on" value="{{ old('ends_on', optional($employee->ends_on)->format('Y-m-d')) }}">
                @error('ends_on') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <label>Observacoes
            <textarea name="notes">{{ old('notes', $employee->notes) }}</textarea>
            @error('notes') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions">
            <button class="btn" type="submit">Salvar funcionario</button>
            <a class="btn secondary" href="{{ route('funcionarios.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
