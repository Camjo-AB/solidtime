<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\Api\ApiException;
use App\Models\User;
use App\Service\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connects a user's Google Calendar (OAuth consent in the browser). The result is shown on the
 * profile page via the `google_calendar` query parameter.
 */
class GoogleCalendarController extends Controller
{
    private const string SESSION_STATE_KEY = 'google_calendar_oauth_state';

    public function connect(Request $request, GoogleCalendarService $googleCalendarService): RedirectResponse
    {
        if (! $googleCalendarService->isEnabled()) {
            return $this->toProfile('not_configured');
        }
        $state = Str::random(40);
        $request->session()->put(self::SESSION_STATE_KEY, $state);

        return redirect()->away($googleCalendarService->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleCalendarService $googleCalendarService): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::SESSION_STATE_KEY);
        $state = $request->query('state');
        if (! is_string($expectedState) || ! is_string($state) || ! hash_equals($expectedState, $state)) {
            return $this->toProfile('error');
        }
        $code = $request->query('code');
        if ($request->query('error') !== null || ! is_string($code) || $code === '') {
            // e.g. the user clicked "Cancel" on Google's consent screen
            return $this->toProfile('cancelled');
        }

        /** @var User $user */
        $user = $request->user();
        try {
            $googleCalendarService->connect($user, $code);
        } catch (ApiException $exception) {
            Log::info('Connecting Google Calendar failed', ['key' => $exception->getKey(), 'user_id' => $user->getKey()]);

            return $this->toProfile('error');
        }

        return $this->toProfile('connected');
    }

    public function disconnect(Request $request, GoogleCalendarService $googleCalendarService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $connection = $user->googleCalendarConnection()->first();
        if ($connection !== null) {
            $googleCalendarService->disconnect($connection);
        }

        return $this->toProfile('disconnected');
    }

    private function toProfile(string $result): RedirectResponse
    {
        return redirect()->to(route('profile.show').'?google_calendar='.$result.'#google-calendar');
    }
}
