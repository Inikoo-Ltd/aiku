<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\Accounting\EInvoice\BuildUblInvoice;
use App\Actions\Accounting\EInvoice\VerifyCustomerDic;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Models\CRM\Customer;
use App\Models\Helpers\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Saves the IČO, DIČ and IČ DPH a customer fills in from the company details request link (HELP-4976) without logging
 * in. A field we already hold is never changed, and nothing is saved unless the Finančná správa register has the IČO
 * under the customer's own name with the same DIČ, and the IČ DPH is that DIČ in the list of VAT payers, so a forwarded
 * link can at worst fill in the customer's correct details. A DIČ the register gives for the IČO is filled in too. The
 * live request is emptied before UpdateCustomer runs, since it reads tax_number from the request (HELP-4808), so a field
 * added to the form can never reach the customer.
 */
class UpdateRetinaCompanyDetails
{
    use AsAction;

    /**
     * @param array{ico?: string|null, dic?: string|null, vat_number?: string|null} $input
     *
     * @return array{identity_document_number?: string, peppol_dic?: string, vat_number?: string}
     * @throws ValidationException
     */
    public function handle(Customer $customer, array $input, VerifyCustomerDic $register): array
    {
        $held  = ShowRetinaCompanyDetails::heldDetails($customer);
        $typed = [
            'ico'        => $held['ico'] ? null : (preg_replace('/\D/', '', (string)($input['ico'] ?? '')) ?: null),
            'dic'        => $held['dic'] ? null : (preg_replace('/\D/', '', (string)($input['dic'] ?? '')) ?: null),
            'vat_number' => $held['vat_number'] ? null : (preg_replace('/\D/', '', (string)($input['vat_number'] ?? '')) ?: null),
        ];

        if ($typed['ico'] && (strlen($typed['ico']) < 6 || strlen($typed['ico']) > 8)) {
            throw ValidationException::withMessages(['ico' => __('The IČO has 6 to 8 digits.')]);
        }
        if ($typed['dic'] && !BuildUblInvoice::isSlovakDic($typed['dic'])) {
            throw ValidationException::withMessages(['dic' => __('This is not a valid Slovak DIČ. Please check the number.')]);
        }

        $ico = $held['ico'] ?? ($typed['ico'] ? str_pad($typed['ico'], 8, '0', STR_PAD_LEFT) : null);
        if (!$ico) {
            throw ValidationException::withMessages(['ico' => __('Please fill in your IČO.')]);
        }

        try {
            $registered = $register->lookupByIco($ico);
        } catch (Throwable) {
            throw ValidationException::withMessages(['ico' => __('We could not check the register just now. Please try again later or log in to your account.')]);
        }

        if (!$registered) {
            throw ValidationException::withMessages(['ico' => __('This IČO is not in the Finančná správa register. Please check it, or log in to your account to change it.')]);
        }
        if (!self::isRegisteredToCustomer($customer, $registered)) {
            throw ValidationException::withMessages(['ico' => __('The register has this IČO under another name. Please log in to your account to change your details.')]);
        }

        $dic = $held['dic'] ?? $typed['dic'] ?? $registered['dic'];
        if ($dic !== $registered['dic']) {
            throw ValidationException::withMessages(['dic' => __('This DIČ is not the one the register has for your IČO.')]);
        }

        if ($typed['vat_number']) {
            if ($typed['vat_number'] !== $dic) {
                throw ValidationException::withMessages(['vat_number' => __('Your IČ DPH is SK followed by your DIČ.')]);
            }
            try {
                $isVatPayer = $register->lookupByVatNumber('SK'.$dic) !== null;
            } catch (Throwable) {
                throw ValidationException::withMessages(['vat_number' => __('We could not check the register just now. Please try again later or log in to your account.')]);
            }
            if (!$isVatPayer) {
                throw ValidationException::withMessages(['vat_number' => __('This IČ DPH is not in the list of VAT payers. Leave it empty if you are not a VAT payer.')]);
            }
        }

        $changes = array_filter([
            'identity_document_number' => $held['ico'] ? null : $ico,
            'peppol_dic'               => $held['dic'] ? null : $dic,
            'vat_number'               => $typed['vat_number'] ? 'SK'.$dic : null,
        ]);

        if (!$changes) {
            throw ValidationException::withMessages(['ico' => __('Please fill in the details that are missing.')]);
        }

        DB::transaction(function () use ($customer, $held, $changes) {
            $locked = Customer::lockForUpdate()->findOrFail($customer->id);
            if (ShowRetinaCompanyDetails::heldDetails($locked) !== $held) {
                throw ValidationException::withMessages(['ico' => __('Your details have just changed. Please reload the page.')]);
            }

            $modelData = array_filter([
                'identity_document_number' => $changes['identity_document_number'] ?? null,
                'peppol_dic'               => $changes['peppol_dic'] ?? null,
            ]);
            if (isset($changes['vat_number'])) {
                $modelData['tax_number'] = [
                    'number'     => $changes['vat_number'],
                    'country_id' => Country::where('code', 'SK')->value('id'),
                ];
            }

            request()->replace([]);
            UpdateCustomer::make()->action($locked, $modelData);
        });

        return $changes;
    }

    /**
     * Stricter than VerifyCustomerDic::isTheirs, since nobody is logged in: a company is matched by its company name only,
     * never by the contact person's name, so the IČO of a sole trader who shares the contact's name is not taken.
     *
     * @param array{dic: string, ico: string, name: string} $registered
     */
    public static function isRegisteredToCustomer(Customer $customer, array $registered): bool
    {
        $name = trim((string)$customer->company_name) ?: trim((string)$customer->name);

        return $name !== '' && VerifyCustomerDic::sameName($registered['name'], $name);
    }

    /**
     * @throws ValidationException
     */
    public function asController(Customer $customer, Request $request): RedirectResponse
    {
        abort_unless($request->input('website')?->shop_id === $customer->shop_id && !$customer->deleted_at, 404);

        $input = $request->validate([
            'ico'        => ['nullable', 'string', 'max:32'],
            'dic'        => ['nullable', 'string', 'max:32'],
            'vat_number' => ['nullable', 'string', 'max:32'],
        ]);

        $previousLocale = app()->getLocale();
        app()->setLocale('sk');

        try {
            $this->handle($customer, $input, VerifyCustomerDic::make());
        } finally {
            app()->setLocale($previousLocale);
        }

        return back()->with('company_details_saved', true);
    }
}
