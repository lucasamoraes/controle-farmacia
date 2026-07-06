<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoletoUploadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_boleto_upload_page(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Farmacia Teste']);
        $company->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get('/boletos/novo')
            ->assertOk()
            ->assertSee('Enviar boleto PDF');
    }
}
