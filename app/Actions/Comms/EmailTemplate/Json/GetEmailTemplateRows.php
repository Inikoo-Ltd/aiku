<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 21 Sep 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate\Json;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateRowTypeEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class GetEmailTemplateRows extends OrgAction
{
    public function handle(Shop $shop, ?string $rowType = null, bool $otherShops = false): Collection
    {
        $query = EmailTemplate::where('builder', EmailTemplateBuilderEnum::BEEFREE->value)
            ->where('state', EmailTemplateStateEnum::ACTIVE->value)
            ->whereJsonContains('data->is_row', true);

        if ($otherShops) {
            $query->whereNotNull('shop_id')->where('shop_id', '<>', $shop->id);
        } else {
            $query->where('shop_id', $shop->id);
        }

        if ($rowType) {
            $query->where('data->row_type', $rowType);
        }

        return $query->latest()->get()->map(function (EmailTemplate $emailTemplate) use ($otherShops) {
            $row = $emailTemplate->layout;

            $row['metadata'] = [
                'name'     => $emailTemplate->name,
                'idRow'    => (string)$emailTemplate->id,
                'rowType'  => data_get($emailTemplate->data, 'row_type', EmailTemplateRowTypeEnum::BLOCK->value),
                'shopName' => $otherShops ? $emailTemplate->shop?->name : null,
            ];

            return $row;
        })->values();
    }

    public function asController(Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle(
            $shop,
            $request->get('row_type'),
            $request->boolean('other_shops')
        );
    }
}
