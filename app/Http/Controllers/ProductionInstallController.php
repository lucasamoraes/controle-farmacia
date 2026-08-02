<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ProductionInstallController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless((bool) env('INSTALLER_ENABLED', false), 404);

        $token = (string) env('INSTALLER_TOKEN', '');
        abort_unless($token !== '' && hash_equals($token, (string) $request->query('token')), 403);

        Artisan::call('migrate', ['--force' => true]);

        return response()->view('install.done', [
            'output' => trim(Artisan::output()),
        ]);
    }
}
