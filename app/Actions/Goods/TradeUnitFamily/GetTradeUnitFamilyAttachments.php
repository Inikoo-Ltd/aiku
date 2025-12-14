<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 14 Dec 2025 09:26:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnitFamily;

use App\Actions\Traits\HasBucketAttachment;
use App\Models\Goods\TradeUnitFamily;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitFamilyAttachments
{
    use AsObject;
    use HasBucketAttachment;

    public function handle(TradeUnitFamily $tradeUnitFamily): array
    {
        return [
            'id'                        => $tradeUnitFamily->id,
            'editable'                  => true,
            'attachment_category_box'   => $this->getAttachmentData($tradeUnitFamily),
            'attachRoute' => [
                'name'       => 'grp.models.trade_unit_family.attachment.attach',
                'parameters' => [
                    'tradeUnitFamily' => $tradeUnitFamily->id,
                ]
            ],
            'detachRoute' => [
                'name'       => 'grp.models.trade_unit_family.attachment.detach',
                'parameters' => [
                    'tradeUnitFamily' => $tradeUnitFamily->id,
                ],
                'method'     => 'delete'
            ],
        ];
    }
}
