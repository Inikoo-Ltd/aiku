<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\Accounting\EInvoice\VerifyCustomerDic;
use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsController;

/**
 * The page behind the signed link in the company details request email (HELP-4976). The customer's own account user,
 * already logged in, goes straight to the account page; anyone else holding the link sees what we hold and can only fill
 * in the IČO, DIČ and IČ DPH still missing or unusable (UpdateRetinaCompanyDetails), and only with numbers the
 * Finančná správa register gives for the customer's own name.
 */
class ShowRetinaCompanyDetails
{
    use AsController;

    public static function url(Customer $customer): string
    {
        $path = URL::temporarySignedRoute('retina.company_details.show', Carbon::parse('2027-02-01 00:00:00'), ['customer' => $customer->id], absolute: false);

        return 'https://'.$customer->shop->website->domain.$path;
    }

    public static function isOwnAccount(Request $request, Customer $customer): bool
    {
        $webUser = $request->user('retina');

        return $webUser && $webUser->customer_id === $customer->id && $webUser->is_root;
    }

    /**
     * @return array{ico: string|null, dic: string|null, vat_number: string|null}
     */
    public static function heldDetails(Customer $customer): array
    {
        $taxNumber = $customer->taxNumber;

        return [
            'ico'        => in_array(data_get($customer->data, 'dic_verification.status'), [VerifyCustomerDic::NOT_FOUND, VerifyCustomerDic::NAME_DIFFERS], true) ? null : VerifyCustomerDic::ico($customer),
            'dic'        => UpdateRetinaCustomer::dicFor($customer),
            'vat_number' => $taxNumber?->valid ? (string)$taxNumber->number : null,
        ];
    }

    public function asController(Customer $customer, Request $request): Response|RedirectResponse
    {
        abort_unless($request->input('website')?->shop_id === $customer->shop_id && !$customer->deleted_at, 404);

        if (self::isOwnAccount($request, $customer)) {
            return redirect()->route('retina.sysadmin.settings.edit');
        }

        $customer->loadMissing('address', 'taxNumber');

        $previousLocale = app()->getLocale();
        app()->setLocale('sk');

        try {
            return Inertia::render('Auth/CompanyDetails', [
                'title'     => __('Company details for e-invoices'),
                'company'   => $customer->company_name ?: $customer->name,
                'address'   => $customer->address?->formatted_address,
                'held'      => self::heldDetails($customer),
                'can_check' => VerifyCustomerDic::isConfigured(),
                'save_url'  => $request->getRequestUri(),
                'login_url' => route('retina.login.show'),
                'saved'     => (bool)session('company_details_saved'),
                'labels'    => [
                    'intro'        => __('From 1 January 2027 our invoices reach you as electronic invoices. Please check your details and fill in what is missing.'),
                    'saved'        => __('Thank you, your details are saved.'),
                    'company'      => __('Company'),
                    'address'      => __('Address'),
                    'no_vat_payer' => __('Leave IČ DPH empty if you are not a VAT payer.'),
                    'cannot_check' => __('We could not check the register just now. Please try again later or log in to your account.'),
                    'save'         => __('Save'),
                    'change_held'  => __('To change details we already hold, log in to your account.'),
                    'log_in'       => __('Log in for full access'),
                ],
            ]);
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
