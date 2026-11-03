<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLink;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_actions_do_not_gain_missing_controllers_or_methods(): void
    {
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            if ($route->getActionName() === 'Closure') {
                continue;
            }
            [$class, $method] = explode('@', $route->getActionName());
            if (! method_exists($class, $method)) {
                $missing[] = $route->uri().' -> '.$class.'@'.$method;
            }
        }
        // These unused routes were already unimplemented before this framework upgrade.
        sort($missing);
        $this->assertSame([
            'api/customers/filter -> App\\Http\\Controllers\\Api\\CustomerController@filter',
            'api/orders/filter -> App\\Http\\Controllers\\Api\\OrderController@filter',
            'api/orders/sequence -> App\\Http\\Controllers\\Api\\OrderController@sequence',
            'api/orders/{order_id} -> App\\Http\\Controllers\\Api\\OrderController@destroy',
        ], $missing);
    }

    public function test_namespaced_user_seeder_loads(): void
    {
        $this->seed(\Database\Seeders\UsersTableSeeder::class);
        $this->assertGreaterThan(0, User::count());
    }

    public function test_cors_preflight_supports_the_react_clients(): void
    {
        $this->options('/api/products', [], [
            'Origin' => 'http://shopadmin.localhost:8080',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://shopadmin.localhost:8080');
    }

    public function test_product_upload_replacement_and_deletion_use_storage_keys(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        $user = User::factory()->create(['admin' => 1]);
        $this->actingAs($user, 'api');
        $product = Product::create(['title' => 'Gift', 'slug' => 'gift', 'description' => 'Gift', 'created_by' => $user->id]);
        $path = 'products/'.$product->id.'/gift.jpg';
        $upload = $this->post('/api/products/'.$product->id.'/images', [
            'image' => UploadedFile::fake()->create('gift.jpg', 2, 'image/jpeg'),
            'description' => 'Gift image',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'success');
        Storage::disk('public')->assertExists($path);
        $id = $upload->json('data.id');
        $this->put('/api/products/'.$product->id.'/images/'.$id, [
            'image' => UploadedFile::fake()->create('gift.jpg', 3, 'image/jpeg'),
            'description' => 'Updated image',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'success');
        Storage::disk('public')->assertExists($path);
        $this->deleteJson('/api/products/'.$product->id.'/images/'.$id)->assertOk()->assertJsonPath('status', 'success');
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('product_images', ['id' => $id]);
    }

    public function test_password_mail_renders_and_uses_the_symfony_array_transport(): void
    {
        $user = User::factory()->create();
        $user->password_reset_token = 'testing-token';
        $mail = new PasswordResetLink($user);
        $this->assertStringContainsString('testing-token', $mail->render());
        Mail::to($user->email)->send($mail);
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(\Illuminate\Mail\Transport\ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
    }

    public function test_existing_model_date_format_is_preserved(): void
    {
        $product = Product::create(['title' => 'Gift', 'slug' => 'gift', 'description' => 'Gift', 'created_by' => 1]);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $product->toArray()['created_at']);
    }
}
