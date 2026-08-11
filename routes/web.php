<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoletoUploadController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\CreditCardInvoiceController;
use App\Http\Controllers\DailySalesImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReferenceController;
use App\Http\Controllers\FinancialCategoryController;
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
        Route::post('importar/vendas-diarias/manual', [DailySalesImportController::class, 'storeManual'])->name('imports.vendas-diarias.manual');
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
        Route::get('funcionarios/{funcionario}/recibo', [EmployeeController::class, 'receipt'])->name('funcionarios.recibo');
        Route::post('funcionarios/{funcionario}/recibo/eventos', [EmployeeController::class, 'storePayrollItem'])->name('funcionarios.recibo.eventos.store');
        Route::delete('funcionarios/recibo/eventos/{item}', [EmployeeController::class, 'deletePayrollItem'])->name('funcionarios.recibo.eventos.destroy');
        Route::post('funcionarios/{funcionario}/vales', [EmployeeController::class, 'storeAdvance'])->name('funcionarios.vales.store');
        Route::delete('funcionarios/vales/{vale}', [EmployeeController::class, 'deleteAdvance'])->name('funcionarios.vales.destroy');
        Route::get('funcionarios/{funcionario}/edit', [EmployeeController::class, 'edit'])->name('funcionarios.edit');
        Route::put('funcionarios/{funcionario}', [EmployeeController::class, 'update'])->name('funcionarios.update');
        Route::patch('funcionarios/{funcionario}', [EmployeeController::class, 'update']);
        Route::delete('funcionarios/{funcionario}', [EmployeeController::class, 'destroy'])->name('funcionarios.destroy');
        Route::patch('funcionarios/{funcionario}/reativar', [EmployeeController::class, 'restore'])->name('funcionarios.restore');
    });

    Route::get('faturas-cartao', [CreditCardInvoiceController::class, 'index'])->name('faturas-cartao.index');
    Route::middleware('company.role:owner,finance')->group(function () {
        Route::get('faturas-cartao/create', [CreditCardInvoiceController::class, 'create'])->name('faturas-cartao.create');
        Route::post('faturas-cartao', [CreditCardInvoiceController::class, 'store'])->name('faturas-cartao.store');
        Route::get('faturas-cartao/{faturas_cartao}/edit', [CreditCardInvoiceController::class, 'edit'])->name('faturas-cartao.edit');
        Route::put('faturas-cartao/{faturas_cartao}', [CreditCardInvoiceController::class, 'update'])->name('faturas-cartao.update');
        Route::delete('faturas-cartao/{faturas_cartao}', [CreditCardInvoiceController::class, 'destroy'])->name('faturas-cartao.destroy');
        Route::patch('faturas-cartao/{faturas_cartao}/marcar-paga', [CreditCardInvoiceController::class, 'markAsPaid'])->name('faturas-cartao.mark-paid');
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

    Route::get('configuracoes/categorias', [FinancialCategoryController::class, 'index'])->name('configuracoes.categorias.index');
    Route::middleware('company.role:owner,finance')->group(function () {
        Route::post('configuracoes/categorias', [FinancialCategoryController::class, 'store'])->name('configuracoes.categorias.store');
        Route::put('configuracoes/categorias/{categoria}', [FinancialCategoryController::class, 'update'])->name('configuracoes.categorias.update');
        Route::delete('configuracoes/categorias/{categoria}', [FinancialCategoryController::class, 'destroy'])->name('configuracoes.categorias.destroy');
        Route::get('configuracoes/cartoes', [CreditCardController::class, 'index'])->name('configuracoes.cartoes.index');
        Route::post('configuracoes/cartoes', [CreditCardController::class, 'store'])->name('configuracoes.cartoes.store');
        Route::put('configuracoes/cartoes/{cartao}', [CreditCardController::class, 'update'])->name('configuracoes.cartoes.update');
        Route::delete('configuracoes/cartoes/{cartao}', [CreditCardController::class, 'destroy'])->name('configuracoes.cartoes.destroy');
        Route::get('configuracoes/funcionarios', [EmployeeReferenceController::class, 'index'])->name('configuracoes.funcionarios.index');
        Route::post('configuracoes/funcionarios/cargos', [EmployeeReferenceController::class, 'storePosition'])->name('configuracoes.funcionarios.cargos.store');
        Route::put('configuracoes/funcionarios/cargos/{cargo}', [EmployeeReferenceController::class, 'updatePosition'])->name('configuracoes.funcionarios.cargos.update');
        Route::delete('configuracoes/funcionarios/cargos/{cargo}', [EmployeeReferenceController::class, 'destroyPosition'])->name('configuracoes.funcionarios.cargos.destroy');
        Route::post('configuracoes/funcionarios/departamentos', [EmployeeReferenceController::class, 'storeDepartment'])->name('configuracoes.funcionarios.departamentos.store');
        Route::put('configuracoes/funcionarios/departamentos/{departamento}', [EmployeeReferenceController::class, 'updateDepartment'])->name('configuracoes.funcionarios.departamentos.update');
        Route::delete('configuracoes/funcionarios/departamentos/{departamento}', [EmployeeReferenceController::class, 'destroyDepartment'])->name('configuracoes.funcionarios.departamentos.destroy');
        Route::post('configuracoes/funcionarios/movimentos', [EmployeeReferenceController::class, 'storeMovementType'])->name('configuracoes.funcionarios.movimentos.store');
        Route::put('configuracoes/funcionarios/movimentos/{movimento}', [EmployeeReferenceController::class, 'updateMovementType'])->name('configuracoes.funcionarios.movimentos.update');
        Route::delete('configuracoes/funcionarios/movimentos/{movimento}', [EmployeeReferenceController::class, 'destroyMovementType'])->name('configuracoes.funcionarios.movimentos.destroy');
    });
});


