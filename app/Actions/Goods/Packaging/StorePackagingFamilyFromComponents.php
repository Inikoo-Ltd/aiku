<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Enums\Goods\Packaging\PackagingFamilySourceEnum;
use App\Enums\Goods\Packaging\PackagingPolymerEnum;
use App\Models\Goods\PackagingComponent;
use App\Models\Goods\PackagingFamily;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The packaging family for a set of components read from a supplier sheet. Packaging is entered once: a part
 * packed exactly like one already known gets that family, and a component already known (the same bottle,
 * the same carton) is shared rather than copied.
 */
class StorePackagingFamilyFromComponents
{
    use AsAction;

    public const array COMPONENT_FIELDS = [
        'packaging_level', 'name', 'material', 'material_id_code', 'material_category', 'weight_g', 'recycled_content_pct',
        'recycled_content_evidence', 'recyclability', 'separable', 'marks', 'national_marks', 'artwork_owner', 'notes',
    ];

    /**
     * @param list<array<string, mixed>> $components as CheckSupplierProductSheet::packaging() returns them
     */
    public function handle(Group $group, string $code, ?string $name, array $components): PackagingFamily
    {
        $lines = [];
        foreach ($components as $component) {
            $attributes = Arr::only($component, self::COMPONENT_FIELDS);
            $signature  = $this->signature($attributes);
            $lines[$signature] ??= ['attributes' => $attributes, 'quantity' => 0.0, 'quantity_per_unit' => 0.0];
            $lines[$signature]['quantity']          += (float)$component['quantity'];
            $lines[$signature]['quantity_per_unit'] += (float)$component['quantity_per_unit'];
        }
        ksort($lines);

        $familySignature = sha1(json_encode(array_map(fn (array $line) => [$line['quantity'], round($line['quantity_per_unit'], 6)], $lines)));

        $family = PackagingFamily::where('group_id', $group->id)->where('signature', $familySignature)->first();
        if ($family) {
            return $family;
        }

        $family = PackagingFamily::create([
            'group_id'  => $group->id,
            'code'      => $code,
            'name'      => $name,
            'signature' => $familySignature,
            'source'    => PackagingFamilySourceEnum::SUPPLIER,
        ]);

        foreach ($lines as $signature => $line) {
            $packagingComponent = PackagingComponent::firstOrCreate(
                ['group_id' => $group->id, 'signature' => $signature],
                [...$line['attributes'], 'polymer' => PackagingPolymerEnum::fromMaterialCode($line['attributes']['material_id_code'] ?? null)]
            );
            $family->components()->attach($packagingComponent->id, [
                'quantity'          => $line['quantity'],
                'quantity_per_unit' => round($line['quantity_per_unit'], 6),
            ]);
        }

        return $family;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function signature(array $attributes): string
    {
        $normalised = array_map(fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : $value, $attributes);
        ksort($normalised);

        return sha1(json_encode($normalised));
    }
}
