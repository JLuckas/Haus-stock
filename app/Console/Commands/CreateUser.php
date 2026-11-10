<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Self-registration is disabled; staff accounts are created here. */
class CreateUser extends Command
{
    protected $signature = 'user:create {name : Full name} {email : Login e-mail}';

    protected $description = 'Create a staff account that can sign in to the stock dashboard';

    public function handle(): int
    {
        $data = [
            'name' => (string) $this->argument('name'),
            'email' => (string) $this->argument('email'),
            'password' => (string) $this->secret('Password (min. 8 characters)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create($data + ['email_verified_at' => now()]);

        $this->components->info("User {$user->email} created.");

        return self::SUCCESS;
    }
}
