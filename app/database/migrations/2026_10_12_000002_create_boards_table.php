<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * boards: доски. Принадлежат пользователю (owner_user_id -> users.user_id).
 *
 *   board_id         SERIAL PRIMARY KEY
 *   title            VARCHAR(255) NOT NULL
 *   owner_user_id    INT NOT NULL
 *   is_public        BOOLEAN NOT NULL DEFAULT false
 *   created_datetime TIMESTAMP NOT NULL
 *   updated_datetime TIMESTAMP DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create('boards', function (Blueprint $table) {
            $table->increments('board_id');
            $table->string('title', 255);
            $table->integer('owner_user_id');
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_datetime')->useCurrent();
            $table->timestamp('updated_datetime')->nullable();

            // удаляем пользователя - удаляются и его доски
            $table->foreign('owner_user_id')->references('user_id')->on('users')->cascadeOnDelete();
            $table->index('owner_user_id', 'idx_boards_owner_user_id');
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('boards');
    }
};
