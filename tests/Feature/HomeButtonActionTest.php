<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HomeButtonActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_runs_alpha_earnings_action(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('app:alpha', ['--all' => true, '--earnings' => true])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn("OK\n");

        $res = $this->postJson('/api/home-button-action', [
            'action' => 'alpha_earnings',
        ]);

        $res->assertOk();
        $res->assertJson([
            'ok' => true,
            'mode' => 'sync',
            'action' => 'alpha_earnings',
            'command' => 'app:alpha',
            'exit_code' => 0,
        ]);
    }

    public function test_rejects_unknown_action(): void
    {
        $res = $this->postJson('/api/home-button-action', [
            'action' => 'nope',
        ]);

        $res->assertStatus(422);
    }
}

