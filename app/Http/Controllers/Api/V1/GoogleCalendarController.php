<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\GoogleCalendarReconnectRequiredApiException;
use App\Exceptions\Api\GoogleCalendarRequestFailedApiException;
use App\Http\Requests\V1\GoogleCalendar\GoogleCalendarEventsRequest;
use App\Service\GoogleCalendarService;

/**
 * Camjo: the current user's Google Calendar, shown read-only in the calendar view.
 * Connecting and disconnecting happens on the profile page (web OAuth flow).
 */
class GoogleCalendarController extends Controller
{
    /**
     * Get my Google Calendar connection
     *
     * @return array{data: array{enabled: bool, connected: bool, email: string|null}}
     *
     * @operationId getMyGoogleCalendar
     */
    public function show(GoogleCalendarService $googleCalendarService): array
    {
        $connection = $this->user()->googleCalendarConnection()->first();

        return [
            'data' => [
                'enabled' => $googleCalendarService->isEnabled(),
                'connected' => $googleCalendarService->isEnabled() && $connection !== null,
                'email' => $connection?->google_email,
            ],
        ];
    }

    /**
     * Get my Google Calendar events
     *
     * Timed events of the primary calendar in the range; all-day, cancelled and declined events are
     * left out. Returns an empty list when no calendar is connected.
     *
     * @return array{data: list<array{id: string, title: string, start: string, end: string, html_link: string|null}>}
     *
     * @throws GoogleCalendarReconnectRequiredApiException
     * @throws GoogleCalendarRequestFailedApiException
     *
     * @operationId getMyGoogleCalendarEvents
     */
    public function events(GoogleCalendarEventsRequest $request, GoogleCalendarService $googleCalendarService): array
    {
        $connection = $this->user()->googleCalendarConnection()->first();
        if ($connection === null || ! $googleCalendarService->isEnabled()) {
            return ['data' => []];
        }

        return [
            'data' => $googleCalendarService->events($connection, $request->getStart(), $request->getEnd()),
        ];
    }
}
