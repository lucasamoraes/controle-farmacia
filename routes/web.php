<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoletoUploadController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\DailySalesImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MonthlyRevenueController;
use App\Http\Controllers\PayableController;
use App\Http\Controllers\ProductionInstallController;
use App\Http\Controllers\SpreadsheetImportController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/instalar', ProductionInstallController::class)->name('production.install');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/entrar', [AuthController::class, 'login'])->name('login.store');
    Route::get('/cadastro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/cadastro', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/resumo', SummaryController::class)->name('resumo.index');
    Route::get('/usuarios', [CompanyUserController::class, 'index'])->middleware('company.role:owner')->name('usuarios.index');
    Route::post('/usuarios', [CompanyUserController::class, 'store'])->middleware('company.role:owner')->name('usuarios.store');
    Route::patch('/usuarios/{usuario}', [CompanyUserController::class, 'update'])->middleware('company.role:owner')->name('usuarios.update');
    Route::delete('/usuarios/{usuario}', [CompanyUserController::class, 'destroy'])->middleware('company.role:owner')->name('usuarios.destroy');

    Route::get('faturamento-mensal', [MonthlyRevenueController::class, 'index'])->name('faturamento-mensal.index');
    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('faturamento-mensal/create', [MonthlyRevenueController::class, 'create'])->name('faturamento-mensal.create');
        Route::post('faturamento-mensal', [MonthlyRevenueController::class, 'store'])->name('faturamento-mensal.store');
        Route::get('faturamento-mensal/{faturamento}/edit', [MonthlyRevenueController::class, 'edit'])->name('faturamento-mensal.edit');
        Route::put('faturamento-mensal/{faturamento}', [MonthlyRevenueController::class, 'update'])->name('faturamento-mensal.update');
        Route::patch('faturamento-mensal/{faturamento}', [MonthlyRevenueController::class, 'update']);
        Route::delete('faturamento-mensal/{faturamento}', [MonthlyRevenueController::class, 'destroy'])->name('faturamento-mensal.destroy');
    });

    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('importar/boletos', [SpreadsheetImportController::class, 'create'])->name('imports.boletos.create');
        Route::get('importar/boletos/modelo', [SpreadsheetImportController::class, 'template'])->name('imports.boletos.template');
        Route::post('importar/boletos', [SpreadsheetImportController::class, 'store'])->name('imports.boletos.store');
        Route::get('importar/vendas-diarias', [DailySalesImportController::class, 'create'])->name('imports.vendas-diarias.create');
        Route::get('importar/vendas-diarias/modelo', [DailySalesImportController::class, 'template'])->name('imports.vendas-diarias.template');
        Route::post('importar/vendas-diarias', [DailySalesImportController::class, 'store'])->name('imports.vendas-diarias.store');
    });

    Route::get('fornecedores', [SupplierController::class, 'index'])->name('fornecedores.index');
    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('fornecedores/create', [SupplierController::class, 'create'])->name('fornecedores.create');
        Route::post('fornecedores', [SupplierController::class, 'store'])->name('fornecedores.store');
        Route::get('fornecedores/{fornecedore}/edit', [SupplierController::class, 'edit'])->name('fornecedores.edit');
        Route::put('fornecedores/{fornecedore}', [SupplierController::class, 'update'])->name('fornecedores.update');
        Route::patch('fornecedores/{fornecedore}', [SupplierController::class, 'update']);
        Route::delete('fornecedores/{fornecedore}', [SupplierController::class, 'destroy'])->name('fornecedores.destroy');
        Route::patch('fornecedores/{fornecedore}/reativar', [SupplierController::class, 'restore'])->name('fornecedores.restore');
    });

    Route::get('funcionarios', [EmployeeController::class, 'index'])->name('funcionarios.index');
    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('funcionarios/create', [EmployeeController::class, 'create'])->name('funcionarios.create');
        Route::post('funcionarios', [EmployeeController::class, 'store'])->name('funcionarios.store');
        Route::post('funcionarios/gerar-despesas', [EmployeeController::class, 'generateMonthlyPayables'])->name('funcionarios.generate-payables');
        Route::patch('funcionarios/pagar-folha', [EmployeeController::class, 'markPayrollAsPaid'])->name('funcionarios.mark-payroll-paid');
        Route::get('funcionarios/{funcionario}/edit', [EmployeeController::class, 'edit'])->name('funcionarios.edit');
        Route::put('funcionarios/{funcionario}', [EmployeeController::class, 'update'])->name('funcionarios.update');
        Route::patch('funcionarios/{funcionario}', [EmployeeController::class, 'update']);
        Route::delete('funcionarios/{funcionario}', [EmployeeController::class, 'destroy'])->name('funcionarios.destroy');
        Route::patch('funcionarios/{funcionario}/reativar', [EmployeeController::class, 'restore'])->name('funcionarios.restore');
    });

    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('boletos/novo', [BoletoUploadController::class, 'create'])->name('boletos.create');
        Route::post('boletos', [BoletoUploadController::class, 'store'])->name('boletos.store');
        Route::get('boletos/{boleto}/senha', [BoletoUploadController::class, 'password'])->name('boletos.password');
        Route::post('boletos/{boleto}/senha', [BoletoUploadController::class, 'unlock'])->name('boletos.unlock');
        Route::get('boletos/{boleto}/revisar', [BoletoUploadController::class, 'review'])->name('boletos.review');
        Route::post('boletos/{boleto}/confirmar', [BoletoUploadController::class, 'confirm'])->name('boletos.confirm');
    });

    Route::get('contas-a-pagar', [PayableController::class, 'index'])->name('contas-a-pagar.index');
    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('contas-a-pagar/create', [PayableController::class, 'create'])->name('contas-a-pagar.create');
        Route::post('contas-a-pagar', [PayableController::class, 'store'])->name('contas-a-pagar.store');
        Route::get('contas-a-pagar/{contas_a_pagar}/edit', [PayableController::class, 'edit'])->name('contas-a-pagar.edit');
        Route::put('contas-a-pagar/{contas_a_pagar}', [PayableController::class, 'update'])->name('contas-a-pagar.update');
        Route::patch('contas-a-pagar/{contas_a_pagar}', [PayableController::class, 'update']);
        Route::delete('contas-a-pagar/{contas_a_pagar}', [PayableController::class, 'destroy'])->name('contas-a-pagar.destroy');
        Route::patch('contas-a-pagar/{contas_a_pagar}/marcar-paga', [PayableController::class, 'markAsPaid'])->name('payables.mark-paid');
        Route::delete('contas-a-pagar/{contas_a_pagar}/excluir', [PayableController::class, 'delete'])->name('payables.delete');
    });
});


