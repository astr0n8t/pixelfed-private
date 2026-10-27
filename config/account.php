<?php

return [

    // Defaults for the admin autofollow settings (new accounts follow these local usernames)
    'autofollow' => env('AUTOFOLLOW_ENABLED', false),
    'autofollow_usernames' => env('AUTOFOLLOW_USERNAMES', ''),
];
