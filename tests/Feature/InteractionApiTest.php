<?php

namespace Tests\Feature;

use App\Models\CategoryProduit;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InteractionApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $category;
    protected $produit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = CategoryProduit::factory()->create();
        $this->produit = Produit::factory()->create([
            'category_id' => $this->category->id
        ]);
        \App\Models\ProduitCount::create(['produit_id' => $this->produit->id]);
    }

    /** @test */
    public function it_can_toggle_favorite_on_a_product()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/produits/{$this->produit->id}/favorite");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);

        // Check if interaction is recorded (assuming toggleFavorite creates an entry or sets a status)
        // Adjust based on actual implementation in FavoriteController
    }

    /** @test */
    public function it_can_fetch_product_interaction_counts()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/produits/{$this->produit->id}/counts");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'favorites_count',
                    'partages_count',
                    'contacts_count',
                ]
            ]);
    }

    /** @test */
    public function it_can_record_a_product_view()
    {
        $payload = [
            'product_id' => $this->produit->id
        ];

        $response = $this->actingAs($this->user)->postJson('/record_view', $payload);

        $response->assertStatus(200);
        // Check if view count increased if applicable
    }

    /** @test */
    public function it_can_list_favorites()
    {
        // First favorite the product
        $this->actingAs($this->user)
            ->postJson("/produits/{$this->produit->id}/favorite");

        $response = $this->actingAs($this->user)
            ->getJson('/favorites');

        $response->assertStatus(200);
        // Verify JSON structure based on FavoriteController@index
    }
}
