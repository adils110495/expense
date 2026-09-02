<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Talks to WhatsApp or the email provider, off the request.
 *
 * Takes an id rather than the model: by the time a worker picks this up the
 * row may have been marked cancelled or already sent from elsewhere, and a
 * serialised copy from minutes ago would not know.
 */
class SendNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $logId) {}

    /** Attempts before the queue gives up, from config so it is tunable. */
    public function tries(): int
    {
        return max(1, (int) config('notifications.retries', 3));
    }

    /**
     * Widening gaps between attempts, so a provider having a bad minute is
     * not hammered by every queued message at once.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return config('notifications.retry_backoff', [30, 120, 600]);
    }

    public function handle(NotificationService $notifications): void
    {
        $log = NotificationLog::find($this->logId);

        // Deleted, or already resolved by a webhook or a manual send.
        if (! $log) {
            return;
        }

        // deliver() reports whether another attempt could plausibly help. It
        // has already recorded sent, or failed for good - retrying a malformed
        // number forever helps nobody.
        if (! $notifications->deliver($log)) {
            return;
        }

        if ($this->attempts() < $this->tries()) {
            $this->release($this->backoffFor($this->attempts()));

            return;
        }

        // Out of attempts. Said so explicitly, because releasing is what would
        // otherwise have brought us back - without this the job ends cleanly
        // and the log sits on "pending" for ever with nothing coming for it.
        $notifications->giveUp(
            $log,
            'Gave up after '.$this->tries().' attempts. Last error: '.($log->error ?: 'unknown'),
        );
    }

    /**
     * Called by the queue once every attempt is spent, including when the
     * worker itself died mid-send.
     */
    public function failed(?\Throwable $e): void
    {
        $log = NotificationLog::find($this->logId);

        if ($log && $log->status === 'pending') {
            app(NotificationService::class)->giveUp(
                $log,
                $e?->getMessage() ?: 'Gave up after '.$this->tries().' attempts.',
            );
        }
    }

    private function backoffFor(int $attempt): int
    {
        $delays = $this->backoff();

        return $delays[$attempt - 1] ?? end($delays);
    }
}
