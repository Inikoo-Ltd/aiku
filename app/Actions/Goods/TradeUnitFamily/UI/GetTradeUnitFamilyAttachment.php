<?php

namespace App\Actions\Goods\TradeUnitFamily\UI;

use App\Actions\Traits\HasBucketAttachment;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Models\Goods\TradeUnitFamily;

class GetTradeUnitFamilyAttachment
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
            /* 'attachments'   => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))->resolve() */
        ];
    }
}
