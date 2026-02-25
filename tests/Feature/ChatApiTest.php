<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /** @test */
    public function it_can_fetch_conversations()
    {
        // Create some messages between users
        Message::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id,
            'content' => 'Hello there!'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'conversations' => [
                    '*' => [
                        'user_id',
                        'name',
                        'last_message',
                        'last_message_type',
                        'updated_at',
                        'unread_count',
                        'profile_photo'
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('conversations')); // user + 1 service client by default in controller
    }

    /** @test */
    public function it_can_fetch_chat_history()
    {
        Message::factory()->count(5)->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/chat/{$this->otherUser->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'messages',
                'hasMore',
                'user'
            ]);

        $this->assertCount(5, $response->json('messages'));
    }

    /** @test */
    public function it_can_send_a_message()
    {
        $payload = [
            'type' => 'text',
            'content' => 'Test message from unit test'
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/chat/{$this->otherUser->id}", $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Message envoyé avec succès'
            ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id,
            'content' => 'Test message from unit test'
        ]);
    }

    /** @test */
    public function it_can_update_a_message()
    {
        $message = Message::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id,
            'content' => 'Original content'
        ]);

        $payload = [
            'content' => 'Updated content'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/chat/message/{$message->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Message mis à jour avec succès'
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'Updated content'
        ]);
    }

    /** @test */
    public function it_can_delete_a_message()
    {
        $message = Message::factory()->create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/chat/message/{$message->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Message supprimé avec succès'
            ]);

        $this->assertDatabaseMissing('messages', [
            'id' => $message->id
        ]);
    }

    /** @test */
    public function it_cannot_delete_someone_elses_message()
    {
        $anotherUser = User::factory()->create();
        $message = Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'receiver_id' => $anotherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/chat/message/{$message->id}");

        $response->assertStatus(403);
    }
}
