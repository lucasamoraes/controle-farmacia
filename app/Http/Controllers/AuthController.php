<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        return view('auth.login', [
            'captchaQuestion' => $this->refreshLoginCaptcha($request),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'captcha' => ['required', 'string'],
        ]);
        $credentials = [
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        $this->ensureLoginIsNotThrottled($request);

        $captchaAnswer = (string) $request->session()->pull('login_captcha_answer', '');
        if (! hash_equals($captchaAnswer, trim((string) $data['captcha']))) {
            RateLimiter::hit($this->throttleKey($request), 60);
            $this->refreshLoginCaptcha($request);

            throw ValidationException::withMessages([
                'captcha' => 'Codigo de seguranca invalido.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), 60);
            $this->refreshLoginCaptcha($request);

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha invalidos.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();
        $request->session()->forget('login_captcha_answer');

        $company = Auth::user()?->companies()->first();
        if ($company && Auth::user()->roleForCompany($company) === 'buyer') {
            return redirect()->route('listas-compras.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        abort(404);
    }

    public function register(Request $request): RedirectResponse
    {
        abort(404);
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

    private function refreshLoginCaptcha(Request $request): string
    {
        $left = random_int(2, 9);
        $right = random_int(1, 9);
        $request->session()->put('login_captcha_answer', (string) ($left + $right));

        return "{$left} + {$right}";
    }

    private function ensureLoginIsNotThrottled(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));
        throw ValidationException::withMessages([
            'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }
}
