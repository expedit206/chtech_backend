<?php

namespace Tests\Feature;

use App\Models\CategoryProduit;
use App\Models\Produit;
use App\Models\User;
use App\Models\PromotionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MarketplaceAndPromoTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function marketplace_returns_simple_pagination_meta()
    {
        // Create 30 products (per_page is 25 by default)
        Produit::factory()->count(30)->create();

        $response = $this->getJson('/marketplace/produits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'produits',
                'meta' => [
                    'current_page',
                    'per_page',
                    'has_more_pages'
                ]
            ])
            ->assertJsonMissing(['meta' => ['total', 'last_page']]);

        $this->assertTrue($response->json('meta.has_more_pages'));
    }


}
