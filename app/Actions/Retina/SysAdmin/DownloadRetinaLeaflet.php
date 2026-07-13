<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 19:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Retina\SysAdmin;

use App\Actions\RetinaAction;
use App\Models\Billables\ModelHasLeaflet;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadRetinaLeaflet extends RetinaAction
{
    public function handle(ModelHasLeaflet $customerLeaflet): StreamedResponse
    {
        $media    = $customerLeaflet->media;
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);

        return Storage::disk($media->disk)->download(
            $media->getPathRelativeToRoot(),
            "{$media->name}.{$extension}"
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(ModelHasLeaflet $customerLeaflet, ActionRequest $request): StreamedResponse
    {
        $this->initialisation($request);

        abort_unless(
            $customerLeaflet->model_type === 'Customer'
                && $customerLeaflet->model_id === $this->customer->id
                && $customerLeaflet->media_id !== null,
            404
        );

        return $this->handle($customerLeaflet);
    }
}
