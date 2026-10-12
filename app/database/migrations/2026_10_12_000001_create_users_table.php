<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * users: пользователи приложения.
 *
 *   user_id          SERIAL PRIMARY KEY
 *   email            VARCHAR(255) NOT NULL, уникальный индекс idx_email
 *   password_hash    VARCHAR(255) NOT NULL  (только хэш, пароль не храним)
 *   name             VARCHAR(255) NOT NULL
 *   created_datetime TIMESTAMP NOT NULL
 *   updated_datetime TIMESTAMP DEFAULT NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Capsule::schema()->create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('email', 255);
            $table->string('password_hash', 255);
            $table->string('name', 255);
            $table->timestamp('created_datetime')->useCurrent();
            $table->timestamp('updated_datetime')->nullable();
        });

        Capsule::connection()->statement('CREATE UNIQUE INDEX idx_email ON users (email)');
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('users');
    }
};
