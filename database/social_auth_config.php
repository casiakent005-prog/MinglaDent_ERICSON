<?php
// Social Authentication Configuration
// IMPORTANT: Never commit actual credentials to version control
// These should be set in environment variables or a separate config file

return [
    'facebook' => [
        'app_id' => getenv('FACEBOOK_APP_ID') ?: '1641743634210304',
        'app_secret' => getenv('FACEBOOK_APP_SECRET') ?: '7d1e27ae3c2d92d5fc50eb3acadd9d26',
        'redirect_uri' => 'http://localhost/innovate/login/facebook_callback.php',
        'graph_version' => 'v18.0'
    ],
    'google' => [
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: 'YOUR_GOOGLE_CLIENT_ID',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirect_uri' => 'http://localhost/innovate/login/google_callback.php'
    ]
];
?>
