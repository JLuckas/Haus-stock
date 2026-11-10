<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Self-registration is disabled; staff accounts are created here.
 *
 *   php artisan user:create "Maria Souza" maria@example.com          (asks for the password)
 *   php artisan user:create Admin admin@example.com --password-env=ADMIN_PASSWORD --skip-existing
 *
 * The second form runs on deploy (docker/entrypoint.sh) to create the first login without a shell.
 */
class CreateUser extends Command
{
    protected $signature = 'user:create
        {name : Full name}
        {email : Login e-mail}
        {--password-env= : Read the password from this environment variable instead of asking}
        {--skip-existing : Do nothing (and succeed) when the e-mail already has an account}';

    protected $description = 'Create a staff account that can sign in to the stock dashboard';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if ($this->option('skip-existing') && User::query()->where('email', $email)->exists()) {
            $this->components->info("User {$email} already exists, nothing to do.");

            return self::SUCCESS;
        }

        $data = [
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => $this->password(),
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

        // email_verified_at isn't mass-assignable, so set it explicitly: accounts made here are trusted.
        $user = User::query()->make($data)->forceFill(['email_verified_at' => now()]);
        $user->save();

        $this->components->info("User {$user->email} created.");

        return self::SUCCESS;
    }

    private function password(): string
    {
        $variable = $this->option('password-env');

        if ($variable) {
            return (string) (getenv($variable) ?: ($_ENV[$variable] ?? $_SERVER[$variable] ?? ''));
        }

        return (string) $this->secret('Password (min. 8 characters)');
    }
}
