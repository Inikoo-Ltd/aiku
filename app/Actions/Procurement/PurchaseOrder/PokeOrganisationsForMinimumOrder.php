<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\SendUserPushNotification;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use App\Notifications\PurchaseOrderMinimumPokeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

/**
 * Asks the buyers of the other organisations' drafts with the same supplier to add a product, so that
 * together the orders reach the supplier's minimum. A buyer is asked once per product every 15 minutes.
 */
class PokeOrganisationsForMinimumOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(OrgSupplierProduct $orgSupplierProduct, User $sender, float $cartonsShort): int
    {
        $supplierProduct = $orgSupplierProduct->supplierProduct;

        $drafts = PurchaseOrder::query()
            ->where('group_id', $orgSupplierProduct->group_id)
            ->where('organisation_id', '!=', $orgSupplierProduct->organisation_id)
            ->where('state', PurchaseOrderStateEnum::IN_PROCESS)
            ->where(fn ($query) => $query->where('supplier_id', $supplierProduct->supplier_id)
                ->orWhereExists(fn ($lines) => $lines->selectRaw('1')->from('purchase_order_transactions as pot')
                    ->whereColumn('pot.purchase_order_id', 'purchase_orders.id')
                    ->whereNull('pot.deleted_at')
                    ->where('pot.supplier_product_id', $supplierProduct->id)))
            ->whereHas('buyer', fn ($buyer) => $buyer->where('status', true)->where('id', '!=', $sender->id))
            ->whereNotExists(fn ($pokes) => $pokes->selectRaw('1')->from('purchase_order_minimum_pokes as poke')
                ->whereColumn('poke.recipient_id', 'purchase_orders.buyer_id')
                ->where('poke.supplier_product_id', $supplierProduct->id)
                ->where('poke.created_at', '>', now()->subMinutes(15)))
            ->with('buyer', 'organisation')
            ->get()
            ->unique('buyer_id');

        $senderName = $sender->contact_name ?: $sender->username;
        $title      = __(':code: :organisation is short of the supplier minimum', ['code' => $supplierProduct->code, 'organisation' => $orgSupplierProduct->organisation->code]);

        foreach ($drafts as $draft) {
            $body = __(':sender (:organisation) needs :short more cartons of :code :name to reach the supplier minimum of :moq. Can you add some to your draft :reference?', [
                'sender'       => $senderName,
                'organisation' => $orgSupplierProduct->organisation->code,
                'short'        => trimDecimalZeros($cartonsShort),
                'code'         => $supplierProduct->code,
                'name'         => $supplierProduct->name,
                'moq'          => $supplierProduct->minimum_carton_order,
                'reference'    => $draft->reference,
            ]);

            DB::table('purchase_order_minimum_pokes')->insert([
                'group_id'             => $orgSupplierProduct->group_id,
                'organisation_id'      => $orgSupplierProduct->organisation_id,
                'sender_id'            => $sender->id,
                'recipient_id'         => $draft->buyer_id,
                'purchase_order_id'    => $draft->id,
                'supplier_product_id'  => $supplierProduct->id,
                'cartons_short'        => $cartonsShort,
                'minimum_carton_order' => (int) $supplierProduct->minimum_carton_order,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $draft->buyer->notify(new PurchaseOrderMinimumPokeNotification($draft, $title, $body));

            if (in_array('browser', UserNotificationEnum::PURCHASE_ORDER_MINIMUM_POKE->deliverTo($draft->buyer), true) && $draft->buyer->pushSubscriptions()->exists()) {
                SendUserPushNotification::dispatch($draft->buyer, [
                    'title' => $title,
                    'body'  => Str::limit($body, 200),
                    'url'   => route('grp.org.procurement.purchase_orders.show', [$draft->organisation->slug, $draft->slug]),
                    'tag'   => UserNotificationEnum::PURCHASE_ORDER_MINIMUM_POKE->value,
                ]);
            }
        }

        return $drafts->count();
    }

    public function rules(): array
    {
        return [
            'cartons_short' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function asController(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): array
    {
        $this->initialisation($orgSupplierProduct->organisation, $request);

        return ['notified' => $this->handle($orgSupplierProduct, $request->user(), (float) $this->validatedData['cartons_short'])];
    }
}
