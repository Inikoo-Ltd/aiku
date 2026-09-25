<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 18 Sep 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerStaging;

final class PartnerBayPickingOrder
{
    /**
     * A partner's goods out bay holds stock set aside for that partner alone. On the partner's
     * own delivery note the bay is the first place to pick from, on anybody else's it is the
     * last. Sorts ascending and expects the locations table in scope.
     *
     * The partner is recognised by its intercompany customer, kept on the buyer's side of the
     * partnership, while the bay is kept on the seller's side.
     */
    public static function sql(string $deliveryNoteItemIdExpression): string
    {
        return "(case
            when not locations.is_goods_out then 1
            when exists (
                select 1
                from delivery_note_items bay_dni
                join delivery_notes bay_dn on bay_dn.id = bay_dni.delivery_note_id
                join org_partners seller_side on seller_side.organisation_id = bay_dn.organisation_id
                    and seller_side.goods_out_location_id = locations.id
                join org_partners buyer_side on buyer_side.organisation_id = seller_side.partner_id
                    and buyer_side.partner_id = seller_side.organisation_id
                where bay_dni.id = ".$deliveryNoteItemIdExpression."
                    and jsonb_typeof(buyer_side.data->'intercompany_customers') = 'object'
                    and exists (
                        select 1 from jsonb_each_text(buyer_side.data->'intercompany_customers') intercompany_customer
                        where intercompany_customer.value = bay_dn.customer_id::text
                    )
            ) then 0
            else 2
        end)";
    }
}
