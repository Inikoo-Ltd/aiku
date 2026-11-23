<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\Api\Group\WithMobileDateRange;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileReturns
{
    use AsController;
    use WithMobileDateRange;
    use WithMobileReturns;

    public const array SORTS = [
        'created_at' => 'return_delivery_notes.created_at',
        'reference'  => 'return_delivery_notes.reference',
        'customer'   => 'customers.name',
    ];

    public const string TO_PROCESS = 'to_process';

    public const array TO_PROCESS_STATES = [
        ReturnDeliveryNoteStateEnum::RECEIVED,
        ReturnDeliveryNoteStateEnum::RETURNING,
    ];

    /**
     * @param array{state?: string|null, q?: string|null, from?: string|null, to?: string|null, sort?: string|null, per_page?: int|null, page?: int|null} $filters
     */
    public function handle(Warehouse $warehouse, array $filters): LengthAwarePaginator
    {
        $state      = $filters['state'] ?? 'all';
        $search     = trim((string) ($filters['q'] ?? ''));
        $sort       = $filters['sort'] ?? '-created_at';
        $descending = str_starts_with($sort, '-');

        $query = self::returnsQuery($warehouse, $state)
            ->leftJoin('customers', 'customers.id', '=', 'return_delivery_notes.customer_id')
            ->select('return_delivery_notes.*')
            ->when($search !== '', fn ($query) => $query->whereWith('return_delivery_notes.reference', $search));

        $this->whereWithinDays($query, 'return_delivery_notes.created_at', $warehouse, $filters['from'] ?? null, $filters['to'] ?? null);

        return $query
            ->withCount([
                'returnDeliveryNoteItem as number_items',
                'returnDeliveryNoteItem as number_handled' => fn ($query) => $query->where('is_handled', true),
            ])
            ->with(['customer', 'deliveryNote', 'handlerUser'])
            ->orderByRaw(self::SORTS[ltrim($sort, '-')].($descending ? ' desc nulls last' : ' asc nulls last'))
            ->orderBy('return_delivery_notes.id', $descending ? 'desc' : 'asc')
            ->paginate(min((int) ($filters['per_page'] ?? 0), self::WEB_MAX_PER_PAGE) ?: (int) config('ui.table.records_per_page'), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    public static function returnsQuery(Warehouse $warehouse, string $state): Builder
    {
        return ReturnDeliveryNote::query()
            ->leftJoin('shops', 'return_delivery_notes.shop_id', '=', 'shops.id')
            ->where('return_delivery_notes.warehouse_id', $warehouse->id)
            ->where('shops.is_aiku', true)
            ->when($state === self::TO_PROCESS, fn (Builder $query) => $query->whereIn('return_delivery_notes.state', self::TO_PROCESS_STATES))
            ->when(!in_array($state, ['all', self::TO_PROCESS], true), fn (Builder $query) => $query->where('return_delivery_notes.state', $state));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $returns): array
    {
        return collect($returns->items())->map(fn (ReturnDeliveryNote $returnDeliveryNote) => [
            'id'             => $returnDeliveryNote->id,
            'reference'      => $returnDeliveryNote->reference,
            'type'           => $returnDeliveryNote->type->value,
            'type_label'     => ReturnDeliveryNoteTypeEnum::labels()[$returnDeliveryNote->type->value],
            'state'          => $returnDeliveryNote->state->value,
            'state_label'    => ReturnDeliveryNoteStateEnum::labels()[$returnDeliveryNote->state->value],
            'created_at'     => $this->isoDateTime($returnDeliveryNote->created_at),
            'customer'       => $returnDeliveryNote->customer ? ['name' => $returnDeliveryNote->customer->name] : null,
            'delivery_note'  => $this->returnDeliveryNoteReference($returnDeliveryNote),
            'number_items'   => (int) $returnDeliveryNote->getAttribute('number_items'),
            'number_handled' => (int) $returnDeliveryNote->getAttribute('number_handled'),
            'handler'        => $this->userReference($returnDeliveryNote->handlerUser),
        ])->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function counts(Warehouse $warehouse): array
    {
        $stats  = $warehouse->stats;
        $counts = [];
        foreach (ReturnDeliveryNoteStateEnum::cases() as $state) {
            $counts[$state->value] = (int) $stats?->{'number_return_delivery_notes_state_'.$state->value};
        }
        $counts[self::TO_PROCESS] = collect(self::TO_PROCESS_STATES)->sum(fn (ReturnDeliveryNoteStateEnum $state) => $counts[$state->value]);
        $counts['all']            = (int) $stats?->number_return_delivery_notes;

        return $counts;
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewReturns($user, $warehouse);

        $filters = Validator::make($request->query(), [
            'state'    => ['sometimes', 'nullable', Rule::in([...ReturnDeliveryNoteStateEnum::values(), self::TO_PROCESS, 'all'])],
            'q'        => ['sometimes', 'nullable', 'string', 'max:64'],
            ...$this->dateRangeRules($request->query()),
            'sort'     => ['sometimes', 'nullable', Rule::in(collect(array_keys(self::SORTS))->flatMap(fn (string $sort) => [$sort, '-'.$sort])->all())],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::WEB_MAX_PER_PAGE],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
        ])->validate();

        $returns = $this->handle($warehouse, $filters);

        return response()->json([
            'data'   => $this->rows($returns),
            'meta'   => $this->paginationMeta($returns),
            'counts' => $this->counts($warehouse),
        ]);
    }
}
