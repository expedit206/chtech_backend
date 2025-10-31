<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'espace-cameroun'),
    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', base_path('firebase-credentials.json')),
    ],
    'messaging' => [
        'sender_id' => env('FIREBASE_SENDER_ID', '47497828463'),
        'server_key' => env('FIREBASE_SERVER_KEY'),
    ],
];