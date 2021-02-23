<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

use Illuminate\Http\Response;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * 
     * @return void
     */
    public function test_get_list_of_tasks()
    {
        $this->json('get', 'api/products')
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'fasdf',
                            'fasdfa'
                        ]
                    ]
                ]
            );
    }

    public function test_store_product()
    {
        $product = array(
            'categroy_id' => $this->faker->randomDigit,
            'title' => $this->faker->text(20),
            'slug' => $this->faker->slug(),
            'feature_image' => $this->faker->imageUrl(640, 480),
            'standfirst' => $this->faker->text(100),
            'description' => $this->faker->text(200),
            'created_by' => 1
        );
        $response = $this->json('POST', '/api/products', $product);

        $response->assertStatus(201)
        ->assertJson($product);
    }
}
