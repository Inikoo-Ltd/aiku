<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:37:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI;

use App\Actions\RetinaAction;
use App\Http\Resources\Helpers\UploadsResource;
use App\Models\CRM\WebUser;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Upload;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IndexRetinaRecentCustomerSalesChannelPortfolioUploads extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    public function handle(CustomerSalesChannel $customerSalesChannel, WebUser $webUser): array|Collection
    {
        $upload = Upload::where('web_user_id', $webUser->id)
            ->where('parent_id', $customerSalesChannel->id)
            ->where('parent_type', $customerSalesChannel->getMorphClass())
            ->whereDay('created_at', today());

        return $upload->orderBy('created_at', 'DESC')->get();
    }

    public function jsonResponse(Collection $collection): JsonResource
    {
        return UploadsResource::collection($collection);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): array|Collection
    {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request->user());
    }
}
