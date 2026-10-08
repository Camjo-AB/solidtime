<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\Api\GoogleCalendarNotConfiguredApiException;
use App\Exceptions\Api\GoogleCalendarReconnectRequiredApiException;
use App\Exceptions\Api\GoogleCalendarRequestFailedApiException;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only access to a user's primary Google Calendar via Google's REST APIs.
 * Events are fetched on demand and never stored; only the OAuth tokens are kept (encrypted).
 */
class GoogleCalendarService
{
    public const string AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const string REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public const string USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public const string EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public const string SCOPES = 'openid email https://www.googleapis.com/auth/calendar.events.readonly';

    private const int MAX_EVENT_PAGES = 10;

    public function isEnabled(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    /**
     * @throws GoogleCalendarNotConfiguredApiException
     */
    public function authorizationUrl(string $state): string
    {
        $this->ensureEnabled();

        $query = [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            // Always ask, so Google returns a refresh token also on a reconnect
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];
        if ($this->hostedDomain() !== null) {
            $query['hd'] = $this->hostedDomain();
        }

        return self::AUTHORIZATION_URL.'?'.http_build_query($query);
    }

    /**
     * Exchanges the authorization code from the callback and stores the connection.
     *
     * @throws GoogleCalendarNotConfiguredApiException
     * @throws GoogleCalendarRequestFailedApiException
     * @throws GoogleCalendarReconnectRequiredApiException
     */
    public function connect(User $user, string $code): GoogleCalendarConnection
    {
        $this->ensureEnabled();

        $token = $this->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        $accessToken = (string) $token->json('access_token');
        $userInfo = $this->get(self::USERINFO_URL, $accessToken);
        $email = (string) $userInfo->json('email');
        if ($this->hostedDomain() !== null && strcasecmp((string) $userInfo->json('hd'), $this->hostedDomain()) !== 0) {
            // The hd parameter only preselects the account; the claim is what proves it.
            throw new GoogleCalendarReconnectRequiredApiException;
        }

        /** @var GoogleCalendarConnection|null $connection */
        $connection = GoogleCalendarConnection::query()->where('user_id', $user->getKey())->first();
        $refreshToken = $token->json('refresh_token') ?? $connection?->refresh_token;
        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new GoogleCalendarReconnectRequiredApiException;
        }

        $connection ??= new GoogleCalendarConnection(['user_id' => $user->getKey()]);
        $connection->google_email = $email;
        $connection->refresh_token = $refreshToken;
        $connection->access_token = $accessToken;
        $connection->access_token_expires_at = Carbon::now()->addSeconds((int) $token->json('expires_in', 3600) - 60);
        $connection->save();

        return $connection;
    }

    /**
     * Timed events of the user's primary calendar between start and end. All-day, cancelled and
     * declined events are left out.
     *
     * @return list<array{id: string, title: string, start: string, end: string, html_link: string|null}>
     *
     * @throws GoogleCalendarReconnectRequiredApiException
     * @throws GoogleCalendarRequestFailedApiException
     */
    public function events(GoogleCalendarConnection $connection, Carbon $start, Carbon $end): array
    {
        $accessToken = $this->accessToken($connection);

        $events = [];
        $pageToken = null;
        for ($page = 0; $page < self::MAX_EVENT_PAGES; $page++) {
            $query = [
                'timeMin' => $start->copy()->utc()->toRfc3339String(),
                'timeMax' => $end->copy()->utc()->toRfc3339String(),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 250,
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $response = $this->get(self::EVENTS_URL, $accessToken, $query, $connection);

            /** @var array<int, array<string, mixed>> $items */
            $items = $response->json('items', []);
            foreach ($items as $item) {
                $event = $this->mapEvent($item);
                if ($event !== null) {
                    $events[] = $event;
                }
            }

            $pageToken = $response->json('nextPageToken');
            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return $events;
    }

    /**
     * Revokes the token at Google (best effort) and removes the connection.
     */
    public function disconnect(GoogleCalendarConnection $connection): void
    {
        try {
            Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $connection->refresh_token]);
        } catch (ConnectionException $exception) {
            Log::warning('Could not revoke Google Calendar token', ['message' => $exception->getMessage()]);
        }
        $connection->delete();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: string, title: string, start: string, end: string, html_link: string|null}|null
     */
    private function mapEvent(array $item): ?array
    {
        if (($item['status'] ?? null) === 'cancelled') {
            return null;
        }
        /** @var array<string, mixed> $start */
        $start = $item['start'] ?? [];
        /** @var array<string, mixed> $end */
        $end = $item['end'] ?? [];
        // All-day events only have a date
        if (! isset($start['dateTime'], $end['dateTime'])) {
            return null;
        }
        /** @var array<int, array<string, mixed>> $attendees */
        $attendees = $item['attendees'] ?? [];
        foreach ($attendees as $attendee) {
            if (($attendee['self'] ?? false) === true && ($attendee['responseStatus'] ?? null) === 'declined') {
                return null;
            }
        }

        $summary = $item['summary'] ?? null;

        return [
            'id' => (string) ($item['id'] ?? ''),
            'title' => is_string($summary) && $summary !== '' ? $summary : '(No title)',
            'start' => Carbon::parse((string) $start['dateTime'])->utc()->toIso8601ZuluString(),
            'end' => Carbon::parse((string) $end['dateTime'])->utc()->toIso8601ZuluString(),
            'html_link' => isset($item['htmlLink']) ? (string) $item['htmlLink'] : null,
        ];
    }

