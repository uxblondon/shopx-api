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
                        '*' => []
                    ]
                ]
            );
    }

    public function test_create_product()
    {
        $this->json('get', 'api/products/create')
            ->assertStatus(Response::HTTP_OK);
    }
}
