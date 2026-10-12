<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Таблица boards.
 *
 * @property int $board_id
 * @property string $title
 * @property int $owner_user_id
 * @property bool $is_public
 * @property \Illuminate\Support\Carbon $created_datetime
 * @property \Illuminate\Support\Carbon|null $updated_datetime
 */
class Board extends Model
{
    protected $table = 'boards';
    protected $primaryKey = 'board_id';

    public const CREATED_AT = 'created_datetime';
    public const UPDATED_AT = 'updated_datetime';

    protected $fillable = ['title', 'owner_user_id', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id', 'user_id');
    }

    /** Колонки слева направо. @return HasMany<BoardColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class, 'board_id', 'board_id')->orderBy('ordinal_position');
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'board_id', 'board_id');
    }
}
