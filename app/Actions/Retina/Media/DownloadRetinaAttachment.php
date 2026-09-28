<?php

/*
 * author Arya Permana - Kirin
 * created on 14-02-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Media;

use App\Actions\Iris\Media\DownloadIrisAttachment;
use App\Models\CRM\Customer;
use App\Models\Helpers\Media;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadRetinaAttachment
{
    use AsAction;

    public function handle(Media $media): BinaryFileResponse
    {
        return DownloadIrisAttachment::make()->handle($media);
    }

    public function asController(Media $media, ActionRequest $request): BinaryFileResponse
    {
        $customer = $request->user()?->customer;

        if (!DownloadIrisAttachment::isPublic($media) && !($customer && $this->isAttachedToCustomerModel($media, $customer))) {
            abort(404);
        }

        return $this->handle($media);
    }

    private function isAttachedToCustomerModel(Media $media, Customer $customer): bool
    {
        $fulfilmentCustomerId = $customer->fulfilmentCustomer?->id;

        return DB::table('model_has_attachments')
            ->where('media_id', $media->id)
            ->where(function (Builder $query) use ($customer, $fulfilmentCustomerId) {
                $query->where(function (Builder $query) use ($customer) {
                    $query->where('model_type', 'Order')
                        ->whereIn('model_id', DB::table('orders')->where('customer_id', $customer->id)->select('id'));
                });

                if ($fulfilmentCustomerId) {
                    foreach (['PalletDelivery' => 'pallet_deliveries', 'PalletReturn' => 'pallet_returns'] as $modelType => $table) {
                        $query->orWhere(function (Builder $query) use ($modelType, $table, $fulfilmentCustomerId) {
                            $query->where('model_type', $modelType)
                                ->whereIn('model_id', DB::table($table)->where('fulfilment_customer_id', $fulfilmentCustomerId)->select('id'));
                        });
                    }
                }
            })
            ->exists();
    }
}
