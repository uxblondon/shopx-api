<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_administration_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
        $this->postJson('/api/products', [])->assertUnauthorized();
    }

    public function test_admin_can_create_update_list_and_delete_a_product(): void
    {
        $user = User::factory()->create(['admin' => 1]);
        $token = auth('api')->login($user);
        $headers = ['Authorization' => 'Bearer '.$token];
        $category = Category::create(['title' => 'Gifts', 'slug' => 'gifts', 'description' => 'Gifts', 'status' => 'published', 'created_by' => $user->id]);
        $payload = ['title' => 'Test Gift', 'description' => 'A test gift', 'categories' => [['id' => $category->id]]];
        $create = $this->postJson('/api/products', $payload, $headers)->assertOk()->assertJsonPath('status', 'success');
        $id = $create->json('data.id');
        $this->assertDatabaseHas('product_categories', ['product_id' => $id, 'category_id' => $category->id]);
        $this->getJson('/api/products', $headers)->assertOk()->assertJsonPath('data.0.title', 'Test Gift');
        $this->putJson('/api/products/'.$id, array_merge($payload, ['title' => 'Updated Gift', 'status' => 'published']), $headers)
            ->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('products', ['id' => $id, 'title' => 'Updated Gift']);
        $this->deleteJson('/api/products/'.$id, [], $headers)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSoftDeleted('products', ['id' => $id]);
    }

    public function test_invalid_product_returns_the_existing_validation_envelope(): void
    {
        $this->actingAs(User::factory()->create(['admin' => 1]), 'api')
            ->postJson('/api/products', [])->assertUnprocessable()
            ->assertJsonPath('status', 'error')->assertJsonValidationErrors(['title', 'categories', 'description']);
    }

    public function test_public_catalog_omits_drafts_and_retains_response_fields(): void
    {
        foreach (['published', 'draft'] as $status) {
            Product::create(['title' => $status, 'slug' => $status, 'description' => 'Gift', 'status' => $status, 'created_by' => 1]);
        }
        $this->getJson('/api/available-products')->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'published');
        $this->getJson('/api/published-products')->assertOk()->assertJsonPath('status', 'success')->assertJsonCount(1, 'data');
    }
}
