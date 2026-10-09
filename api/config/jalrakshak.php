<?php

return [

    // Single hardcoded officer login for the demo.
    'officer' => [
        'email' => env('OFFICER_EMAIL', 'officer@jalrakshak.in'),
        'password' => env('OFFICER_PASSWORD', 'jalrakshak2026'),
        'token' => env('OFFICER_TOKEN', 'jalrakshak-officer-demo-token'),
    ],

    // Disk that inundation maps live on. 's3' in production, 'public' for
    // offline local development.
    'maps_disk' => env('MAPS_DISK', 's3'),

    // Read here, not with env() deeper in the app: once php artisan config:cache
    // runs in production, env() outside a config file returns null.
    'aws' => [
        'region' => env('AWS_DEFAULT_REGION', 'ap-south-1'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'bucket' => env('AWS_BUCKET'),
    ],

    'sns' => [
        // When false the app records the alert and explains that SNS is off,
        // instead of failing. Set true once AWS credentials are in place.
        'enabled' => env('SNS_ENABLED', false),
        'topic_prefix' => env('SNS_TOPIC_PREFIX', 'jalrakshak-zone'),
    ],

];
