<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FinancialCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'E-mail ou senha invalidos.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_document' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $company = Company::create([
            'name' => $data['company_name'],
            'trade_name' => $data['company_name'],
            'document' => $data['company_document'] ?? null,
        ]);

        $company->users()->attach($user->id, ['role' => 'owner']);
        $this->seedDefaultCategories($company);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Conta criada com sucesso.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function seedDefaultCategories(Company $company): void
    {
        $categories = [
            ['name' => 'Compra de mercadoria', 'type' => 'expense'],
            ['name' => 'Aluguel', 'type' => 'expense'],
            ['name' => 'Funcionarios', 'type' => 'expense'],
            ['name' => 'Energia', 'type' => 'expense'],
            ['name' => 'Internet e telefone', 'type' => 'expense'],
            ['name' => 'Contador', 'type' => 'expense'],
            ['name' => 'Combustivel', 'type' => 'expense'],
            ['name' => 'Taxas bancarias', 'type' => 'expense'],
            ['name' => 'Outros', 'type' => 'expense'],
            ['name' => 'Faturamento mensal', 'type' => 'revenue'],
        ];

        foreach ($categories as $category) {
            $company->categories()->create($category + ['is_default' => true]);
        }
    }
}
