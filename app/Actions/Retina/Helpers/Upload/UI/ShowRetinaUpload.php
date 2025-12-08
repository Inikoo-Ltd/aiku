<?php

/*
 * author Arya Permana - Kirin
 * created on 22-01-2025-09h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Helpers\Upload\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\UploadRecordsResource;
use App\Models\Helpers\Upload;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ShowRetinaUpload extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    private bool $action = false;

    public function handle(Upload $upload, ?string $prefix = null): Upload
    {
        return $upload;
    }

    public function htmlResponse(Upload $upload, ActionRequest $request): Response
    {
        return Inertia::render(
            'Uploads/RetinaUploadRecords',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title' => __('Upload Records'),
                'pageHead' => [
                    'icon' => ['fal', 'fa-upload'],
                    'model' => __('Upload'),
                    'title' => __('Records'),
                    'iconRight' => 'fal fa-history',
                ],
                'data' => UploadRecordsResource::collection(IndexRetinaUploadRecords::run($upload)),
            ]
        )->table(IndexRetinaUploadRecords::make()->tableStructure());
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

    public function asController(Upload $upload, ActionRequest $request): Upload
    {
        $this->initialisation($request);

        return $this->handle($upload);
    }
}
