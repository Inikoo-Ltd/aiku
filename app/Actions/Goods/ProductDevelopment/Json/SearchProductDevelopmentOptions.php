<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-18h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\PackagingFamily;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;

class SearchProductDevelopmentOptions extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const array SCOPES = ['trade_units', 'trade_unit_families', 'packaging_families'];

    public const int LIMIT = 20;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->canCreateProductDevelopment($request->user())) {
            return true;
        }

        $productDevelopment = $request->route('scope') === 'trade_units' && $request->integer('product_development')
            ? ProductDevelopment::where('group_id', $request->user()->group_id)->find($request->integer('product_development'))
            : null;

        return $productDevelopment && $this->productDevelopmentAccess()->canManageSamples($request->user(), $productDevelopment);
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function handle(Group $group, string $scope, string $query): array
    {
        $model = match ($scope) {
            'trade_units' => TradeUnit::class,
            'trade_unit_families' => TradeUnitFamily::class,
            'packaging_families' => PackagingFamily::class,
        };

        return $model::query()
            ->where('group_id', $group->id)
            ->when($query !== '', fn (Builder $builder) => $builder->where(fn (Builder $builder) => $builder
                ->whereStartWith('code', $query)
                ->orWhereAnyWordStartWith('name', $query)))
            ->orderBy('code')
            ->limit(self::LIMIT)
            ->get(['id', 'code', 'name'])
            ->map(fn ($record) => [
                'value' => $record->id,
                'label' => trim($record->code.' · '.$record->name, ' ·'),
                'code'  => $record->code,
                'name'  => $record->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function asController(string $scope, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($this->group, $scope, trim((string) ($request->input('filter.global') ?? $request->query('q', ''))));
    }
}
