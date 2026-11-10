<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Issues a Sanctum token for the JSON API, e.g. for a spreadsheet or BI tool:
 *
 *   php artisan api:token admin@example.com --name="Power BI"
 *   curl -H "Authorization: Bearer <token>" -H "Accept: application/json" https://host/api/reports/profitability
 */
class CreateApiToken extends Command
{
    protected $signature = 'api:token
        {email : The user the token acts as}
        {--name=api : Label to recognise the token later}
        {--revoke : Revoke all of this user\'s tokens with that name instead of creating one}';

    protected $description = 'Create (or revoke) an API token for the JSON endpoints';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error("No user with e-mail {$this->argument('email')}.");

            return self::FAILURE;
        }

        $name = (string) $this->option('name');

        if ($this->option('revoke')) {
            $count = $user->tokens()->where('name', $name)->delete();
            $this->components->info("Revoked {$count} token(s) named \"{$name}\".");

            return self::SUCCESS;
        }

        $token = $user->createToken($name)->plainTextToken;

        $this->components->info("Token \"{$name}\" created for {$user->email}. It is shown only once:");
        $this->line($token);

        return self::SUCCESS;
    }
}
