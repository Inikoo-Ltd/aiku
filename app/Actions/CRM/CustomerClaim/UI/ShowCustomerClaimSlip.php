<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim\UI;

use App\Actions\CRM\CustomerClaim\GetCustomerClaimForm;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Lorisleiva\Actions\Concerns\AsAction;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * The claim slip a customer prints and puts in the parcel when staff ask for the items back
 * (HELP-4869): the claim reference as a barcode for goods in, the order, the items and our return
 * address (the warehouse the order left from). It is reached through a signed link in the approved
 * email, the account and Merlin, so the customer needs no login. No carrier return label is made.
 */
class ShowCustomerClaimSlip
{
    use AsAction;

    public function handle(CustomerClaim $claim): View
    {
        $claim->loadMissing(['lines', 'order', 'customer', 'shop']);
        $locale    = $claim->shop->language?->code ?? app()->getLocale();
        $warehouse = $claim->order?->deliveryNotes()->where('delivery_notes.type', DeliveryNoteTypeEnum::ORDER)->with('warehouse.address')->first()?->warehouse;

        return view('crm.customer-claim-slip', [
            'claim'         => $claim,
            'locale'        => $locale,
            'barcode'       => (new BarcodeGeneratorSVG())->getBarcode($claim->reference, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 60),
            'returnAddress' => $warehouse?->address?->formatted_address,
            'returnTo'      => $claim->shop->name,
            'lines'         => $claim->lines->map(fn ($line) => [
                'code'     => $line->code,
                'name'     => $line->name,
                'quantity' => (float) $line->quantity,
                'reason'   => GetCustomerClaimForm::reasonLabel($line->reason, $locale),
            ])->all(),
        ]);
    }

    /**
     * Signed on the path alone, so proxies rewriting scheme or host cannot break the link, then given
     * the app domain the route is bound to (as the purchase order labels are).
     */
    public static function url(CustomerClaim $claim): string
    {
        $signed = URL::signedRoute('grp.customer_claim_slip', ['customerClaim' => $claim->ulid], absolute: false);
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        return route('grp.customer_claim_slip', ['customerClaim' => $claim->ulid, ...$query]);
    }

    public function asController(CustomerClaim $customerClaim): Response
    {
        abort_unless((bool) data_get($customerClaim->result, 'return_requested'), 404);

        $previousLocale = app()->getLocale();
        app()->setLocale($customerClaim->shop->language?->code ?? $previousLocale);

        try {
            return response($this->handle($customerClaim)->render());
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
