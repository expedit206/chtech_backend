    <?php
    return [

        'paths' => ['*', 'broadcasting/auth', 'sanctum/csrf-cookie', 'login', 'logout', 'password/generate-token'],

        'allowed_methods' => ['*'],

        // Liste explicite de ton front local et backend local
        'allowed_origins' => [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://localhost:5175',
            'http://localhost:5176',
            'http://localhost:5177',
            'http://localhost:4000',
            'http://localhost:3000',
            'http://127.0.0.1:5500',
            'http://localhost:8000',
            'https://espacecameroun.com',
            'espacecameroun',
            'https://api.espacecameroun.com'
        ],


        'allowed_origins_patterns' => [],

        'allowed_headers' => ['*'],

        'exposed_headers' => [],
    
        'supports_credentials' => true, // IMPORTANT pour Sanctum

    ];