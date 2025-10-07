<?php

namespace App\Actions\Traits;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;

trait HasBucketAttachment
{
    public function getAttachmentData(MasterAsset|Product|TradeUnit $model): array
    {
        $public = [
            [
                'label' => __('IFRA'),
                'scope' => 'IFRA',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::IFRA)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('SDS'),
                'scope' => 'SDS',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::SDS)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('Allergen Declarations'),
                'scope' => 'allergen_declarations',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('Declaration of Conformity'),
                'scope' => 'declaration_of_conformity',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::DOC)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('CPSR'),
                'scope' => 'CPSR',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::CPSR)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
        ];

        $private = [
            [
                'label' => __('UFRA Private'),
                'scope' => 'ifra_private',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::IFRA_PRIVATE)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('SDS Private'),
                'scope' => 'sds_private',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::SDS_PRIVATE)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('Allergen Declarations Private'),
                'scope' => 'allergen_declarations_private',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS_PRIVATE)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('DOC Private'),
                'scope' => 'doc_private',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::DOC_PRIVATE)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('CPSR Private'),
                'scope' => 'cpsr_private',
                'id'    => $model->attachments()->wherePivot('scope', TradeAttachmentScopeEnum::CPSR_PRIVATE)->first()?->id,
                'file'  => null,
                'size'  => '2kb',
            ],
        ];

        return [
            'public'  => $public,
            'private' => $private,
        ];
    }
}
