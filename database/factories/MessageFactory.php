<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'type' => 'text',
            'content' => $this->faker->sentence(),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