    /**
     * @throws GoogleCalendarReconnectRequiredApiException
     * @throws GoogleCalendarRequestFailedApiException
     */
    private function accessToken(GoogleCalendarConnection $connection): string
    {
        if ($connection->access_token !== null && $connection->access_token_expires_at !== null
            && $connection->access_token_expires_at->isFuture()) {
            return $connection->access_token;
        }

        $token = $this->post(self::TOKEN_URL, [
            'refresh_token' => $connection->refresh_token,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type' => 'refresh_token',
        ], $connection);

        $connection->access_token = (string) $token->json('access_token');
        $connection->access_token_expires_at = Carbon::now()->addSeconds((int) $token->json('expires_in', 3600) - 60);
        $connection->save();

        return $connection->access_token;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws GoogleCalendarReconnectRequiredApiException
     * @throws GoogleCalendarRequestFailedApiException
     */
    private function post(string $url, array $data, ?GoogleCalendarConnection $connection = null): Response
    {
        try {
            $response = Http::asForm()->timeout(15)->post($url, $data);
        } catch (ConnectionException) {
            throw new GoogleCalendarRequestFailedApiException;
        }

        return $this->checkResponse($response, $connection);
    }

    /**
     * @param  array<string, mixed>  $query
     *
     * @throws GoogleCalendarReconnectRequiredApiException
     * @throws GoogleCalendarRequestFailedApiException
     */
    private function get(string $url, string $accessToken, array $query = [], ?GoogleCalendarConnection $connection = null): Response
    {
        try {
            $response = Http::withToken($accessToken)->timeout(15)->get($url, $query);
        } catch (ConnectionException) {
            throw new GoogleCalendarRequestFailedApiException;
        }

        return $this->checkResponse($response, $connection);
    }

    /**
     * @throws GoogleCalendarReconnectRequiredApiException
     * @throws GoogleCalendarRequestFailedApiException
     */
    private function checkResponse(Response $response, ?GoogleCalendarConnection $connection): Response
    {
        if ($response->successful()) {
            return $response;
        }
        // Revoked or expired consent: the stored connection is useless, the user has to connect again
        if ($response->json('error') === 'invalid_grant' || $response->status() === 401) {
            $connection?->delete();
            throw new GoogleCalendarReconnectRequiredApiException;
        }
        Log::warning('Google Calendar request failed', ['status' => $response->status()]);

        throw new GoogleCalendarRequestFailedApiException;
    }

    /**
     * @throws GoogleCalendarNotConfiguredApiException
     */
    private function ensureEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw new GoogleCalendarNotConfiguredApiException;
        }
    }

    private function redirectUri(): string
    {
        return route('google-calendar.callback');
    }

    private function clientId(): ?string
    {
        $value = config('services.google_calendar.client_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function clientSecret(): ?string
    {
        $value = config('services.google_calendar.client_secret');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function hostedDomain(): ?string
    {
        $value = config('services.google_calendar.hosted_domain');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
