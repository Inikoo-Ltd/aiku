<?php

namespace App\Actions\Traits;

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
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('SDS'),
                'scope' => 'SDS',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('Allergen Declarations'),
                'scope' => 'allergen_declarations',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('Declaration of Conformity'),
                'scope' => 'declaration_of_conformity',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('CPSR'),
                'scope' => 'CPSR',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
        ];

        $private = [
            [
                'label' => __('UFRA Private'),
                'scope' => 'ifra_private',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('SDS Private'),
                'scope' => 'sds_private',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('Allergen Declarations Private'),
                'scope' => 'allergen_declarations_private',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('CPSR Private'),
                'scope' => 'cpsr_private',
                'id'    => null,
                'file'  => null,
                'size'  => '2kb',
            ],
            [
                'label' => __('DOC Private'),
                'scope' => 'doc_private',
                'id'    => null,
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
