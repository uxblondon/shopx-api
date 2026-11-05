<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLink;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

    public function test_password_resets_have_a_primary_key_without_changing_email_index_semantics(): void
    {
        $primary = collect(Schema::getIndexes('password_resets'))->firstWhere('primary', true);
        $this->assertNotNull($primary);
        $this->assertSame(['id'], $primary['columns']);

        DB::table('password_resets')->insert([
            ['email' => 'duplicate@example.test', 'token' => 'first-token'],
            ['email' => 'duplicate@example.test', 'token' => 'second-token'],
        ]);
        $this->assertSame(2, DB::table('password_resets')->where('email', 'duplicate@example.test')->count());
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
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jS1sAAAAASUVORK5CYII=');
        $upload = $this->post('/api/products/'.$product->id.'/images', [
            'image' => UploadedFile::fake()->createWithContent('gift.png', $png),
            'description' => 'Gift image',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'success');
        $path = 'products/'.$product->id.'/'.basename($upload->json('data.location'));
        Storage::disk('public')->assertExists($path);
        $id = $upload->json('data.id');
        $replacement = $this->put('/api/products/'.$product->id.'/images/'.$id, [
            'image' => UploadedFile::fake()->createWithContent('gift.png', $png),
            'description' => 'Updated image',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'success');
        $newPath = 'products/'.$product->id.'/'.basename($replacement->json('data.location'));
        $this->assertNotSame($path, $newPath);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists($newPath);
        $this->deleteJson('/api/products/'.$product->id.'/images/'.$id)->assertOk()->assertJsonPath('status', 'success');
        Storage::disk('public')->assertMissing($newPath);
        $this->assertDatabaseMissing('product_images', ['id' => $id]);
    }

    public function test_password_mail_renders_and_uses_the_symfony_array_transport(): void
    {
        $user = User::factory()->create();
        $mail = new PasswordResetLink($user, 'testing-token');
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
