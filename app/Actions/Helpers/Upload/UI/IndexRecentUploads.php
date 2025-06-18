<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Jan 2025 14:19:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Upload\UI;

use App\Http\Resources\Helpers\UploadsResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Helpers\Upload;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IndexRecentUploads
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(string $class, $argument): array|Collection
    {
        $upload = Upload::where('model', $class);

        if (!blank($argument)) {
            $upload->where(Arr::get($argument, 'key'), Arr::get($argument, 'value'));
        }

        return $upload->orderBy('id', 'DESC')->limit(4)->get()->reverse();
    }

    public function jsonResponse(Collection $collection): JsonResource
    {
        return UploadsResource::collection($collection);
    }

    public function inPalletDelivery(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletDelivery $palletDelivery, ActionRequest $request): array|Collection
    {

        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(class_basename(Pallet::class), [
            'user_id'   =>  $request->user()->id

        ]);
    }

    public function inPalletReturn(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn, ActionRequest $request): array|Collection
    {
        return $this->handle(class_basename(PalletReturnItem::class), [
            'key'   => 'user_id',
            'value' => $request->user()->id
        ]);
    }

    public function inPalletRetina(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletDelivery $palletDelivery, ActionRequest $request): array|Collection
    {
        return $this->handle(class_basename(Pallet::class), [
            'key'   => 'web_user_id',
            'value' => $request->user()->id
        ]);
    }

    public function inPalletReturnRetina(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn, ActionRequest $request): array|Collection
    {
        return $this->handle(class_basename(PalletReturnItem::class), [
            'key'   => 'web_user_id',
            'value' => $request->user()->id
        ]);
    }

    public function inOrderRetina(Order $order, ActionRequest $request): array|Collection
    {
        return $this->handle(class_basename(Transaction::class), [
            'key'   => 'web_user_id',
            'value' => $request->user()->id
        ]);
    }

    public function inEmployee(Organisation $organisation, ActionRequest $request): array|Collection
    {
        return $this->handle(class_basename(Employee::class), [
            'key'   => 'user_id',
            'value' => $request->user()->id
        ]);
    }


}
