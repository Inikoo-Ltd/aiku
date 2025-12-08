<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-09h-06m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Helpers\Upload\UI;

use App\Actions\RetinaAction;
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

class IndexRetinaUploadRecords extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    private bool $action = false;

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
                'title' => __('Upload Records'),
                'pageHead' => [
                    'icon' => ['fal', 'fa-upload'],
                    'model' => __('Upload'),
                    'title' => __('Records'),
                    'iconRight' => 'fal fa-history',
                ],
                'data' => UploadRecordsResource::collection($collection),

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
                        'title' => __('No upload records found'),
                        'count' => 0,
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

                'type' => 'simple',
                'simple' => [
                    'icon' => 'fal fa-tachometer-alt-fast',
                    'label' => 'Upload Records',
                    'route' => [
                        'name' => 'retina.helpers.uploads.records.show',
                        'parameters' => request()->route()->originalParameters(),
                    ],
                ],

            ],

        ];
    }

    public function asController(Upload $upload, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($upload);
    }
}
