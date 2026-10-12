<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Таблица users.
 *
 * @property int $user_id
 * @property string $email
 * @property string $password_hash
 * @property string $name
 * @property \Illuminate\Support\Carbon $created_datetime
 * @property \Illuminate\Support\Carbon|null $updated_datetime
 */
class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';

    public const CREATED_AT = 'created_datetime';
    public const UPDATED_AT = 'updated_datetime';

    protected $fillable = ['email', 'password_hash', 'name'];

    /** Хэш пароля никогда не попадает в JSON-ответы. */
    protected $hidden = ['password_hash'];

    /** Сохраняет хэш пароля (password_hash), сам пароль нигде не хранится. */
    public function setPassword(string $plain): void
    {
        $this->password_hash = password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->password_hash);
    }

    /** @return HasMany<Board, $this> */
    public function boards(): HasMany
    {
        return $this->hasMany(Board::class, 'owner_user_id', 'user_id');
    }
}
