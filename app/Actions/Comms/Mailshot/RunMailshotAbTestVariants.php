<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 Oct 2026 16:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\UI\GetMailshotAbTest;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RunMailshotAbTestVariants
{
    use AsAction;

    public string $jobQueue = 'ses';
    public string $commandSignature = 'run-mailshot-ab-test-variants';

    public function handle(): void
    {
        $dueOriginals = Mailshot::query()
            ->where('state', MailshotStateEnum::SENT)
            ->where('ab_test_variant_delay_seconds', '>', 0)
            ->whereRaw("start_sending_at + (ab_test_variant_delay_seconds || ' seconds')::interval <= ?", [Carbon::now()->utc()])
            ->whereHas('abTestVariant', fn ($query) => $query->where('state', MailshotStateEnum::READY)->whereNull('start_sending_at'));

        foreach ($dueOriginals->cursor() as $mailshot) {
            $claimed = Mailshot::whereKey($mailshot->abTestVariant->id)
                ->where('state', MailshotStateEnum::READY)
                ->whereNull('start_sending_at')
                ->update([
                    'state'            => MailshotStateEnum::SENDING,
                    'start_sending_at' => Carbon::now()->utc(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                PrepareMailshotAbTestRecipients::make()->sendDelayedVariant($mailshot);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->freezeFinishedTests();
        $this->sendDueWinners();
    }

    /**
     * The comparison stops at the end of the tracking window, whether or not the winner is sent automatically.
     */
    private function freezeFinishedTests(): void
    {
        $unfrozenTests = Mailshot::query()
            ->whereNull('ab_test_winner_sent_at')
            ->whereNull('data->AB_testing_data')
            ->where('state', MailshotStateEnum::SENT)
            ->whereHas('abTestVariant', fn ($query) => $query->where('state', MailshotStateEnum::SENT));

        foreach ($unfrozenTests->cursor() as $mailshot) {
            try {
                FreezeMailshotAbTestResults::run($mailshot);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /**
     * Tests set to send the winner automatically get the suggested winner once tracking is over, version A on a tie.
     */
    private function sendDueWinners(): void
    {
        $finishedTests = Mailshot::query()
            ->where('ab_test_auto_send_winner', true)
            ->whereNull('ab_test_winner_sent_at')
            ->where('ab_test_size_percentage', '<', 100)
            ->where('state', MailshotStateEnum::SENT)
            ->whereHas('abTestVariant', fn ($query) => $query->where('state', MailshotStateEnum::SENT));

        foreach ($finishedTests->cursor() as $mailshot) {
            if ($mailshot->abTestTrackingEndsAt()?->isFuture() !== false) {
                continue;
            }

            try {
                FreezeMailshotAbTestResults::run($mailshot);
                SendMailshotAbTestWinner::make()->action($mailshot, [
                    'winner_mailshot_id' => GetMailshotAbTest::run($mailshot, false)['suggested_winner_id'] ?? $mailshot->id,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
