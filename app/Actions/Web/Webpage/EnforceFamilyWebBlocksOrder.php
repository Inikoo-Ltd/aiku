<?php

namespace App\Actions\Web\Webpage;

use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class EnforceFamilyWebBlocksOrder
{
    use AsAction;

    public const array LOCKED_FAMILY_CODES = [
        'family-2' => 'family-2-extra-description',
        'family-3' => 'family-3-extra-description',
    ];

    public function handle(Webpage $webpage): void
    {
        $blocks = $webpage->modelHasWebBlocks()
            ->with('webBlock.webBlockType:id,code')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $codeOf = fn (ModelHasWebBlocks $block) => $block->webBlock?->webBlockType?->code;

        $familyBlock = $blocks->first(fn (ModelHasWebBlocks $block) => array_key_exists($codeOf($block), self::LOCKED_FAMILY_CODES));
        if (!$familyBlock) {
            return;
        }

        $extraDescriptionCode = self::LOCKED_FAMILY_CODES[$codeOf($familyBlock)];
        $productsCodes        = WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes();

        $lockedBlocks = collect([
            $familyBlock,
            $blocks->first(fn (ModelHasWebBlocks $block) => in_array($codeOf($block), $productsCodes)),
            $blocks->first(fn (ModelHasWebBlocks $block) => $codeOf($block) === $extraDescriptionCode),
        ])->filter();

        $lockedIds = $lockedBlocks->pluck('id');

        $lockedBlocks
            ->concat($blocks->reject(fn (ModelHasWebBlocks $block) => $lockedIds->contains($block->id)))
            ->values()
            ->each(function (ModelHasWebBlocks $block, int $position) {
                if ((int) $block->position !== $position) {
                    $block->update(['position' => $position]);
                }
            });
    }
}
