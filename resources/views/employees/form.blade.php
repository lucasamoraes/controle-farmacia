@extends('layouts.app', ['pageTitle' => $employee->exists ? 'Editar funcionario' : 'Novo funcionario'])

@section('content')
    <div class="actions" style="justify-content:space-between; align-items:flex-start;">
        <div>
            <h1 class="title">{{ $employee->exists ? 'Editar funcionario' : 'Novo funcionario' }}</h1>
            <p class="subtitle">Informe os dados do colaborador e o salario base. As bases do recibo sao calculadas automaticamente.</p>
        </div>
        <a class="btn secondary" href="{{ route('funcionarios.index') }}">Voltar</a>
    </div>

    <form class="form" method="post" action="{{ $employee->exists ? route('funcionarios.update', $employee) : route('funcionarios.store') }}" style="margin-top:22px;" data-confirm-title="Salvar funcionario" data-confirm-message="Deseja salvar este funcionario?" data-confirm-button="Salvar">
        @csrf
        @if ($employee->exists)
            @method('put')
        @endif

        <label>Nome
            <input name="name" value="{{ old('name', $employee->name) }}" required autofocus>
            @error('name') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="field-grid">
            <label>Codigo
                <input name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" placeholder="Ex: 8">
                @error('employee_code') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>CPF ou documento
                <input name="document" value="{{ old('document', $employee->document) }}" inputmode="numeric">
                @error('document') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Cargo
                <select name="role" required data-role-input>
                    <option value="">Selecione</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->name }}" data-cbo="{{ $position->cbo_code }}" @selected(old('role', $employee->role) === $position->name)>{{ $position->name }}</option>
                    @endforeach
                </select>
                @error('role') <span class="error">{{ $message }}</span> @enderror
                @if ($positions->isEmpty())
                    <span class="error">Cadastre cargos em Configuracao > Funcionarios antes de cadastrar a equipe.</span>
                @endif
            </label>
            <label>CBO
                <input name="cbo_code" value="{{ old('cbo_code', $employee->cbo_code) }}" placeholder="Ex: 521130" data-cbo-input readonly>
                @error('cbo_code') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Departamento
                <select name="department" required>
                    <option value="">Selecione</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->name }}" @selected(old('department', $employee->department) === $department->name)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department') <span class="error">{{ $message }}</span> @enderror
                @if ($departments->isEmpty())
                    <span class="error">Cadastre departamentos em Configuracao > Funcionarios.</span>
                @endif
            </label>
            <label>Filial
                <input name="branch" value="{{ old('branch', $employee->branch) }}" placeholder="Ex: 1">
                @error('branch') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Salario base
                <input type="number" step="0.01" min="0" name="base_salary" value="{{ old('base_salary', $employee->base_salary ?? $employee->salary ?? 0) }}" required>
                @error('base_salary') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Dia de pagamento
                <input type="number" min="1" max="31" name="payment_day" value="{{ old('payment_day', $employee->payment_day ?? 5) }}" required>
                @error('payment_day') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="field-grid">
            <label>Admissao
                <input type="date" name="starts_on" value="{{ old('starts_on', optional($employee->starts_on)->format('Y-m-d')) }}">
                @error('starts_on') <span class="error">{{ $message }}</span> @enderror
            </label>
            <label>Fim
                <input type="date" name="ends_on" value="{{ old('ends_on', optional($employee->ends_on)->format('Y-m-d')) }}">
                @error('ends_on') <span class="error">{{ $message }}</span> @enderror
            </label>
        </div>

        @if ($employee->exists)
            <section class="alert info" style="margin:0;">
                <strong>Bases atuais calculadas</strong>
                INSS: R$ {{ number_format($employee->inss_salary, 2, ',', '.') }} ·
                FGTS: R$ {{ number_format($employee->fgts_month, 2, ',', '.') }} ·
                IRRF base: R$ {{ number_format($employee->irrf_base, 2, ',', '.') }}.
            </section>
        @endif

        <label>Observacoes
            <textarea name="notes">{{ old('notes', $employee->notes) }}</textarea>
            @error('notes') <span class="error">{{ $message }}</span> @enderror
        </label>

        <div class="actions">
            <button class="btn" type="submit">Salvar funcionario</button>
            <a class="btn secondary" href="{{ route('funcionarios.index') }}">Cancelar</a>
        </div>
    </form>
    <script>
        const roleInput = document.querySelector('[data-role-input]');
        const cboInput = document.querySelector('[data-cbo-input]');
        const positions = @json($positions->map(fn ($position) => ['name' => $position->name, 'cbo' => $position->cbo_code])->values());
        const syncCbo = () => {
            const found = positions.find((position) => position.name === roleInput?.value);
            if (cboInput) cboInput.value = found?.cbo || '';
        };
        roleInput?.addEventListener('change', syncCbo);
        if (roleInput?.value && !cboInput?.value) syncCbo();
    </script>
@endsection
