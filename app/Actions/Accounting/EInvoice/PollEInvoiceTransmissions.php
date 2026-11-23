<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Models\Accounting\InvoiceEInvoiceTransmission;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The provider tells only FAILED and REJECTED by webhook, so delivery is polled. Rows not accepted yet are sent
 * again with their own key; accepted ones are asked for their status. One the provider has not accepted UNSENT_AFTER_HOURS
 * after it was queued (key missing or refused, provider down), or still not final STUCK_AFTER_DAYS after it was accepted
 * (SENT_WITHOUT_RESPONSE may never move), is reported once, and polling stops STOP_POLLING_AFTER_DAYS after acceptance.
 * A row that throws is reported and skipped so it cannot hold up the others.
 */
class PollEInvoiceTransmissions
{
    use AsAction;

    public const int STUCK_AFTER_DAYS = 3;

    public const int UNSENT_AFTER_HOURS = 24;

    public const int STOP_POLLING_AFTER_DAYS = 30;

    public string $commandSignature = 'e-invoices:poll';

    public function __construct(private readonly EInvoiceProvider $provider)
    {
    }

    public function handle(): int
    {
        $sender = SendEInvoice::make();
        $polled = 0;

        InvoiceEInvoiceTransmission::whereNull('final_at')
            ->where(fn ($query) => $query->whereNull('accepted_at')->orWhere('accepted_at', '>', now()->subDays(self::STOP_POLLING_AFTER_DAYS)))
            ->with('invoice')
            ->lazyById(100)
            ->each(function (InvoiceEInvoiceTransmission $transmission) use ($sender, &$polled) {
                if ($transmission->invoice->trashed()) {
                    return;
                }
                $polled++;

                try {
                    $this->poll($sender, $transmission);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

        return $polled;
    }

    private function poll(SendEInvoice $sender, InvoiceEInvoiceTransmission $transmission): void
    {
        if (!$transmission->transaction_id) {
            $sender->transmit($transmission);
        } else {
            try {
                $status   = $this->provider->status($transmission->transaction_id);
                $isProblem = in_array(strtoupper($status['status']), InvoiceEInvoiceTransmission::PROBLEM_STATUSES, true);
                $sender->applyStatus($transmission, $status['status'], $isProblem ? ['message' => 'Provider status '.$status['status'], 'payload' => $status['details']] : null);
            } catch (EInvoiceProviderException $exception) {
                $transmission->update(['error' => ['message' => $exception->getMessage(), 'http_status' => $exception->httpStatus]]);
            }
        }

        $transmission->refresh();
        $isStuck = $transmission->accepted_at
            ? $transmission->accepted_at->lt(now()->subDays(self::STUCK_AFTER_DAYS))
            : $transmission->created_at->lt(now()->subHours(self::UNSENT_AFTER_HOURS));
        if (!$transmission->isFinal() && $isStuck) {
            $sender->alert($transmission, $transmission->accepted_at ? 'STUCK' : 'UNSENT');
        }
    }

    public function asCommand(Command $command): int
    {
        $command->info('Polled '.$this->handle().' e-invoice transmission(s)');

        return 0;
    }
}
