<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\GoogleCalendar;

use App\Http\Requests\V1\BaseFormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class GoogleCalendarEventsRequest extends BaseFormRequest
{
    /**
     * At most two months per request, enough for the month view with its overflow weeks.
     */
    public const int MAX_RANGE_DAYS = 62;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string|ValidationRule|\Closure>>
     */
    public function rules(): array
    {
        return [
            // Start of the range (Format: "Y-m-d\TH:i:s\Z", UTC timezone)
            'start' => [
                'required',
                'date_format:Y-m-d\TH:i:s\Z',
            ],
            // End of the range (Format: "Y-m-d\TH:i:s\Z", UTC timezone), at most 62 days after start
            'end' => [
                'required',
                'date_format:Y-m-d\TH:i:s\Z',
                'after:start',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $start = $this->input('start');
                    if (is_string($start) && is_string($value)
                        && Carbon::parse($start)->diffInDays(Carbon::parse($value)) > self::MAX_RANGE_DAYS) {
                        $fail('The range can be at most '.self::MAX_RANGE_DAYS.' days.');
                    }
                },
            ],
        ];
    }

    public function getStart(): Carbon
    {
        return Carbon::parse((string) $this->input('start'));
    }

    public function getEnd(): Carbon
    {
        return Carbon::parse((string) $this->input('end'));
    }
}
