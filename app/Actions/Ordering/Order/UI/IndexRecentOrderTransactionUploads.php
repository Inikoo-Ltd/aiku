<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:37:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Helpers\UploadsResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Upload;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IndexRecentOrderTransactionUploads extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private Shop $parent;

    public function handle(Order $order, User $user): array|Collection
    {
        $upload = Upload::where('user_id', $user->id)->where('parent_id', $order->id)
            ->where('parent_type', $order->getMorphClass())
        ->whereDay('created_at', today());

        return $upload->orderBy('created_at', 'DESC')->get()->reverse();
    }

    public function jsonResponse(Collection $collection): JsonResource
    {
        return UploadsResource::collection($collection);
    }

    public function asController(Order $order, ActionRequest $request): array|Collection
    {
        $this->parent = $order->shop;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $request->user());
    }
}
