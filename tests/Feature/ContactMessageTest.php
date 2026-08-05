<?php

namespace Tests\Feature;

use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The route is throttled per IP. Without clearing it, whichever test
        // runs sixteenth gets a 429 and the failure looks unrelated to it.
        RateLimiter::clear('');
    }

    /** @return array<string, string> */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Hartono',
            'email' => 'rina@example.com',
            'phone' => '+62 21 555 0100',
            'subject' => 'Leasing enquiry',
            'message' => 'We are looking for 400 sqm of office space from Q1 next year.',
            'locale' => 'id',
        ], $overrides);
    }

    public function test_a_valid_enquiry_is_stored(): void
    {
        $this->post('/contact', $this->valid())->assertRedirect();

        $message = ContactMessage::sole();

        $this->assertSame('Rina Hartono', $message->name);
        $this->assertSame('rina@example.com', $message->email);
        $this->assertSame('Leasing enquiry', $message->subject);
        // The reader's language is kept so a reply can be written in it.
        $this->assertSame('id', $message->locale);
        $this->assertTrue($message->isUnread());
    }

    public function test_the_no_javascript_path_redirects_back_with_a_reference(): void
    {
        $response = $this->from('/contact-us')->post('/contact', $this->valid());

        $response->assertRedirect('/contact-us#enquire');
        $response->assertSessionHas('contact.sent', 'SCBD-00001');
    }

    public function test_a_json_request_gets_json_back_rather_than_a_redirect(): void
    {
        // This is what contact.js relies on. A redirect here is followed
        // silently by fetch, which then reads a whole HTML page as if it were
        // the answer — the form appears to do nothing at all.
        $response = $this->postJson('/contact', $this->valid());

        $response->assertOk();
        $response->assertJson(['ok' => true, 'reference' => 'SCBD-00001']);
    }

    public function test_a_json_validation_failure_returns_422_with_the_field_errors(): void
    {
        $response = $this->postJson('/contact', $this->valid(['email' => 'not-an-email', 'message' => '']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'message']);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_the_honeypot_rejects_the_submission(): void
    {
        $response = $this->postJson('/contact', $this->valid([
            StoreContactMessageRequest::TRAP => 'https://spam.example',
        ]));

        $response->assertStatus(422);
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_an_empty_honeypot_is_not_treated_as_spam(): void
    {
        // The field is present on every submission, empty. Rejecting a present
        // but empty trap would block every genuine enquiry.
        $this->postJson('/contact', $this->valid([StoreContactMessageRequest::TRAP => '']))
            ->assertOk();

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_a_short_message_is_rejected(): void
    {
        $this->postJson('/contact', $this->valid(['message' => 'hi']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_an_unknown_locale_falls_back_rather_than_being_stored(): void
    {
        // locale comes from a hidden input, so it is attacker-controlled.
        $this->postJson('/contact', $this->valid(['locale' => 'zz-XX']))->assertOk();

        $this->assertSame('en', ContactMessage::sole()->locale);
    }

    public function test_the_route_is_throttled(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->postJson('/contact', $this->valid())->assertOk();
        }

        $this->postJson('/contact', $this->valid())->assertStatus(429);
    }

    public function test_reading_a_message_marks_it_read_and_it_can_be_unread_again(): void
    {
        $message = ContactMessage::create($this->valid());

        $this->assertTrue($message->isUnread());

        $message->markAsRead();
        $this->assertFalse($message->fresh()->isUnread());

        $message->markAsUnread();
        $this->assertTrue($message->fresh()->isUnread());
    }
}
