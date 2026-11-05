<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_administration_screens_have_working_data_endpoints(): void
    {
        $user = User::factory()->create(['admin' => 1]);
        $this->actingAs($user, 'api');
        foreach (['orders', 'orders/statistics', 'customers', 'products', 'categories', 'shipping-zones', 'shipping-options', 'shipping-package-sizes', 'collection-points', 'auth/users'] as $path) {
            $this->getJson('/api/'.$path)->assertOk()->assertJsonPath('status', 'success');
        }
    }
}
