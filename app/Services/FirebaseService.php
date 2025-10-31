<?php
// app/Services/FirebaseService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FirebaseService
{
    private $projectId;
    private $privateKey;
    private $clientEmail;
    private $tokenUri;

    public function __construct()
    {
        $this->projectId = 'espace-cameroun';
        $this->privateKey = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCmbBJ+jBmDJXkt\nWqumuMoTFS9g4OEC+wlUw0lrrAB7U9AEIT83Pesy26qdWcyy0N/axEHqyjwAC4om\nWZKx6U70ixO1k5QFnBRA+HUFdz0UojO+jdJvMG1RWi2LzmTf3nOd/rJyAuz5cV5u\n3NFR47w4807LQG0qY7JQ/9m5qDE0alCSFcSdWYU7/MEB481cMTd8kcj47WUWHN5T\ny6dZEyIU+Sgedhz7ngkTrBAgInBRkrsPhoCU8Ph3ACawg8vYFIRo6uf195kSTeE2\nRq57+e1HjBKHgZfddbk3s/jZedFZ+2WYk0uVx20hCAPhAqNnjuZUTWgsy21W9puw\nCkFwEgCzAgMBAAECggEAGdy0mQ7bjhpeNPKgtzWDTajepn776LMrVY1NlRpzLrct\nHd8uzD5mlQ42+uHwIh+8keoq1jpcTaN9cg08c0Ju/yi3xOFswNxq91fFE7beepzk\nTqoKeDX93wUVC+NkoLeYnwZsI7SZr//yrWbKbp/l6bHB3msiK9K3bMf9xVDCaMMW\njx3WQI8Tjc3YXebRd3BDlhYuBkbNaDH9HrddDulxoD/p+kLgQD8517kXAmUaIbmQ\niFoUcRKYUfZiPkxU+Rjrq/t81Va/P6C7X/Oh0tmrKGzjUqHVV0JrwTNMAeJKl6Fe\nYQEzHTix8A0aXCRoD0WRJh/3L0XiWc5E4H8JJlWzyQKBgQDYB2Z1h2RGZsfVJlsf\nbRklWBTxXkETieh3eF+OsXCAv5pdoFCUGU+qJPbNWQGYmW6pRPWAO9IZBZqzf5VV\nvBQoZZhECc4iKaXNUB4ko/Yj3ckBpwjk2aMGa4g8IYzqVaLub19xbwPSPs8zO24h\n1pIXd7FCCeeiIyQ6C9r87oWMOwKBgQDFNvVnlWpj2VTj67378BTDy/BHDzJNecUX\nMfqzKQyUGCmcDJW0cSfsNkKSKJDsqYG1RFLRyr9+/mfLhyJi0bBU2gHlEULTIwX8\nlKD4yHSl9MOjSEe8DJBZ2ZRpbMxaATEjvKmMsjMXwRqaf0dB5ExkMPoTRzKHqBYh\nrO8qoa0t6QKBgCKAlBGjTRWdZr8ZQCZ1wzVeqwGdvyECvpkOJZzhpAk8EMhxSU1N\n+ZZADxbe501a+/yW4erYIwcSCRIwB0bJIiMNjtMXCdAU/MEc0aOieDZkPq0/40DB\nrbLhy8Fmzl3CzaHtMb4pL+ANXgRdsoo+/dg3qpzUfiU8HZYTvKb25WdvAoGAVuKM\noXMRaSYRoUx16e3uqAhMNnI6fimcrEhno9D86q3ufwKIXfPQW9/X2l6m4q0XKQ4N\n0sGZNlDM170UaCbroaZeWVYOc8ilVY8BkDl4MrkwC9rHR9DdM8iI+x1ktm56AL2Y\nkRTdL6TynIhgk2YKRcXSFvYyC9QA1BtFTynliykCgYEAgauqbm01Cp4SSmdoBPnh\nfjcPM2z7x4UXGyP46VNgl7HrbsRWT0w9m7r1aKD7Dry5IV1SSdlH1YXX+eK9pceN\ngK35pdAmNEbzFEzzGgSJW2Qp3Q0Q9k6+3dFzpUoLqEuzHCBa177du+M7K39urJP2\ntxC2u6EHNDuqK5UdgE1AFpY=\n-----END PRIVATE KEY-----\n";
        $this->clientEmail = 'firebase-adminsdk-fbsvc@espace-cameroun.iam.gserviceaccount.com';
        $this->tokenUri = 'https://oauth2.googleapis.com/token';
    }

    /**
     * Obtenir un token d'accès JWT pour l'API Firebase
     */
 public function getAccessToken()
    {
        return Cache::remember('firebase_access_token', 3500, function () {
            $now = time();
            
            // Créer le payload JWT
            $payload = [
                'iss' => $this->clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $this->tokenUri,
                'exp' => $now + 3600,
                'iat' => $now
            ];

            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];

            // Encoder le header et payload
            $encodedHeader = $this->base64UrlEncode(json_encode($header));
            $encodedPayload = $this->base64UrlEncode(json_encode($payload));
            
            // Créer la signature
            $signature = '';
            openssl_sign(
                $encodedHeader . '.' . $encodedPayload,
                $signature,
                $this->privateKey,
                'SHA256'
            );

            $encodedSignature = $this->base64UrlEncode($signature);
            $jwt = $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;

            Log::info('🔐 JWT créé', ['jwt_header' => substr($jwt, 0, 50) . '...']);

            // Échanger le JWT contre un token d'accès
            $response = Http::timeout(30)->asForm()->post($this->tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

            Log::info('🔑 Réponse token Google', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('✅ Token d\'accès obtenu avec succès');
                return $data['access_token'];
            }

            throw new \Exception('Erreur HTTP ' . $response->status() . ': ' . $response->body());
        });
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * ENVOYER UNE NOTIFICATION (API Firebase V1)
     */
    public function sendNotification($fcmToken, $title, $body, $data = [])
    {
        try {
            $accessToken = $this->getAccessToken();
            Log::info('🔑 Token d\'accès récupéré', ['token' => substr($accessToken, 0, 20) . '...']);

            // URL CORRIGÉE - format correct pour Firebase v1
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'data' => $data,
                    'webpush' => [
                        'headers' => [
                            'Urgency' => 'normal'
                        ],
                        'notification' => [
                            'icon' => '/icons/icon-192x192.png',
                            'badge' => '/icons/badge-72x72.png'
                        ],
                        'fcm_options' => [
                            'link' => $data['action_url'] ?? 'https://espacecameroun.com'
                        ]
                    ]
                ]
            ];

            Log::info('📤 Envoi notification FCM', [
                'url' => $url,
                'token' => substr($fcmToken, 0, 8) . '...',
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->timeout(30)
              ->post($url, $payload);

            Log::info('📥 Réponse FCM', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info('✅ Notification FCM envoyée avec succès', [
                    'message_id' => $responseData['name'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['name'] ?? null,
                    'response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $response->status(),
                    'details' => $response->body()
                ];
            }

        } catch (\Exception $e) {
            Log::error('❌ Exception envoi notification FCM', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }




    /**
     * ENVOYER À PLUSIEURS APPAREILS
     */
    public function sendMulticastNotification(array $tokens, $title, $body, $data = [])
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($tokens as $token) {
            $result = $this->sendNotification($token, $title, $body, $data);
            $results[$token] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        Log::info('📤 Notification multicast terminée', [
            'total_tokens' => count($tokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);

        return [
            'success' => true,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * VALIDER UN TOKEN FCM
     * Pour valider, on envoie une notification silencieuse
     */
    public function validateToken($fcmToken)
    {
        try {
            // Envoyer une notification de test silencieuse
            $result = $this->sendNotification(
                $fcmToken, 
                'Validation', 
                '',
                ['validate' => 'true', 'silent' => 'true']
            );

            // Si on reçoit une erreur spécifique de token invalide
            if (!$result['success']) {
                $error = $result['details'] ?? '';
                if (str_contains($error, 'registration-token-not-registered') ||
                    str_contains($error, 'invalid-argument') ||
                    str_contains($error, 'unregistered')) {
                    return false;
                }
            }

            return $result['success'];

        } catch (\Exception $e) {
            Log::error('Erreur validation token FCM', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * MÉTHODE DE COMPATIBILITÉ - pour votre code existant
     */
    public function sendToTokenSimple($fcmToken, $title, $body, $data = [])
    {
        return $this->sendNotification($fcmToken, $title, $body, $data);
    }

    /**
     * MÉTHODE DE COMPATIBILITÉ
     */
    public function sendToTokens(array $tokens, $title, $body, $data = [])
    {
        return $this->sendMulticastNotification($tokens, $title, $body, $data);
    }
}