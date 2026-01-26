<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Aug 2023 08:09:28 Malaysia Time, Pantai Lembeng, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Upload\UI;

use App\Http\Resources\Helpers\UploadRecordsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Upload;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IndexUploadRecords
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(Upload $upload, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for($upload->records());

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
            ->defaultSort('upload_records.id')
            ->with('excel')
            ->allowedSorts(['uploads.original_filename', 'row_number', 'status', 'created_at'])
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
                'data'        => UploadRecordsResource::collection($collection),

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
                ->column(key: 'row_number', label: __('row number'), canBeHidden: false, searchable: true)
                ->column(key: 'errors', label: __('errors'), canBeHidden: false, searchable: true)
                ->column(key: 'fail_column', label: __('fail column'), canBeHidden: false, searchable: true)
                ->column(key: 'status', label: __('status'), canBeHidden: false, searchable: true)
                ->column(key: 'created_at', label: __('created_at'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $collection): AnonymousResourceCollection
    {
        return UploadRecordsResource::collection($collection);
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

    public function asController(Upload $upload, ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($upload);
    }
}
