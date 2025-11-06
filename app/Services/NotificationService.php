<?php

namespace App\Services;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class NotificationService
{
    protected $jwtService;
    protected $projectId;

    public function __construct(FirebaseService $jwtService)
    {
        $this->jwtService = $jwtService;
        $this->projectId = env('FIREBASE_PROJECT_ID');
    }

    /**
     * Envoyer une notification à un device spécifique
     */
    public function sendToDevice(string $deviceToken, array $notification, array $data = [])
    {
        try {
            $accessToken = $this->jwtService->getAccessToken();
            
            if (!$accessToken) {
                throw new \Exception('Could not get access token');
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $message = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => $notification,
                    'data' => $data,
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'content-available' => 1,
                                'sound' => 'default'
                            ]
                        ],
                        'headers' => [
                            'apns-priority' => '10'
                        ]
                    ],
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'high'
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $message);

            if ($response->successful()) {
                Log::info('Notification sent successfully', [
                    'device_token' => $deviceToken,
                    'response' => $response->json()
                ]);
                return $response->json();
            } else {
                Log::error('Failed to send notification', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return 'false';
            }

        } catch (\Exception $e) {
            Log::error('Error sending notification: ' . $e->getMessage());
            return $e;
        }
    }

    /**
     * Envoyer une notification à multiple devices
     */
    public function sendToMultipleDevices(array $deviceTokens, array $notification, array $data = [])
    {
        $results = [];
        
        foreach ($deviceTokens as $token) {
            $results[$token] = $this->sendToDevice($token, $notification, $data);
        }

        return $results;
    }

    /**
     * Envoyer une notification à un topic
     */
    public function sendToTopic(string $topic, array $notification, array $data = [])
    {
        try {
            $accessToken = $this->jwtService->getAccessToken();
            
            if (!$accessToken) {
                throw new \Exception('Could not get access token');
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $message = [
                'message' => [
                    'topic' => $topic,
                    'notification' => $notification,
                    'data' => $data,
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'content-available' => 1
                            ]
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $message);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Error sending topic notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Souscrire un device à un topic
     */
    public function subscribeToTopic(string $deviceToken, string $topic)
    {
        try {
            $accessToken = $this->jwtService->getAccessToken();
            
            $url = "https://iid.googleapis.com/v1/projects/{$this->projectId}/topics/{$topic}/rel/topics/{$topic}";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'to' => "/topics/{$topic}",
                'registration_tokens' => [$deviceToken]
            ]);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('Error subscribing to topic: ' . $e->getMessage());
            return false;
        }
    }
}