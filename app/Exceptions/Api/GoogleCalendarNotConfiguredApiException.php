<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

class GoogleCalendarNotConfiguredApiException extends ApiException
{
    public const string KEY = 'google_calendar_not_configured';
}
