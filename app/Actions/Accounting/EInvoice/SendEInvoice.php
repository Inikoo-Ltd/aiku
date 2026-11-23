<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceEInvoiceTransmission;
use App\Models\SysAdmin\User;
use App\Notifications\EInvoiceProblemNotification;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Sends a finalised invoice or refund in e-invoicing scope to the Peppol access point. Each attempt is a
 * transmission row committed before the HTTP call, holding the exact XML and a single-use idempotency key.
 * A timeout or server error keeps the row QUEUED with the same key for PollEInvoiceTransmissions to retry;
 * a new key is minted only for a new attempt after the previous one ended as a problem.
 */
class SendEInvoice
{
    use AsAction;

    public string $commandSignature = 'e-invoices:send {invoice : invoice id}';

    public int $jobTries = 3;

    public function __construct(private readonly EInvoiceProvider $provider)
    {
    }

    public static function dispatchIfEnabled(Invoice $invoice): void
    {
        if (!$invoice->in_process && Arr::get($invoice->organisation->settings, 'e_invoicing.countries')) {
            static::dispatch($invoice)->afterCommit();
        }
    }

    public function handle(Invoice $invoice): ?InvoiceEInvoiceTransmission
    {
        if (!IsInvoiceInEInvoiceScope::run($invoice)) {
            return null;
        }

        [$xml, $error] = $this->build($invoice);

        $transmission = DB::transaction(function () use ($invoice, $xml, $error) {
            Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            $latest = $invoice->eInvoiceTransmissions()->latest('id')->first();
            if ($latest && !$latest->isProblem()) {
                return null;
            }

            return $invoice->eInvoiceTransmissions()->create([
                'organisation_id' => $invoice->organisation_id,
                'provider'        => $this->provider->name(),
                'idempotency_key' => (string)Str::uuid(),
                'tag'             => $invoice->uuid ?: 'aiku-invoice-'.$invoice->id,
                'status'          => $xml === null ? InvoiceEInvoiceTransmission::NOT_BUILT : InvoiceEInvoiceTransmission::QUEUED,
                'error'           => $error,
                'xml'             => $xml,
                'final_at'        => $xml === null ? now() : null,
            ]);
        });

        if (!$transmission) {
            return null;
        }

        if ($transmission->isProblem()) {
            $this->alert($transmission);
        } else {
            $this->transmit($transmission);
        }

        return $transmission->refresh();
    }

    /**
     * @return array{0: string|null, 1: array{message: string}|null}
     */
    private function build(Invoice $invoice): array
    {
        try {
            return [BuildUblInvoice::run($invoice), null];
        } catch (QueryException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return [null, ['message' => $exception->getMessage()]];
        } catch (Throwable $exception) {
            report($exception);

            return [null, ['message' => 'Aiku could not write the e-invoice: '.$exception->getMessage()]];
        }
    }

    /**
     * Sends a QUEUED row. Nothing is sent while the API key is missing or refused; PollEInvoiceTransmissions reports the
     * row once it has waited UNSENT_AFTER_HOURS. Before its key is first used the XML is written again, so an invoice queued for
     * weeks goes out as it is now. After that the same key always carries the same stored XML, which the provider answers
     * with its original response; as keys expire after 24 hours, a retry first looks for the transaction under the tag.
     */
    public function transmit(InvoiceEInvoiceTransmission $transmission): void
    {
        if ($transmission->status !== InvoiceEInvoiceTransmission::QUEUED || !$this->provider->isConfigured() || $transmission->invoice->trashed()) {
            return;
        }

        $lock = Cache::lock('e-invoice-transmit:'.$transmission->id, 300);
        if (!$lock->get()) {
            return;
        }

        try {
            $transmission->refresh();
            if ($transmission->status === InvoiceEInvoiceTransmission::QUEUED) {
                $this->transmitLocked($transmission);
            }
        } finally {
            $lock->release();
        }
    }

    private function transmitLocked(InvoiceEInvoiceTransmission $transmission): void
    {

        if (!$transmission->first_attempted_at) {
            $transmission->update(['first_attempted_at' => now()]);
            [$xml, $error] = $this->build($transmission->invoice->refresh());
            if ($xml === null) {
                $this->applyStatus($transmission, InvoiceEInvoiceTransmission::NOT_BUILT, $error);

                return;
            }
            $transmission->update(['xml' => $xml]);
        } elseif ($this->recover($transmission, notFoundIsError: false)) {
            return;
        }

        try {
            $accepted = $this->provider->send($transmission->xml, $transmission->idempotency_key, $transmission->tag);
        } catch (EInvoiceProviderException $exception) {
            if ($exception->isKeyAlreadyUsed()) {
                $this->recover($transmission);
            } elseif ($exception->isDocumentRefused()) {
                $this->applyStatus($transmission, 'INVALID', ['message' => $exception->getMessage(), 'payload' => $exception->payload]);
            } else {
                $transmission->update(['error' => ['message' => $exception->getMessage(), 'http_status' => $exception->httpStatus]]);
            }

            return;
        }

        $transmission->update([
            'transaction_id' => $accepted['transaction_id'],
            'checksum'       => $accepted['checksum'],
            'accepted_at'    => now(),
            'error'          => null,
        ]);
        $this->applyStatus($transmission, $accepted['status']);
    }

