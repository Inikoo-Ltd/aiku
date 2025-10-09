<?php

namespace App\Actions\Traits;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Masters\MasterAsset;

trait HasBucketAttachment
{
    public function getAttachmentData(MasterAsset|Product|TradeUnit|TradeUnitFamily $model): array
    {
        $attachments = $model->attachments()->get()->keyBy(fn($att) => $att->pivot->scope);
        // dd($model->id);
        $attachmentConfigs = [
            'public' => [
                ['label' => __('IFRA'), 'scope' => 'ifra', 'enum' => TradeAttachmentScopeEnum::IFRA],
                ['label' => __('SDS'), 'scope' => 'sds', 'enum' => TradeAttachmentScopeEnum::SDS],
                ['label' => __('Allergen Declarations'), 'scope' => 'allergen_declarations', 'enum' => TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS],
                ['label' => __('Declaration of Conformity'), 'scope' => 'doc', 'enum' => TradeAttachmentScopeEnum::DOC],
                ['label' => __('CPSR'), 'scope' => 'cpsr', 'enum' => TradeAttachmentScopeEnum::CPSR],
            ],
            'private' => [
                ['label' => __('IFRA Private'), 'scope' => 'ifra_private', 'enum' => TradeAttachmentScopeEnum::IFRA_PRIVATE],
                ['label' => __('SDS Private'), 'scope' => 'sds_private', 'enum' => TradeAttachmentScopeEnum::SDS_PRIVATE],
                ['label' => __('Allergen Declarations Private'), 'scope' => 'allergen_declarations_private', 'enum' => TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS_PRIVATE],
                ['label' => __('DOC Private'), 'scope' => 'doc_private', 'enum' => TradeAttachmentScopeEnum::DOC_PRIVATE],
                ['label' => __('CPSR Private'), 'scope' => 'cpsr_private', 'enum' => TradeAttachmentScopeEnum::CPSR_PRIVATE],
            ],
        ];

        $mapAttachments = function($configs) use ($attachments) {
            return array_map(function($config) use ($attachments) {
                $attachment = $attachments->get($config['enum']->value ?? $config['enum']);
                return [
                    'label'            => $config['label'],
                    'scope'            => $config['scope'],
                    'attachment'       => $attachment ?? null,
                    'download_route'  =>  [
                        'name'       => 'grp.media.download',
                        'parameters' => [
                            'media' => $attachment->ulid ?? null,
                        ],
                        'method'     => 'get'
                    ],
                ];
            }, $configs);
        };
        
        $public = $mapAttachments($attachmentConfigs['public']);
        $private = $mapAttachments($attachmentConfigs['private']);

        return [
            'public'  => $public,
            'private' => $private,
        ];
    }
}
