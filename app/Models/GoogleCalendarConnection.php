<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's read-only connection to their Google Calendar. Tokens are encrypted with APP_KEY.
 *
 * @property string $id
 * @property string $user_id
 * @property string $google_email
 * @property string $refresh_token
 * @property string|null $access_token
 * @property Carbon|null $access_token_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class GoogleCalendarConnection extends Model
{
    use HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'refresh_token' => 'encrypted',
        'access_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'refresh_token',
        'access_token',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
