<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
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

    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'topic' => 'technical',
            'message' => "My instance won't connect after scanning the QR.",
        ], $overrides);
    }

    public function test_guest_can_view_the_contact_page(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee('Send message')
            ->assertSee(config('mail.support_address'));
    }

    public function test_logged_in_users_name_and_email_are_prefilled(): void
    {
        $user = User::factory()->create(['name' => 'Prefilled Pat', 'email' => 'pat@example.com']);

        $this->actingAs($user)->get(route('contact'))
            ->assertOk()
            ->assertSee('value="Prefilled Pat"', escape: false)
            ->assertSee('value="pat@example.com"', escape: false);
    }

    public function test_topic_can_be_preselected_from_the_url(): void
    {
        $this->get(route('contact', ['topic' => 'privacy']))
            ->assertOk()
            ->assertSee('value="privacy" class="ct-topic-input" required checked', escape: false);
    }

    public function test_sending_emails_support_with_reply_to_the_sender(): void
    {
        Mail::fake();

        $this->post(route('contact.send'), $this->validForm())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo(config('mail.support_address'))
                && $mail->hasReplyTo('jane@example.com', 'Jane Doe')
                && $mail->topic === 'Technical support'
                && $mail->messageText === "My instance won't connect after scanning the QR."
                && $mail->userId === null;
        });
    }

    public function test_email_subject_uses_the_fixed_topic_label_and_body_has_the_details(): void
    {
        $mail = new ContactMessage('Jane Doe', 'jane@example.com', 'Billing & payments', "It's about my invoice.", 7);

        $mail->assertHasSubject('[Contact] Billing & payments');
        $mail->assertSeeInText('Jane Doe <jane@example.com>');
        $mail->assertSeeInText("It's about my invoice."); // apostrophe not turned into &#039;
        $mail->assertSeeInText('registered user #7');
    }

    public function test_logged_in_senders_user_id_is_included(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contact.send'), $this->validForm());

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => $mail->userId === $user->id);
    }

    public function test_validation_errors(): void
    {
        Mail::fake();

        $this->post(route('contact.send'), $this->validForm([
            'name' => '',
            'email' => 'not-an-email',
            'topic' => 'made-up-topic',
            'message' => 'short',
        ]))->assertSessionHasErrors(['name', 'email', 'topic', 'message']);

        Mail::assertNothingSent();
    }

    public function test_honeypot_submissions_are_silently_dropped(): void
    {
        Mail::fake();

        $this->post(route('contact.send'), $this->validForm(['website' => 'http://spam.example']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_is_limited_to_five_messages_per_ten_minutes(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.send'), $this->validForm())->assertRedirect();
        }

        $this->post(route('contact.send'), $this->validForm())->assertStatus(429);
        Mail::assertSentCount(5);
    }

    public function test_a_mail_failure_shows_a_friendly_error_and_keeps_the_input(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->from(route('contact'))
            ->post(route('contact.send'), $this->validForm())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('error')
            ->assertSessionHasInput('message');
    }

    public function test_footer_links_to_the_contact_page(): void
    {
        $this->get('/')->assertSee(route('contact'), escape: false);
    }
}
