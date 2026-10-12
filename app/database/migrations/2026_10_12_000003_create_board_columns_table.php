<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * board_columns: колонки доски (например "To do", "In progress", "Done").
 *
 *   column_id         SERIAL PRIMARY KEY
 *   board_id          INT NOT NULL, индекс idx_board_id
 *   ordinal_position  SMALLINT NOT NULL  (порядок колонки слева направо)
 *   title             VARCHAR(255) NOT NULL
 *   color             VARCHAR(6) DEFAULT NULL  (HEX без "#", например "ff8800")
 */
return new class extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create('board_columns', function (Blueprint $table) {
            $table->increments('column_id');
            $table->integer('board_id');
            $table->smallInteger('ordinal_position');
            $table->string('title', 255);
            $table->string('color', 6)->nullable();

            // удаляем доску - удаляются и её колонки
            $table->foreign('board_id')->references('board_id')->on('boards')->cascadeOnDelete();
            $table->index('board_id', 'idx_board_id');
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('board_columns');
    }
};
