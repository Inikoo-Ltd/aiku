<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-51m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Search;

use App\Actions\Api\Group\GoodsOut\GetMobileScan;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileSearch
{
    use AsController;
    use WithMobileSearch;

    public const int MIN_QUERY_LENGTH = 2;
    public const int MAX_QUERY_LENGTH = 64;
    public const int ROWS_PER_TYPE = 5;
    public const int ROWS_PER_PAGE = 20;

    /**
     * @param array<int, string> $searchableTypes
     * @return array{query: string, types: array<int, string>, exact: array<string, mixed>|null, groups: array<int, array{type: string, label: string, has_more: bool, rows: array<int, array<string, mixed>>}>}
     */
    public function handle(Warehouse $warehouse, User $user, array $searchableTypes, string $query, string $type = 'all', int $page = 1): array
    {
        $query  = trim($query);
        $result = [
            'query'  => $query,
            'types'  => $searchableTypes,
            'exact'  => null,
            'groups' => [],
        ];

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return $result;
        }

        $searchedTypes = $type === 'all' ? $searchableTypes : array_values(array_intersect([$type], $searchableTypes));
        $limit         = $type === 'all' ? self::ROWS_PER_TYPE : self::ROWS_PER_PAGE;
        $offset        = $type === 'all' ? 0 : ($page - 1) * self::ROWS_PER_PAGE;
        $labels        = $this->searchTypeLabels();

        $result['exact'] = $this->exactRow($warehouse, $user, $query, $searchedTypes);

        foreach ($searchedTypes as $searchedType) {
            $models = $this->searchMatchesQuery($searchedType, $warehouse, $query)
                ->offset($offset)
                ->limit($limit + 1)
                ->get();

            if ($models->isEmpty()) {
                continue;
            }

            $result['groups'][] = [
                'type'     => $searchedType,
                'label'    => $labels[$searchedType],
                'has_more' => $models->count() > $limit,
                'rows'     => $models->take($limit)->map(fn (Model $model) => $this->searchRow($searchedType, $model))->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, string> $searchedTypes
     * @return array<string, mixed>|null
     */
    private function exactRow(Warehouse $warehouse, User $user, string $query, array $searchedTypes): ?array
    {
        $scannableTypes = array_intersect(['skos', 'locations', 'delivery_notes', 'stock_deliveries', 'pallets', 'pallet_deliveries'], $searchedTypes);
        if ($scannableTypes === []) {
            return null;
        }

        $scan = app(GetMobileScan::class)->handle($warehouse, $user, $query);
        $type = array_search($scan['type'], self::SEARCH_TYPES, true);

        if ($type === false || !in_array($type, $scannableTypes, true)) {
            return null;
        }

        $model = $this->searchTypeQuery($type, $warehouse)->whereKey($scan[$scan['type']]['id'])->first();

        return $model ? $this->searchRow($type, $model) : null;
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->authorisedWarehouses()->whereKey($warehouse->id)->exists(), 404);

        $validated = Validator::make($request->query(), [
            'q'    => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_QUERY_LENGTH],
            'type' => ['sometimes', 'nullable', Rule::in(['all', ...array_keys(self::SEARCH_TYPES)])],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $searchableTypes = $this->searchableTypes($user, $warehouse);
        $type            = $validated['type'] ?? 'all';

        abort_if($searchableTypes === [], 403);
        abort_unless($type === 'all' || in_array($type, $searchableTypes, true), 403);

        return response()->json([
            'data' => $this->handle($warehouse, $user, $searchableTypes, (string) ($validated['q'] ?? ''), $type, (int) ($validated['page'] ?? 1)),
        ]);
    }
}
