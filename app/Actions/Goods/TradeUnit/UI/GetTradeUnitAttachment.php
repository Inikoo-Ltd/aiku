<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Goods\TradeUnit\UI;

use App\Actions\Traits\HasBucketAttachment;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\Helpers\Media\UI\IndexAttachments;

class GetTradeUnitAttachment
{
    use AsObject;
    use HasBucketAttachment;

    public function handle(TradeUnit $tradeUnit): array
    {
        return [
            'id'                        => $tradeUnit->id,
            'editable'                  => true,
            'attachment_category_box'   => $this->getAttachmentData($tradeUnit),
            'attachRoute' => [
                'name'       => 'grp.models.trade-unit.attachment.attach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ]
            ],
            'detachRoute' => [
                'name'       => 'grp.models.trade-unit.attachment.detach',
                'parameters' => [
                    'tradeUnit' => $tradeUnit->id,
                ],
                'method'     => 'delete'
            ],
            'attachments'   => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))->resolve()
        ];
    }
}
