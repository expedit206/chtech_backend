<?php

namespace Tests\Feature;

use App\Models\CategoryProduit;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProduitApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'fournisseur']);
        $this->category = CategoryProduit::factory()->create();
    }

    /** @test */
    public function it_can_list_user_products()
    {
        Produit::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/user/mesProduits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'produits'
            ]);

        $this->assertCount(3, $response->json('produits'));
    }

    /** @test */
    public function it_can_store_a_new_product()
    {
        Storage::fake('public');

        $payload = [
            'nom' => 'Nouveau Produit Test',
            'description' => 'Description du produit test',
            'prix' => 15000,
            'category_id' => $this->category->id,
            'stock' => 10,
            'ville' => 'Douala',
            'condition' => 'neuf',
            'revendable' => false,
            'est_actif' => true,
            'photos' => [
                UploadedFile::fake()->image('product1.jpg')
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/user/produits', $payload);

        $response->assertStatus(201) || $response->assertStatus(200); // Depend on controller implementation

        $this->assertDatabaseHas('produits', [
            'nom' => 'Nouveau Produit Test',
            'user_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_update_a_product()
    {
        $produit = Produit::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id
        ]);

        $payload = [
            'nom' => 'Produit Modifié',
            'description' => 'Ma nouvelle description',
            'prix' => 25000,
            'category_id' => $this->category->id,
            'stock' => 5,
            'condition' => 'occasion',
            'revendable' => true,
            'marge_min' => 1000,
            'old_photos' => [],
            '_method' => 'POST'
        ];

        // The route is POST /api/user/produits/{id}
        $response = $this->actingAs($this->user)
            ->postJson("/user/produits/{$produit->id}", $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('produits', [
            'id' => $produit->id,
            'nom' => 'Produit Modifié',
            'prix' => 25000
        ]);
    }

    /** @test */
    public function it_can_delete_a_product()
    {
        $produit = Produit::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $this->category->id
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/user/delete/produit/{$produit->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('produits', [
            'id' => $produit->id
        ]);
    }
}
