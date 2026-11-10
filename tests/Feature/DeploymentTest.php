<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** What the Render deploy relies on: creating the first login without a shell, and HTTPS behind a proxy. */
class DeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('TEST_ADMIN_PASSWORD');
        parent::tearDown();
    }

    public function test_user_create_reads_the_password_from_an_environment_variable(): void
    {
        putenv('TEST_ADMIN_PASSWORD=s3cret-pass');

        $this->artisan('user:create', ['name' => 'Admin', 'email' => 'admin@example.com', '--password-env' => 'TEST_ADMIN_PASSWORD'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.com')->sole();
        $this->assertTrue(Hash::check('s3cret-pass', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_skip_existing_leaves_an_account_untouched_on_later_deploys(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'original-pass']);
        putenv('TEST_ADMIN_PASSWORD=another-pass');

        $this->artisan('user:create', ['name' => 'Admin', 'email' => 'admin@example.com', '--password-env' => 'TEST_ADMIN_PASSWORD', '--skip-existing' => true])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('original-pass', $user->fresh()->password));
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_missing_or_short_password_is_refused(): void
    {
        $this->artisan('user:create', ['name' => 'Admin', 'email' => 'admin@example.com', '--password-env' => 'TEST_ADMIN_PASSWORD'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_links_use_https_behind_the_hosting_proxy(): void
    {
        $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'haus-stock.onrender.com'])
            ->assertOk()
            ->assertSee('https://haus-stock.onrender.com/login', false);
    }

    public function test_the_health_check_answers(): void
    {
        $this->get('/up')->assertOk();
    }
}
