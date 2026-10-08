<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Api\V1\GoogleCalendarController;
use App\Http\Controllers\Web\GoogleCalendarController as WebGoogleCalendarController;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use App\Service\GoogleCalendarService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(GoogleCalendarController::class)]
#[UsesClass(WebGoogleCalendarController::class)]
#[UsesClass(GoogleCalendarService::class)]
class GoogleCalendarEndpointTest extends ApiEndpointTestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google_calendar.client_id' => 'client-id',
            'services.google_calendar.client_secret' => 'client-secret',
            'services.google_calendar.hosted_domain' => null,
        ]);
    }

    private function connect(User $user, ?Carbon $expiresAt = null): GoogleCalendarConnection
    {
        $connection = new GoogleCalendarConnection;
        $connection->user_id = $user->getKey();
        $connection->google_email = 'person@camjo.se';
        $connection->refresh_token = 'refresh-token';
        $connection->access_token = 'access-token';
        $connection->access_token_expires_at = $expiresAt ?? Carbon::now()->addHour();
        $connection->save();

        return $connection;
    }

    /**
     * @return array<string, string>
     */
    private function range(): array
    {
        return ['start' => '2026-10-05T00:00:00Z', 'end' => '2026-10-12T00:00:00Z'];
    }

    public function test_status_reports_disabled_when_not_configured(): void
    {
        config(['services.google_calendar.client_id' => null]);
        $data = $this->createUserWithRole(Role::Employee);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.show'));

        $response->assertSuccessful();
        $response->assertExactJson(['data' => ['enabled' => false, 'connected' => false, 'email' => null]]);
    }

    public function test_status_reports_connection_of_current_user(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $this->connect($data->user);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.show'));

        $response->assertExactJson(['data' => ['enabled' => true, 'connected' => true, 'email' => 'person@camjo.se']]);
    }

    public function test_events_are_empty_without_connection(): void
    {
        Http::fake();
        $data = $this->createUserWithRole(Role::Employee);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.events', $this->range()));

        $response->assertSuccessful();
        $response->assertExactJson(['data' => []]);
        Http::assertNothingSent();
    }

    public function test_events_are_mapped_and_all_day_cancelled_and_declined_events_are_left_out(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $this->connect($data->user);
        Http::fake([
            'www.googleapis.com/calendar/*' => Http::response(['items' => [
                [
                    'id' => 'meeting',
                    'summary' => 'Internmöte',
                    'htmlLink' => 'https://calendar.google.com/event?eid=1',
                    'start' => ['dateTime' => '2026-10-06T10:00:00+02:00'],
                    'end' => ['dateTime' => '2026-10-06T11:00:00+02:00'],
                ],
                ['id' => 'all-day', 'summary' => 'Ledig', 'start' => ['date' => '2026-10-07'], 'end' => ['date' => '2026-10-08']],
                [
                    'id' => 'cancelled', 'status' => 'cancelled',
                    'start' => ['dateTime' => '2026-10-06T12:00:00Z'], 'end' => ['dateTime' => '2026-10-06T13:00:00Z'],
                ],
                [
                    'id' => 'declined', 'summary' => 'Nej tack',
                    'start' => ['dateTime' => '2026-10-06T14:00:00Z'], 'end' => ['dateTime' => '2026-10-06T15:00:00Z'],
                    'attendees' => [['email' => 'person@camjo.se', 'self' => true, 'responseStatus' => 'declined']],
                ],
                ['id' => 'untitled', 'start' => ['dateTime' => '2026-10-08T08:00:00Z'], 'end' => ['dateTime' => '2026-10-08T08:30:00Z']],
            ]]),
        ]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.events', $this->range()));

        $response->assertSuccessful();
        $response->assertExactJson(['data' => [
            [
                'id' => 'meeting',
                'title' => 'Internmöte',
                'start' => '2026-10-06T08:00:00Z',
                'end' => '2026-10-06T09:00:00Z',
                'html_link' => 'https://calendar.google.com/event?eid=1',
            ],
            [
                'id' => 'untitled',
                'title' => '(No title)',
                'start' => '2026-10-08T08:00:00Z',
                'end' => '2026-10-08T08:30:00Z',
                'html_link' => null,
            ],
        ]]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'timeMin=2026-10-05T00%3A00%3A00')
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_expired_access_token_is_refreshed(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $connection = $this->connect($data->user, Carbon::now()->subMinute());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-access-token', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/*' => Http::response(['items' => []]),
        ]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.events', $this->range()));

        $response->assertSuccessful();
        $this->assertSame('new-access-token', $connection->refresh()->access_token);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'calendar/v3')
            && $request->hasHeader('Authorization', 'Bearer new-access-token'));
    }

    public function test_revoked_consent_removes_connection_and_asks_to_reconnect(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $connection = $this->connect($data->user, Carbon::now()->subMinute());
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.events', $this->range()));

        $response->assertStatus(400);
        $response->assertJsonPath('key', 'google_calendar_reconnect_required');
        $this->assertDatabaseMissing('google_calendar_connections', ['id' => $connection->getKey()]);
    }

    public function test_events_use_only_the_current_users_connection(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $otherUser = User::factory()->create();
        $this->connect($otherUser);
        Http::fake();
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.events', $this->range()));

        $response->assertExactJson(['data' => []]);
        Http::assertNothingSent();
    }

    public function test_events_range_is_limited(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.google-calendar.events', [
            'start' => '2026-01-01T00:00:00Z',
            'end' => '2026-06-01T00:00:00Z',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end']);
    }

    // Web OAuth flow

    public function test_connect_redirects_to_google_with_state(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $this->actingAs($data->user);

        $response = $this->get(route('google-calendar.connect'));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(GoogleCalendarService::AUTHORIZATION_URL, $location);
        $this->assertStringContainsString('calendar.events.readonly', urldecode($location));
        $this->assertStringContainsString('state='.session('google_calendar_oauth_state'), $location);
    }

    public function test_callback_with_wrong_state_is_rejected(): void
    {
        Http::fake();
        $data = $this->createUserWithRole(Role::Employee);
        $this->actingAs($data->user);

        $response = $this->withSession(['google_calendar_oauth_state' => 'expected'])
            ->get(route('google-calendar.callback', ['state' => 'forged', 'code' => 'code']));

        $response->assertRedirect(route('profile.show').'?google_calendar=error#google-calendar');
        Http::assertNothingSent();
        $this->assertDatabaseCount('google_calendar_connections', 0);
    }

    public function test_callback_stores_connection(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600,
            ]),
            'openidconnect.googleapis.com/*' => Http::response(['email' => 'person@camjo.se', 'hd' => 'camjo.se']),
        ]);
        $this->actingAs($data->user);

        $response = $this->withSession(['google_calendar_oauth_state' => 'state'])
            ->get(route('google-calendar.callback', ['state' => 'state', 'code' => 'code']));

        $response->assertRedirect(route('profile.show').'?google_calendar=connected#google-calendar');
        /** @var GoogleCalendarConnection $connection */
        $connection = GoogleCalendarConnection::query()->where('user_id', $data->user->getKey())->firstOrFail();
        $this->assertSame('person@camjo.se', $connection->google_email);
        $this->assertSame('refresh-token', $connection->refresh_token);
        // Stored encrypted
        $this->assertDatabaseMissing('google_calendar_connections', ['refresh_token' => 'refresh-token']);
    }

    public function test_callback_rejects_account_outside_the_workspace_domain(): void
    {
        config(['services.google_calendar.hosted_domain' => 'camjo.se']);
        $data = $this->createUserWithRole(Role::Employee);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600,
            ]),
            'openidconnect.googleapis.com/*' => Http::response(['email' => 'someone@gmail.com']),
        ]);
        $this->actingAs($data->user);

        $response = $this->withSession(['google_calendar_oauth_state' => 'state'])
            ->get(route('google-calendar.callback', ['state' => 'state', 'code' => 'code']));

        $response->assertRedirect(route('profile.show').'?google_calendar=error#google-calendar');
        $this->assertDatabaseCount('google_calendar_connections', 0);
    }

    public function test_disconnect_revokes_and_removes_connection(): void
    {
        $data = $this->createUserWithRole(Role::Employee);
        $this->connect($data->user);
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);
        $this->actingAs($data->user);

        $response = $this->delete(route('google-calendar.disconnect'));

        $response->assertRedirect(route('profile.show').'?google_calendar=disconnected#google-calendar');
        $this->assertDatabaseCount('google_calendar_connections', 0);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'revoke')
            && $request['token'] === 'refresh-token');
    }
}
