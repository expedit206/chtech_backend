<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    public function generateJWT()
    {
        $privateKey = str_replace("\\n", "\n", env('FIREBASE_PRIVATE_KEY'));
        $clientEmail = env('FIREBASE_CLIENT_EMAIL');
        
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $now = Carbon::now();
        $payload = [
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now->timestamp,
            'exp' => $now->addHour()->timestamp,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));

        $signature = '';
        openssl_sign(
            $encodedHeader . '.' . $encodedPayload,
            $signature,
            $privateKey,
            'SHA256'
        );

        $encodedSignature = $this->base64UrlEncode($signature);

        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function getAccessToken()
    {
        try {
            $jwt = $this->generateJWT();
            
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? null;

        } catch (\Exception $e) {
            Log::error('Error getting Firebase access token: ' . $e->getMessage());
            return null;
        }
    }
}