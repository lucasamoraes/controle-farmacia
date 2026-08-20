<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyUserController extends Controller
{
    public function index(): View
    {
        $company = $this->company();

        return view('company-users.index', [
            'company' => $company,
            'users' => $company->users()->orderBy('name')->get(),
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:6'],
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
        }

        if ($company->users()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => 'Este usuario ja esta vinculado a esta farmacia.'])->withInput();
        }

        $company->users()->attach($user->id, ['role' => $data['role']]);

        return redirect()->route('usuarios.index')->with('status', 'Usuario vinculado a farmacia.');
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $company = $this->company();
        $this->abortUnlessLinked($company, $usuario);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys($this->roles()))],
        ]);

        if ($usuario->id === Auth::id() && $data['role'] !== 'owner') {
            return back()->withErrors(['role' => 'Voce nao pode remover seu proprio perfil de dono.']);
        }

        if ($this->ownerCount($company) <= 1 && $usuario->roleForCompany($company) === 'owner' && $data['role'] !== 'owner') {
            return back()->withErrors(['role' => 'Mantenha pelo menos um dono na farmacia.']);
        }

        $company->users()->updateExistingPivot($usuario->id, ['role' => $data['role']]);

        return redirect()->route('usuarios.index')->with('status', 'Permissao atualizada.');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        $company = $this->company();
        $this->abortUnlessLinked($company, $usuario);

        if ($usuario->id === Auth::id()) {
            return back()->withErrors(['usuario' => 'Voce nao pode remover seu proprio acesso.']);
        }

        if ($this->ownerCount($company) <= 1 && $usuario->roleForCompany($company) === 'owner') {
            return back()->withErrors(['usuario' => 'Mantenha pelo menos um dono na farmacia.']);
        }

        $company->users()->detach($usuario->id);

        return redirect()->route('usuarios.index')->with('status', 'Usuario removido da farmacia.');
    }

    private function company(): Company
    {
        return Auth::user()->companies()->firstOrFail();
    }

    private function abortUnlessLinked(Company $company, User $user): void
    {
        abort_unless($company->users()->whereKey($user->id)->exists(), 404);
    }

    private function ownerCount(Company $company): int
    {
        return $company->users()->wherePivot('role', 'owner')->count();
    }

    private function roles(): array
    {
        return [
            'owner' => 'Dono',
            'finance' => 'Financeiro',
            'buyer' => 'Balconista',
            'viewer' => 'Consulta',
        ];
    }
}
