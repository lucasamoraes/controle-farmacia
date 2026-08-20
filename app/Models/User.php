<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    public function roleForCompany(Company $company): ?string
    {
        $linkedCompany = $this->companies->firstWhere('id', $company->id)
            ?? $this->companies()->whereKey($company->id)->first();

        return $linkedCompany?->pivot?->role;
    }

    public function canManageUsers(Company $company): bool
    {
        return $this->roleForCompany($company) === 'owner';
    }

    public function canWriteFinance(Company $company): bool
    {
        return in_array($this->roleForCompany($company), ['owner', 'finance'], true);
    }

    public function canWritePurchaseList(Company $company): bool
    {
        return in_array($this->roleForCompany($company), ['owner', 'finance', 'buyer'], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
