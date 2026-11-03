<?php

namespace Tests\Feature;

use Aws\MockHandler;
use Aws\Result;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SpacesStorageTest extends TestCase
{
    private function disk(MockHandler $handler)
    {
        return Storage::build(array_merge(config('filesystems.disks.s3'), [
            'key' => 'test-only-key',
            'secret' => 'test-only-secret',
            'region' => 'us-east-1',
            'bucket' => 'uxb',
            'endpoint' => 'https://lon1.digitaloceanspaces.com',
            'url' => 'https://uxb.lon1.digitaloceanspaces.com',
            'root' => 'th-ecom/staging',
            'visibility' => 'public',
            'use_path_style_endpoint' => false,
            'handler' => $handler,
        ]));
    }

    public function test_spaces_urls_include_the_staging_prefix_once(): void
    {
        $this->assertSame('', config('filesystems.disks.s3.root'));
        $disk = $this->disk(new MockHandler());

        $this->assertSame(
            'https://uxb.lon1.digitaloceanspaces.com/th-ecom/staging/products/172/image.jpg',
            $disk->url('products/172/image.jpg')
        );
    }

    public function test_spaces_uploads_use_the_prefix_and_public_image_visibility(): void
    {
        $handler = new MockHandler([new Result()]);
        $this->assertTrue($this->disk($handler)->put('products/172/image.jpg', 'test image content'));

        $command = $handler->getLastCommand();
        $this->assertSame('uxb', $command['Bucket']);
        $this->assertSame('th-ecom/staging/products/172/image.jpg', $command['Key']);
        $this->assertSame('public-read', $command['ACL']);
    }

    public function test_spaces_deletes_use_the_same_scoped_object_key(): void
    {
        $handler = new MockHandler([new Result()]);
        $this->assertTrue($this->disk($handler)->delete('products/172/image.jpg'));

        $this->assertSame('th-ecom/staging/products/172/image.jpg', $handler->getLastCommand()['Key']);
    }
}
