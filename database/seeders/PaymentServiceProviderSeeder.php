<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Sep 2023 20:56:02 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Seeders;

use App\Actions\Accounting\PaymentServiceProvider\StorePaymentServiceProvider;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum;
use App\Models\Accounting\PaymentServiceProvider;
use Illuminate\Database\Seeder;

class PaymentServiceProviderSeeder extends Seeder
{
    use WithAttachMediaToModel;

    public function run(): void
    {
        $paymentServiceProvidersData = collect(PaymentServiceProviderEnum::values());

        $paymentServiceProvidersData->each(function ($modelData) {
            $paymentServiceProvider = PaymentServiceProvider::where('code', $modelData)->first();

            if (! $paymentServiceProvider) {
                $paymentServiceProvider = StorePaymentServiceProvider::run([
                    'code' => $modelData,
                    'type' => PaymentServiceProviderEnum::types()[$modelData],
                    'name' => PaymentServiceProviderEnum::labels()[$modelData],
                ]);
            }

            $imageName = $paymentServiceProvider->code.'.png';
            $imagePath = storage_path('app/public/payment-providers/'.$imageName);

            $imageData = [
                'path' => $imagePath,
                'checksum' => md5_file($imagePath),
                'extension' => 'image/png',
                'originalName' => $imageName,
            ];

            StoreMediaFromFile::run($paymentServiceProvider, $imageData, 'logo');
        });
    }
}
