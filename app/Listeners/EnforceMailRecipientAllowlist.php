<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mime\Address;

final class EnforceMailRecipientAllowlist
{
    public function handle(MessageSending $event): void
    {
        if (! (bool) config('mail.recipient_allowlist.enforce')) {
            return;
        }

        $allowed = array_fill_keys(config('mail.recipient_allowlist.addresses', []), true);
        $allowSimulator = (bool) config('mail.recipient_allowlist.allow_ses_simulator');
        if ($allowed === [] && ! $allowSimulator) {
            throw new RuntimeException('Mail recipient allowlist is required in this environment.');
        }

        $recipients = array_merge(
            $event->message->getTo(),
            $event->message->getCc(),
            $event->message->getBcc(),
        );

        foreach ($recipients as $recipient) {
            $address = strtolower($recipient instanceof Address ? $recipient->getAddress() : (string) $recipient);
            if (isset($allowed[$address]) || ($allowSimulator && str_ends_with($address, '@simulator.amazonses.com'))) {
                continue;
            }

            Log::warning('mail.recipient_blocked', [
                'recipient_sha256' => hash('sha256', $address),
                'environment' => app()->environment(),
            ]);
            throw new RuntimeException('Mail recipient is not allowed in this environment.');
        }
    }
}