    /**
     * Finds the transaction an earlier attempt created under the invoice tag, leaving out transactions other attempts of
     * the same invoice already hold, and adopts it.
     */
    public function recover(InvoiceEInvoiceTransmission $transmission, bool $notFoundIsError = true): bool
    {
        try {
            $taken = InvoiceEInvoiceTransmission::where('tag', $transmission->tag)->whereNotNull('transaction_id')->pluck('transaction_id')->all();
            $found = collect($this->provider->findByTag($transmission->tag))
                ->reject(fn (array $candidate) => $candidate['tag'] !== null && $candidate['tag'] !== $transmission->tag)
                ->reject(fn (array $candidate) => in_array($candidate['transaction_id'], $taken, true))
                ->sortByDesc(fn (array $candidate) => (int)$candidate['transaction_id'])
                ->first();
        } catch (EInvoiceProviderException $exception) {
            $transmission->update(['error' => ['message' => $exception->getMessage(), 'http_status' => $exception->httpStatus]]);

            return $notFoundIsError === false;
        }

        if (!$found) {
            if ($notFoundIsError) {
                $transmission->update(['error' => ['message' => 'The provider refused the key for a different document and lists no transaction under the tag', 'http_status' => 409]]);
            }

            return false;
        }

        $transmission->update(['transaction_id' => $found['transaction_id'], 'accepted_at' => now(), 'error' => null]);
        $this->applyStatus($transmission, $found['status']);

        return true;
    }

    /**
     * @param array<string, mixed>|null $error
     */
    public function applyStatus(InvoiceEInvoiceTransmission $transmission, string $status, ?array $error = null): void
    {
        if ($transmission->final_at && !in_array(strtoupper($status), [...InvoiceEInvoiceTransmission::PROBLEM_STATUSES, InvoiceEInvoiceTransmission::DELIVERED], true)) {
            return;
        }
        $transmission->status            = strtoupper($status);
        $transmission->status_checked_at = now();
        if ($error !== null) {
            $transmission->error = $error;
        }
        if ($transmission->isFinal() && !$transmission->final_at) {
            $transmission->final_at = now();
        }
        $transmission->save();

        if ($transmission->isProblem()) {
            $this->alert($transmission);
        }
    }

    /**
     * Once per transmission and reason (its problem status, or STUCK), to the active users who edit the organisation's accounts.
     */
    public function alert(InvoiceEInvoiceTransmission $transmission, ?string $reason = null): int
    {
        $reason ??= $transmission->status;
        $claimed = InvoiceEInvoiceTransmission::whereKey($transmission->id)
            ->where(fn ($query) => $query->whereNull('alerted_status')->orWhere('alerted_status', '!=', $reason))
            ->update(['alerted_status' => $reason, 'alerted_at' => now()]);
        if (!$claimed) {
            return 0;
        }
        $transmission->refresh();

        setPermissionsTeamId($transmission->invoice->group_id);
        $users = User::permission(["accounting.$transmission->organisation_id.edit"])->where('status', true)->get();
        if ($users->isEmpty()) {
            report(new RuntimeException("E-invoice problem $reason on invoice $transmission->invoice_id and no active user edits the accounts of organisation $transmission->organisation_id"));
        }
        foreach ($users as $user) {
            $notification = new EInvoiceProblemNotification($transmission);
            NotifyUserOnChosenChannels::run(
                $user,
                UserNotificationEnum::E_INVOICE_PROBLEM,
                $notification,
                $notification->title(),
                $notification->body(),
                $notification->url()
            );
        }

        return $users->count();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('accounting.'.$request->route('invoice')->organisation_id.'.edit');
    }

    /**
     * Send again after staff fixed what the last attempt was refused for.
     *
     * @throws ValidationException
     */
    public function asController(Invoice $invoice): RedirectResponse
    {
        if (!$this->handle($invoice)) {
            throw ValidationException::withMessages(['e_invoice' => __('This invoice is not for e-invoicing, or it is already sent or on its way.')]);
        }

        return back();
    }

    public function asCommand(Command $command): int
    {
        $invoice      = Invoice::findOrFail($command->argument('invoice'));
        $transmission = $this->handle($invoice);

        $command->info($transmission ? "$invoice->reference: $transmission->status" : "$invoice->reference: not in scope, or already sent or on its way");

        return 0;
    }
}
