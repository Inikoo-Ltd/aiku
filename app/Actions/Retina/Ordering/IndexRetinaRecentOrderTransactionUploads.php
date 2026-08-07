<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ordering;

use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\UploadsResource;
use App\Models\CRM\WebUser;
use App\Models\Helpers\Upload;
use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaRecentOrderTransactionUploads extends RetinaAction
{
    public function handle(Order $order, WebUser $webUser): Collection
    {
        return Upload::where('web_user_id', $webUser->id)
            ->where('parent_id', $order->id)
            ->where('parent_type', $order->getMorphClass())
            ->whereDate('created_at', today())
            ->orderBy('created_at')
            ->get();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route()->parameter('order')->customer_id === $request->user()->customer_id;
    }

    public function jsonResponse(Collection $collection): JsonResource
    {
        return UploadsResource::collection($collection);
    }

    public function asController(Order $order, ActionRequest $request): Collection
    {
        $this->initialisation($request);

        return $this->handle($order, $request->user());
    }
}
