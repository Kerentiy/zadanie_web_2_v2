<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Таблица board_columns (в таблице нет колонок created/updated).
 *
 * @property int $column_id
 * @property int $board_id
 * @property int $ordinal_position
 * @property string $title
 * @property string|null $color
 */
class BoardColumn extends Model
{
    protected $table = 'board_columns';
    protected $primaryKey = 'column_id';

    public $timestamps = false;

    protected $fillable = ['board_id', 'ordinal_position', 'title', 'color'];

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class, 'board_id', 'board_id');
    }

    /** Карточки колонки по порядку. @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'column_id', 'column_id')->orderBy('sort_index');
    }
}
