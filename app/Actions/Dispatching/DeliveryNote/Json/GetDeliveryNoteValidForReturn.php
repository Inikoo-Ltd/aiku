<?php

namespace App\Actions\Dispatching\DeliveryNote\Json;

use App\Actions\OrgAction;
use App\Actions\Search\WithTypesenseApi;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNote\DeliveryNotesForSelectResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Throwable;

class GetDeliveryNoteValidForReturn extends OrgAction
{
    use WithTypesenseApi;

    protected const int HITS_LIMIT = 30;

    /**
     * What is written on a returned box is handwritten, partial or both, so the index arm
     * tolerates typos from five characters on while the SQL arm still catches fragments
     * ("0706" inside "GB550706") that an index can never reach. Neighbouring matches coming
     * along is fine: the operator confirms against the label.
     */
    public const array SEARCH_TUNING = [
        'typo_tokens_threshold' => 2,
        'min_len_1typo'         => 5,
        'min_len_2typo'         => 8,
        'num_typos'             => 2,
    ];

    public function handle(Warehouse $warehouse): LengthAwarePaginator
    {
        $term = request()->input('filter.global', '');
        $term = is_array($term) ? implode(' ', $term) : (string) $term;
        $ids  = $this->indexHits($term, $warehouse->organisation_id);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) use ($ids) {
            $value = is_array($value) ? implode(' ', $value) : $value;

            $query->where(function ($query) use ($value, $ids) {
                if ($ids) {
                    $query->whereIn('delivery_notes.id', $ids);
                }
                $query->orWhereWith('delivery_notes.reference', $value)
                    ->orWhereWith('delivery_notes.tracking_number', $value)
                    ->orWhereWith('delivery_notes.contact_name', $value)
                    ->orWhereWith('delivery_notes.company_name', $value)
                    ->orWhereWith('customers.reference', $value)
                    ->orWhereWith('customers.name', $value)
                    ->orWhereWith('customers.company_name', $value)
                    ->orWhereWith('customers.contact_name', $value)
                    ->orWhereWith('addresses.address_line_1', $value)
                    ->orWhereWith('addresses.address_line_2', $value)
                    ->orWhereWith('addresses.locality', $value)
                    ->orWhereWith('addresses.postal_code', $value)
                    ->orWhereHas('orders', function ($orders) use ($value) {
                        $orders->where(function ($orders) use ($value) {
                            $orders->whereWith('orders.reference', $value)
                                ->orWhereWith('orders.customer_reference', $value)
                                ->orWhereWith('orders.external_id', $value)
                                ->orWhereWith('orders.tracking_number', $value);
                        });
                    })
                    ->orWhereHas('shipments', function ($shipments) use ($value) {
                        $shipments->where(function ($shipments) use ($value) {
                            $shipments->whereWith('shipments.tracking', $value)
                                ->orWhereWith('shipments.reference', $value);
                        });
                    });
            });
        });

        $query = QueryBuilder::for(DeliveryNote::class);
        $query->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id');
        $query->leftJoin('customers', 'delivery_notes.customer_id', '=', 'customers.id');
        $query->leftJoin('addresses', 'delivery_notes.address_id', '=', 'addresses.id');

        $query->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED);
        $query->where('delivery_notes.organisation_id', $warehouse->organisation_id);
        $query->where('delivery_notes.is_returned', false);

        $query->where('shops.is_aiku', true);

        if ($ids) {
            $query->orderByRaw('array_position(ARRAY['.implode(',', $ids).']::bigint[], delivery_notes.id) NULLS LAST');
        }

        $selectColumns = [
            'delivery_notes.id',
            'delivery_notes.reference',
            'delivery_notes.date',
            'delivery_notes.slug',
            'delivery_notes.contact_name',
            'delivery_notes.company_name',
            'delivery_notes.tracking_number',
            'customers.name as customer_name',
            'customers.reference as customer_reference',
        ];

        return $query
            ->defaultSort('-delivery_notes.date')
            ->select($selectColumns)
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @return array<int, int>
     */
    public function indexHits(string $query, int $organisationId): array
    {
        if (config('scout.driver') !== 'typesense' || trim($query) === '') {
            return [];
        }

        try {
            $response = $this->typesenseClient()
                ->post($this->typesenseUrl().'/multi_search', ['searches' => [self::searchParameters($query, $organisationId)]])
                ->throw();

            if ($error = $response->json('results.0.error')) {
                logger()->warning("Typesense return delivery note search failed: $error");

                return [];
            }

            return array_values(array_unique(array_map('intval', Arr::pluck($response->json('results.0.hits', []), 'document.id'))));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function searchParameters(string $query, int $organisationId): array
    {
        return array_merge(
            [
                'collection'             => (new DeliveryNote())->searchableAs(),
                'q'                      => $query,
                'query_by'               => 'reference,tracking,order_references,customer_reference,company_name,contact_name,customer_name,address',
                'filter_by'              => 'organisation_id:='.$organisationId.' && state:='.DeliveryNoteStateEnum::DISPATCHED->value,
                'per_page'               => self::HITS_LIMIT,
                'page'                   => 1,
                'prioritize_exact_match' => true,
                'prefix'                 => true,
                'enable_synonyms'        => false,
                'enable_overrides'       => false,
            ],
            self::SEARCH_TUNING
        );
    }

    public function jsonResponse(LengthAwarePaginator $deliveryNotes): AnonymousResourceCollection
    {
        return DeliveryNotesForSelectResource::collection($deliveryNotes);
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }
}
