<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Таблица cards.
 *
 * @property int $card_id
 * @property int $board_id
 * @property int $column_id
 * @property int $sort_index
 * @property string $title
 * @property string|null $content
 * @property string|null $color
 * @property bool $is_archived
 * @property \Illuminate\Support\Carbon $created_datetime
 * @property \Illuminate\Support\Carbon|null $updated_datetime
 */
class Card extends Model
{
    protected $table = 'cards';
    protected $primaryKey = 'card_id';

    public const CREATED_AT = 'created_datetime';
    public const UPDATED_AT = 'updated_datetime';

    protected $fillable = ['board_id', 'column_id', 'sort_index', 'title', 'content', 'color', 'is_archived'];

    protected $casts = ['is_archived' => 'boolean'];

    /** Только неархивные карточки (тот самый запрос, который ускоряет idx_active_board_id). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'board_id', 'board_id');
    }

    /** @return BelongsTo<BoardColumn, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'column_id', 'column_id');
    }
}
