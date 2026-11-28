<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Accounting\EInvoice\EnsureInvoiceNotSentAsEInvoice;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Accounting managers fix the tax number of an issued invoice from its page and, in the same
 * step, the customer's, so the next orders are taxed with it too (HELP-4808)
 */
class UpdateInvoiceTaxNumber extends OrgAction
{
    public static function canEditTaxNumber(User $user, Invoice $invoice): bool
    {
        return $user->authTo("org-supervisor.$invoice->organisation_id.accounting");
    }

    /**
     * A number without a known country prefix would be read as no number and delete the one on file
     */
    public static function taxNumberRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $taxNumber = getUnformattedTaxNumber(is_string($value) ? trim($value) : null);

            if (!$taxNumber || !Country::where('code', $taxNumber['country_code'])->exists()) {
                $fail(__('Start the tax number with the country code, e.g. ES B12345678'));
            }
        };
    }

    /**
     * The field is formatted_tax_number, not tax_number: UpdateCustomer's prepareForValidation reads
     * tax_number from the live request even when called as an action, and would blank the customer's
     */
    public function handle(Invoice $invoice, array $modelData): Invoice
    {
        EnsureInvoiceNotSentAsEInvoice::run($invoice, ['formatted_tax_number' => Arr::get($modelData, 'formatted_tax_number')]);

        $formattedTaxNumber = trim((string)Arr::get($modelData, 'formatted_tax_number')) ?: null;

        return DB::transaction(function () use ($invoice, $modelData, $formattedTaxNumber) {
            if (Arr::get($modelData, 'update_customer') && $invoice->customer) {
                $this->updateCustomerTaxNumber($invoice->customer, $invoice, $formattedTaxNumber);
            }

            return UpdateInvoice::make()->action($invoice, ['formatted_tax_number' => $formattedTaxNumber]);
        });
    }

    private function updateCustomerTaxNumber(Customer $customer, Invoice $invoice, ?string $formattedTaxNumber): void
    {
        $oldTaxNumber = $customer->taxNumber?->getFormattedTaxNumber();
        $taxNumber    = getUnformattedTaxNumber($formattedTaxNumber);

        try {
            UpdateCustomer::make()->action($customer, [
                'tax_number' => [
                    'number'     => $taxNumber['number'] ?? null,
                    'country_id' => $taxNumber ? Country::where('code', $taxNumber['country_code'])->value('id') : null,
                ],
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['formatted_tax_number' => Arr::flatten($exception->errors())]);
        }

        $newTaxNumber = $customer->refresh()->taxNumber?->getFormattedTaxNumber();

        if ($oldTaxNumber === $newTaxNumber) {
            return;
        }

        $customer->auditEvent     = 'invoice_tax_number_update';
        $customer->isCustomEvent  = true;
        $customer->auditCustomOld = ['tax_number' => $oldTaxNumber];
        $customer->auditCustomNew = [
            'tax_number'       => $newTaxNumber,
            'affected_invoice' => $invoice->reference,
        ];

        Event::dispatch(new AuditCustom($customer));
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canEditTaxNumber($request->user(), $request->route('invoice'));
    }

    public function rules(): array
    {
        return [
            'formatted_tax_number' => ['present', 'nullable', 'string', 'max:64', self::taxNumberRule()],
            'update_customer'      => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
