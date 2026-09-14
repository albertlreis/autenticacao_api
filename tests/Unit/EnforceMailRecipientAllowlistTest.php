<?php

namespace Tests\Unit;

use App\Listeners\EnforceMailRecipientAllowlist;
use Illuminate\Mail\Events\MessageSending;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class EnforceMailRecipientAllowlistTest extends TestCase
{
    public function test_allows_personal_address_and_ses_simulator(): void
    {
        config(['mail.recipient_allowlist' => [
            'enforce' => true,
            'addresses' => ['albertlimar4@gmail.com'],
            'allow_ses_simulator' => true,
        ]]);

        $message = (new Email)->to('albertlimar4@gmail.com')->cc('success@simulator.amazonses.com');
        app(EnforceMailRecipientAllowlist::class)->handle(new MessageSending($message, []));
        $this->assertTrue(true);
    }

    public function test_rejects_unlisted_to_cc_and_bcc_without_exposing_address(): void
    {
        config(['mail.recipient_allowlist' => [
            'enforce' => true,
            'addresses' => ['albertlimar4@gmail.com'],
            'allow_ses_simulator' => true,
        ]]);
        foreach (['to', 'cc', 'bcc'] as $field) {
            $message = new Email;
            $message->{$field}('blocked@example.com');
            try {
                app(EnforceMailRecipientAllowlist::class)->handle(new MessageSending($message, []));
                $this->fail("{$field} was accepted");
            } catch (RuntimeException $exception) {
                $this->assertStringNotContainsString('blocked@example.com', $exception->getMessage());
            }
        }
    }

    public function test_fails_closed_when_enforced_without_any_allowance(): void
    {
        config(['mail.recipient_allowlist' => ['enforce' => true, 'addresses' => [], 'allow_ses_simulator' => false]]);
        $this->expectException(RuntimeException::class);
        app(EnforceMailRecipientAllowlist::class)->handle(new MessageSending((new Email)->to('any@example.com'), []));
    }
}
