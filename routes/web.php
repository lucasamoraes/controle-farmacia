<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoletoUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonthlyRevenueController;
use App\Http\Controllers\PayableController;
use App\Http\Controllers\SpreadsheetImportController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

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
    Route::resource('faturamento-mensal', MonthlyRevenueController::class)->parameters([
        'faturamento-mensal' => 'faturamento',
    ])->except(['show']);

    Route::get('importar/boletos', [SpreadsheetImportController::class, 'create'])->name('imports.boletos.create');
    Route::get('importar/boletos/modelo', [SpreadsheetImportController::class, 'template'])->name('imports.boletos.template');
    Route::post('importar/boletos', [SpreadsheetImportController::class, 'store'])->name('imports.boletos.store');

    Route::resource('fornecedores', SupplierController::class)->except(['show']);

    Route::get('boletos/novo', [BoletoUploadController::class, 'create'])->name('boletos.create');
    Route::post('boletos', [BoletoUploadController::class, 'store'])->name('boletos.store');
    Route::get('boletos/{boleto}/senha', [BoletoUploadController::class, 'password'])->name('boletos.password');
    Route::post('boletos/{boleto}/senha', [BoletoUploadController::class, 'unlock'])->name('boletos.unlock');
    Route::get('boletos/{boleto}/revisar', [BoletoUploadController::class, 'review'])->name('boletos.review');
    Route::post('boletos/{boleto}/confirmar', [BoletoUploadController::class, 'confirm'])->name('boletos.confirm');

    Route::resource('contas-a-pagar', PayableController::class)->except(['show']);
    Route::patch('contas-a-pagar/{contas_a_pagar}/marcar-paga', [PayableController::class, 'markAsPaid'])->name('payables.mark-paid');
    Route::delete('contas-a-pagar/{contas_a_pagar}/excluir', [PayableController::class, 'delete'])->name('payables.delete');
});


