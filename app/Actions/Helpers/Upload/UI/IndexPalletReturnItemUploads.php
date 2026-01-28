<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-16h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-13h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Helpers\Upload\UI;

use App\Http\Resources\Helpers\PalletReturnItemUploadsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Helpers\Upload;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IndexPalletReturnItemUploads
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(Fulfilment|FulfilmentCustomer $parent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Upload::class);
        $queryBuilder->where('model', 'PalletReturnItem');
        $queryBuilder->where('parent_type', 'PalletReturn');

        if ($parent instanceof FulfilmentCustomer) {
            $queryBuilder->where('uploads.customer_id', $parent->customer_id);
        } elseif ($parent instanceof Fulfilment) {
            $queryBuilder->where('uploads.shop_id', $parent->shop_id);
        }
        /*
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                prefix: $prefix,
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }
        */

        return $queryBuilder
            ->defaultSort('uploads.id')
            ->allowedSorts(['uploads.original_filename', 'number_rows', 'number_success', 'number_fails', 'created_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $collection, ActionRequest $request): Response
    {
        return Inertia::render(
            'Uploads/UploadRecords',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Upload Records'),
                'pageHead'    => [
                    'icon'          => ['fal', 'fa-upload'],
                    'model'         => __('Upload'),
                    'title'         => __('Records'),
                    'iconRight'     => 'fal fa-history'
                ],
                'data'        => PalletReturnItemUploadsResource::collection($collection),

            ]
        )->table($this->tableStructure());
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                            'title'       => __("No upload records found"),
                            'count'       => 0
                        ]
                )
                ->column(key: 'original_filename', label: __('filename'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_rows', label: __('rows'), canBeHidden: false, searchable: true)
                ->column(key: 'number_success', label: __('success'), canBeHidden: false, searchable: true)
                ->column(key: 'number_fails', label: __('fails'), canBeHidden: false, searchable: true)
                ->column(key: 'uploaded_at', label: __('uploaded at'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $collection): AnonymousResourceCollection
    {
        return PalletReturnItemUploadsResource::collection($collection);
    }

    public function getBreadcrumbs(): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-tachometer-alt-fast',
                    'label' => 'Upload Records',
                    'route' => [
                        'name' => 'grp.helpers.uploads.records.show',

                        'parameters' => request()->route()->originalParameters()
                    ]
                ]

            ],

        ];
    }

    public function asController(Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($fulfilment);
    }

    public function inFulfilmentCustomer(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($fulfilmentCustomer);
    }
}
