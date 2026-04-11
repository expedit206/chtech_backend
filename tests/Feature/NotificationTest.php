<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Message;
use App\Notifications\MessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_receives_notification_in_database_when_notified()
    {
        $sender = User::factory()->create(['nom' => 'Sender Unit']);
        $receiver = User::factory()->create(['nom' => 'Receiver Unit']);

        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'content' => 'Test message content',
            'type' => 'text'
        ]);

        // Triger notification
        $receiver->notify(MessageNotification::make($message, $sender));

        // Note: Since BaseNotification has ShouldQueue, we need to check if it's there
        // But in tests, we can use Notification::fake() or just check the database if connection is sync
        
        $this->assertEquals(1, $receiver->notifications()->count());
        $notification = $receiver->notifications()->first();
        
        $this->assertEquals('Nouveau message', $notification->data['title']);
        $this->assertStringContainsString('Sender Unit', $notification->data['message']);
    }

    public function test_notification_api_returns_list_correctly()
    {
        $user = User::factory()->create();
        $sender = User::factory()->create(['nom' => 'Sender API']);
        
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $user->id,
            'content' => 'API test content',
            'type' => 'text'
        ]);

        $user->notify(MessageNotification::make($message, $sender));

        $response = $this->actingAs($user)->getJson('/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'notifications' => [
                'data',
                'total'
            ],
            'unread_count'
        ]);
        
        $this->assertEquals(1, $response->json('unread_count'));
    }

    public function test_can_mark_notification_as_read()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $sender = User::factory()->create(['nom' => 'Sender Read']);
        
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $user->id,
            'content' => 'To be read',
            'type' => 'text'
        ]);

        $user->notify(MessageNotification::make($message, $sender));
        $notificationId = $user->notifications()->first()->id;

        $response = $this->actingAs($user)->putJson("/notifications/{$notificationId}/read");

        $response->assertStatus(200);
        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_sending_chat_message_creates_notification()
    {
        /** @var User $sender */
        $sender = User::factory()->create();
        /** @var User $receiver */
        $receiver = User::factory()->create();

        $response = $this->actingAs($sender)->postJson("/chat/{$receiver->id}", [
            'content' => 'Integration test message',
            'type' => 'text'
        ]);

        $response->assertStatus(201);
        
        $this->assertEquals(1, $receiver->notifications()->count());
        $this->assertStringContainsString('Integration test message', $receiver->notifications()->first()->data['message']);
    }
}
