<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal and HR profile information for an SSO account.
 *
 * Intentionally decoupled from the User (Account) model.
 * A UserProfile may not exist yet for newly-created accounts.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $full_name
 * @property string|null $date_of_birth (Y-m-d)
 * @property string|null $gender (male|female|other)
 * @property string|null $phone_number
 * @property string|null $contact_email
 * @property string|null $address
 * @property string|null $avatar_path
 * @property string|null $bio
 */
#[Fillable([
    'user_id',
    'full_name',
    'date_of_birth',
    'gender',
    'phone_number',
    'contact_email',
    'address',
    'avatar_path',
    'bio',
])]
class UserProfile extends Model
{
    /**
     * Get the SSO account this profile belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the display name, falling back to the account name.
     */
    public function getDisplayName(): string
    {
        return $this->full_name ?? $this->user->name;
    }

    /**
     * Return the effective avatar URL: uploaded file takes priority over
     * the social-provider avatar stored on the User model.
     */
    public function getEffectiveAvatarUrl(): ?string
    {
        if ($this->avatar_path) {
            return asset('storage/'.$this->avatar_path);
        }

        return $this->user->avatar_url;
    }
}
