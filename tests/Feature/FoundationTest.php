<?php

namespace Tests\Feature;

use App\Domain\Identity\Actions\CreateFirstAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_liveness_endpoint_responds_without_exposing_details(): void
    {
        $this->get('/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_the_readiness_endpoint_confirms_all_migrations_ran(): void
    {
        $this->get('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    public function test_the_foundation_page_loads_without_a_frontend_build_step(): void
    {
        app(CreateFirstAdministrator::class)->handle(
            'Moola Owner',
            'owner@example.test',
            'Strong-Passphrase-42!',
            'Our Household',
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome to Moola')
            ->assertSee('/assets/app.css', false);
    }
}
