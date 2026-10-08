<?php

declare(strict_types=1);

return [
    'gotenberg' => [
        'url' => env('GOTENBERG_URL'),
        'basic_auth_username' => env('GOTENBERG_BASIC_AUTH_USERNAME'),
        'basic_auth_password' => env('GOTENBERG_BASIC_AUTH_PASSWORD'),
    ],

    // Camjo: read-only Google Calendar connection, shown in the calendar view.
    // The feature is off while no client id is set.
    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        // Only accounts of this Google Workspace domain can connect (e.g. "camjo.se"); empty = any
        'hosted_domain' => env('GOOGLE_CALENDAR_HOSTED_DOMAIN'),
    ],
];
