<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'trade_name',
        'document',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(FinancialCategory::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class);
    }

    public function monthlyRevenues(): HasMany
    {
        return $this->hasMany(MonthlyRevenue::class);
    }

    public function dailySales(): HasMany
    {
        return $this->hasMany(DailySale::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    public function creditCardInvoices(): HasMany
    {
        return $this->hasMany(CreditCardInvoice::class);
    }

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function employeeDepartments(): HasMany
    {
        return $this->hasMany(EmployeeDepartment::class);
    }

    public function boletoUploads(): HasMany
    {
        return $this->hasMany(BoletoUpload::class);
    }
}
