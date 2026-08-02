<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();
        $company = $user?->companies()->first();

        abort_unless($user && $company, 403);
        abort_unless(in_array($user->roleForCompany($company), $roles, true), 403);

        return $next($request);
    }
}
