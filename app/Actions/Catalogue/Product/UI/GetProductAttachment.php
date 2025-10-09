<?php

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Traits\HasBucketAttachment;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Models\Catalogue\Product;

class GetProductAttachment
{
    use AsObject;
    use HasBucketAttachment;

    public function handle(Product $product): array
    {
        return [
            'id'                        => $product->id,
            'editable'                  => false,
            'attachment_category_box'   => $this->getAttachmentData($product),
            'attachRoute' => [
                'name'       => 'grp.models.product.attachment.attach',
                'parameters' => [
                    'product' => $product->id,
                ]
            ],
            'detachRoute' => [
                'name'       => 'grp.models.product.attachment.detach',
                'parameters' => [
                    'product' => $product->id,
                ],
                'method'     => 'delete'
            ],
            /* 'attachments'   => AttachmentsResource::collection(IndexAttachments::run($tradeUnit))->resolve() */
        ];
    }
}
