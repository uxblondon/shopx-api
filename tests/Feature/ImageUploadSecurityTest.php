<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_content_is_rejected_and_original_filenames_cannot_overwrite_existing_files(): void
    {
        config(['filesystems.default' => 'public']); Storage::fake('public');
        $user = User::factory()->create(['admin' => 1]); $this->actingAs($user, 'api');
        $product = Product::create(['title' => 'Fixture', 'slug' => 'fixture', 'description' => 'Fixture', 'created_by' => $user->id]);
        $url = '/api/products/'.$product->id.'/images';
        foreach (['image.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image.jpg' => '<html><script>alert(1)</script></html>'] as $filename => $contents) {
            $this->post($url, ['image' => UploadedFile::fake()->createWithContent($filename, $contents)], ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }
        Storage::disk('public')->assertDirectoryEmpty('');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jS1sAAAAASUVORK5CYII=');
        $a = $this->post($url, ['image' => UploadedFile::fake()->createWithContent('same.png', $png)], ['Accept' => 'application/json'])->assertOk();
        $b = $this->post($url, ['image' => UploadedFile::fake()->createWithContent('same.png', $png)], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotSame($a->json('data.location'), $b->json('data.location'));
        $this->putJson($url.'/'.$a->json('data.id'), ['description' => 'Edited without a new file'])->assertOk();
    }

    public function test_image_delete_cannot_remove_an_object_outside_its_product_directory(): void
    {
        config(['filesystems.default' => 'public']); Storage::fake('public');
        $user = User::factory()->create(['admin' => 1]); $this->actingAs($user, 'api');
        $product = Product::create(['title' => 'Fixture', 'slug' => 'fixture', 'description' => 'Fixture', 'created_by' => $user->id]);
        Storage::disk('public')->put('private-fixture.txt', 'keep');
        $image = ProductImage::create(['product_id' => $product->id, 'location' => Storage::url('private-fixture.txt')]);
        $this->deleteJson('/api/products/'.$product->id.'/images/'.$image->id)->assertOk();
        Storage::disk('public')->assertExists('private-fixture.txt');
    }
}
