<?php

namespace Database\Factories;

use App\Models\CategoryProduit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryProduitFactory extends Factory
{
    protected $model = CategoryProduit::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'nom' => $this->faker->word(),
            'image' => 'categories/test.png',
        ];
    }
}
