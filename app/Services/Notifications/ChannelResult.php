<?php

namespace App\Services\Notifications;

/**
 * What a provider said.
 *
 * Every channel returns one of these rather than throwing, so the caller
 * handles a refused message and a network failure the same way - by writing
 * the reason to the log and moving on. A notification must never be able to
 * take down the financial operation that triggered it.
 */
class ChannelResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        /** Whether trying again later could plausibly work. */
        public readonly bool $retryable = false,
    ) {}

    public static function sent(?string $messageId = null): self
    {
        return new self(true, $messageId);
    }

    /**
     * A refusal that will happen again however many times it is retried - a
     * malformed number, a rejected template, a bad credential.
     */
    public static function failed(string $error): self
    {
        return new self(false, null, $error, false);
    }

    /**
     * A timeout, a 5xx, a rate limit. Worth another go.
     */
    public static function retryable(string $error): self
    {
        return new self(false, null, $error, true);
    }
}
