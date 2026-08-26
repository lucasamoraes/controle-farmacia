<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_login_page_returns_a_successful_response(): void
    {
        $this->get('/entrar')->assertOk();
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/cadastro')->assertNotFound();
        $this->post('/cadastro')->assertNotFound();
    }
}
