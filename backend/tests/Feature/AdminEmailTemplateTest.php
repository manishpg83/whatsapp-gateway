<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\EmailTemplateTest;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\WelcomeUser;
use App\Services\EmailTemplates;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('whatsapp_gateway_test', DB::connection()->getDatabaseName());
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'Admin Alex']);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Your {plan_name} plan is cancelled',
            'body' => '<p>Hello {name}, your {plan_name} plan has ended.</p>',
            'button_text' => 'Open Billing',
        ], $overrides);
    }

    // --- Access ---------------------------------------------------------

    public function test_guests_and_regular_users_cannot_open_the_pages(): void
    {
        $this->get('/admin/email-templates')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/email-templates')->assertNotFound();
        $this->actingAs($user)->get('/admin/email-templates/welcome/edit')->assertNotFound();
        $this->actingAs($user)->post('/admin/email-templates/welcome', $this->form())->assertNotFound();
        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_admin_sees_every_template_and_can_open_the_editor(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/email-templates')->assertOk();
        foreach (EmailTemplates::definitions() as $template) {
            $response->assertSee($template['label']);
        }

        $this->actingAs($admin)->get('/admin/email-templates/welcome/edit')
            ->assertOk()
            ->assertSee('Welcome aboard, {name}!', escape: false)
            ->assertSee('{plan_name}');
    }

    public function test_an_unknown_template_is_a_404(): void
    {
        $this->actingAs($this->admin())->get('/admin/email-templates/nope/edit')->assertNotFound();
    }

    // --- Saving and resetting --------------------------------------------

    public function test_saving_stores_the_template_logs_it_and_the_next_email_uses_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/email-templates/subscription_cancelled', $this->form())
            ->assertRedirect(route('admin.email-templates.edit', 'subscription_cancelled'));

        $saved = EmailTemplate::where('key', 'subscription_cancelled')->sole();
        $this->assertSame($admin->id, $saved->updated_by);

        $log = AdminAuditLog::sole();
        $this->assertSame('email_template.updated', $log->action);
        $this->assertSame('email_template', $log->target_type);
        $this->assertSame('Subscription cancelled', $log->target_label);

        $user = User::factory()->create(['name' => 'Asha']);
        $mail = (new SubscriptionCancelled('Starter'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Your Starter plan is cancelled', $mail->subject);
        $this->assertStringContainsString('Hello Asha, your Starter plan has ended.', $html);
        $this->assertStringContainsString('Open Billing', $html);
        $this->assertSame(route('billing.index'), $mail->actionUrl);
    }

    public function test_reset_deletes_the_edited_version_and_the_default_is_used_again(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/email-templates/subscription_cancelled', $this->form());

        $this->actingAs($admin)->delete('/admin/email-templates/subscription_cancelled')
            ->assertRedirect(route('admin.email-templates.edit', 'subscription_cancelled'));

        $this->assertDatabaseCount('email_templates', 0);
        $this->assertSame('email_template.reset', AdminAuditLog::latest('id')->first()->action);

        $mail = (new SubscriptionCancelled('Starter'))->toMail(User::factory()->create());
        $this->assertSame('Your Starter subscription has been cancelled', $mail->subject);
    }

    public function test_an_unknown_placeholder_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/email-templates/subscription_cancelled', $this->form(['body' => '<p>Hi {nmae}</p>']))
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_an_empty_body_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/email-templates/subscription_cancelled', $this->form(['body' => '<p><br></p>']))
            ->assertSessionHasErrors('body');
    }

    // --- Safety -----------------------------------------------------------

    public function test_unsafe_html_is_removed_when_saving(): void
    {
        $this->actingAs($this->admin())->post('/admin/email-templates/subscription_cancelled', $this->form([
            'body' => '<p onclick="steal()">Hi <script>alert(1)</script><a href="javascript:alert(1)">click</a> '
                .'<a href="{billing_url}" target="_blank">billing</a> <img src="x"><span style="color:red">kept</span></p>',
        ]));

        $this->assertSame(
            '<p>Hi click <a href="{billing_url}">billing</a> kept</p>',
            EmailTemplate::where('key', 'subscription_cancelled')->sole()->body,
        );
    }

    public function test_values_are_escaped_and_never_run_as_code(): void
    {
        EmailTemplate::create([
            'key' => 'subscription_cancelled',
            'subject' => 'Hi',
            'body' => '<p>Hi {name} {{ 7*7 }} @php echo "x"; @endphp</p>',
            'button_text' => 'Go',
        ]);

        $user = User::factory()->create(['name' => '<b>Eve</b>']);
        $html = (string) (new SubscriptionCancelled('Starter'))->toMail($user)->render();

        $this->assertStringContainsString('Hi &lt;b&gt;Eve&lt;/b&gt; {{ 7*7 }} @php echo', $html);
        $this->assertStringNotContainsString('<b>Eve</b>', $html);
        $this->assertStringNotContainsString('49', strip_tags($html));
    }

    // --- Layout -------------------------------------------------------------

    public function test_the_button_goes_where_the_button_placeholder_is_or_at_the_end(): void
    {
        $build = fn (string $body) => (string) EmailTemplates::build(
            ['subject' => 'S', 'body' => $body, 'button_text' => 'Press'],
            ['name' => 'A'],
            'https://example.com/go',
        )->render();

        $html = $build('<p>First</p><p>{button}</p><p>Last</p>');
        $this->assertLessThan(strpos($html, 'Last'), strpos($html, '>Press<'));
        $this->assertGreaterThan(strpos($html, 'First'), strpos($html, '>Press<'));

        $html = $build('<p>First</p><p>Last</p>');
        $this->assertGreaterThan(strpos($html, 'Last'), strpos($html, '>Press<'));
    }

    public function test_emails_use_the_branded_layout_with_the_logo(): void
    {
        $html = (string) (new WelcomeUser)->toMail(User::factory()->create())->render();

        $this->assertStringContainsString('data:image/png;base64', $html); // embedded logo
        $this->assertStringContainsString('BriskBrain Technologies', $html);
    }

    // --- Laravel's built-in emails ----------------------------------------

    public function test_verify_and_reset_emails_use_the_templates(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Jane']);

        $verify = (new VerifyEmail)->toMail($user);
        $this->assertSame('Verify your email address', $verify->subject);
        $this->assertStringContainsString('Confirm your email, Jane', (string) $verify->render());
        $this->assertStringContainsString('/email/verify/', $verify->actionUrl);

        $reset = (new ResetPassword('tok123'))->toMail($user);
        $this->assertSame('Reset your password', $reset->subject);
        $this->assertStringContainsString('/reset-password/tok123', $reset->actionUrl);
    }

    // --- Preview and test email -------------------------------------------

    public function test_preview_renders_unsaved_content_with_sample_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/email-templates/subscription_cancelled/preview', $this->form())
            ->assertOk()
            ->assertSee('Hello Admin Alex, your Starter plan has ended.');

        $this->actingAs($admin)->post('/admin/email-templates/subscription_cancelled/preview', $this->form(['body' => '<p>{oops}</p>']))
            ->assertStatus(422)
            ->assertSee('Unknown placeholder');

        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_send_test_emails_only_the_admin_and_saves_nothing(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.email-templates.edit', 'subscription_cancelled'))
            ->post('/admin/email-templates/subscription_cancelled/test', $this->form())
            ->assertRedirect(route('admin.email-templates.edit', 'subscription_cancelled'))
            ->assertSessionHas('status');

        Notification::assertSentOnDemand(EmailTemplateTest::class, function (EmailTemplateTest $notification, array $channels, AnonymousNotifiable $notifiable) use ($admin) {
            return $notifiable->routes['mail'] === $admin->email
                && $notification->mail->subject === '[Test] Your Starter plan is cancelled';
        });
        $this->assertDatabaseCount('email_templates', 0);
    }
}
