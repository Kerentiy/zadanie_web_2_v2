<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;
use Psr\Log\LoggerInterface;

/**
 * Небольшой JSON API поверх Eloquent-модели User:
 *   GET /users, GET /users/{id}, POST /users
 */
final class UserApi
{
    public function __construct(private readonly LoggerInterface $log)
    {
    }

    /** @return bool true, если маршрут обработан */
    public function handle(string $method, string $path): bool
    {
        // GET /users - список
        if ($method === 'GET' && $path === '/users') {
            Json::response(['data' => User::query()->orderByDesc('user_id')->limit(50)->get()]);
            return true;
        }

        // GET /users/{id}
        if ($method === 'GET' && preg_match('#^/users/(\d+)$#', $path, $m) === 1) {
            $user = User::find((int) $m[1]);
            $user
                ? Json::response(['data' => $user])
                : Json::response(['error' => 'User not found'], 404);
            return true;
        }

        // POST /users {"name": "...", "email": "...", "password": "..."}
        if ($method === 'POST' && $path === '/users') {
            $this->create();
            return true;
        }

        return false;
    }

    private function create(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            Json::response([
                'error' => 'Invalid JSON body: ' . json_last_error_msg(),
                'hint' => 'The body must be valid UTF-8 JSON (on Windows send it from a file, use \uXXXX escapes or run `chcp 65001` first)',
            ], 400);
            return;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Required';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required';
        } elseif (User::where('email', $email)->exists()) {
            $errors['email'] = 'Already taken';
        }
        // bcrypt (PASSWORD_DEFAULT) учитывает только первые 72 байта пароля
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors['password'] = 'From 8 to 72 bytes';
        }
        if ($errors !== []) {
            Json::response(['errors' => $errors], 422);
            return;
        }

        $user = new User(['name' => $name, 'email' => $email]);
        $user->setPassword($password); // в БД попадает только хэш
        $user->save();
        $this->log->info('User created', ['user_id' => $user->user_id]);
        Json::response(['data' => $user], 201);
    }
}
