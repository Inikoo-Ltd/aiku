<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Aug 2023 08:09:28 Malaysia Time, Pantai Lembeng, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Upload\UI;

use App\Http\Resources\Helpers\UploadRecordsResource;
use App\Models\Helpers\Upload;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ShowUpload
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(Upload $upload, ?string $prefix = null): Upload
    {
        return $upload;
    }

    public function htmlResponse(Upload $upload, ActionRequest $request): Response
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
                'data'        => UploadRecordsResource::collection(IndexUploadRecords::run($upload)),
            ]
        )->table(IndexUploadRecords::make()->tableStructure());
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

    public function asController(Upload $upload, ActionRequest $request): Upload
    {
        return $this->handle($upload);
    }
}
