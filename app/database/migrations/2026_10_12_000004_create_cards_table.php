<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * cards: карточки в колонках доски.
 *
 *   card_id          SERIAL PRIMARY KEY
 *   board_id         INT NOT NULL
 *   column_id        INT NOT NULL
 *   sort_index       SMALLINT NOT NULL  (порядок карточки внутри колонки)
 *   title            VARCHAR(255) NOT NULL
 *   content          TEXT DEFAULT NULL
 *   color            VARCHAR(6) DEFAULT NULL
 *   is_archived      BOOLEAN NOT NULL DEFAULT false
 *   created_datetime TIMESTAMP NOT NULL
 *   updated_datetime TIMESTAMP DEFAULT NULL
 *
 * Частичный индекс idx_active_board_id строится только по неархивным карточкам:
 * основной запрос "все живые карточки доски" ходит по маленькому индексу.
 */
return new class extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create('cards', function (Blueprint $table) {
            $table->increments('card_id');
            $table->integer('board_id');
            $table->integer('column_id');
            $table->smallInteger('sort_index');
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->string('color', 6)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamp('created_datetime')->useCurrent();
            $table->timestamp('updated_datetime')->nullable();

            $table->foreign('board_id')->references('board_id')->on('boards')->cascadeOnDelete();
            $table->foreign('column_id')->references('column_id')->on('board_columns')->cascadeOnDelete();
            $table->index(['column_id', 'sort_index'], 'idx_cards_column_sort');
        });

        Capsule::connection()->statement(
            'CREATE INDEX idx_active_board_id ON cards (board_id) WHERE is_archived = false'
        );
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('cards');
    }
};
