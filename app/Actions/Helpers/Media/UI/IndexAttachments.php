<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-13h-09m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Helpers\Media\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SupplyChain\Supplier;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexAttachments extends OrgAction
{
    protected Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|PalletReturn|PalletDelivery|Order $parent;

    public function handle(Employee|TradeUnit|Supplier|Customer|PurchaseOrder|StockDelivery|Order|PalletDelivery|PalletReturn $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('media.name', $value)
                    ->orWhereStartWith('media.slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Media::class);

        $queryBuilder->join('model_has_attachments', 'model_has_attachments.media_id', '=', 'media.id')
                ->where('model_has_attachments.model_type', class_basename($parent))
                ->where('model_has_attachments.model_id', $parent->id);

        $queryBuilder
            ->defaultSort('model_has_attachments.id')
            ->select([
                'model_has_attachments.id',
                'model_has_attachments.caption',
                'model_has_attachments.scope',
                'media.id as media_id',
                'media.ulid as media_ulid'
            ]);

        return $queryBuilder->allowedSorts(['caption', 'scope'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }

            $noResults = __("No attachments found");


            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('attachment'),__('attachments')])
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => 0,
                    ]
                );


            $table->column(key: 'scope', label: __('Scope'), canBeHidden: false, searchable: true);
            $table->column(key: 'caption', label: __('Caption'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'action', label: __('Actions'), canBeHidden: false, sortable: false, searchable: false);
        };
    }



    public function jsonResponse(LengthAwarePaginator $attachments): AnonymousResourceCollection
    {
        return AttachmentsResource::collection($attachments);
    }
}
