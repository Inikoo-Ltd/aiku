<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\Product;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreProductToTiktok extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(TiktokUser $tiktokUser, array $modelData)
    {
        DB::transaction(function () use ($tiktokUser, $modelData) {
            $productImages = [];
            foreach (Arr::get($modelData, 'products') as $product) {
                $product = Product::find($product);

                /** @var Portfolio $portfolio */
                $portfolio = StorePortfolio::run(
                    $tiktokUser->customer,
                    $product,
                    [
                    'platform_id' => Platform::where('type', PlatformTypeEnum::TIKTOK->value)->first()->id,
                ]
                );

                foreach ($portfolio->item?->images as $image) {
                    $productImage = UploadProductImageToTiktok::run($tiktokUser, $image);

                    $productImages[] = [
                        'uri' => Arr::get($productImage, 'data.uri')
                    ];
                }

                $productData = [
                    'title' => $portfolio->item->name,
                    'description' => $portfolio->item->name,
                    'price' => $portfolio->item->price,
                    'category_id' => "2348816",
                    'main_images' => $productImages,
                    'package_weight' => [
                        'value' => $portfolio->item->gross_weight ?? "1.00",
                        'unit' => 'KILOGRAM'
                    ],
                    'package_dimensions' => [
                        'width' => "10",
                        'length' => "5",
                        'height' => "10",
                        'unit' => "CENTIMETER",
                    ],
                    'product_attributes' => [
                        [
                            'id' => "101710",
                            'values' => [
                                [
                                    'id' => "1000059",
                                    'name' => "No"
                                ]
                            ]
                        ],
                        [
                            'id' => "100110",
                            'values' => [
                                [
                                    'id' => "1000059",
                                    'name' => "No"
                                ]
                            ]
                        ]
                    ],
                    'skus' => [
                        [
                            'sales_attributes' => [],
                            'inventory' => [
                                [
                                    'quantity' => $portfolio->item->available_quantity,
                                    'warehouse_id' => "7480392764145895201"
                                ]
                            ],
                            'price' => [
                                'amount' => $portfolio->item->price,
                                'currency' => $tiktokUser->customer->shop->currency->code
                            ],
                        ]
                    ]
                ];

                $product = $tiktokUser->uploadProductToTiktok($productData);

                $tiktokUser->products()->create([
                    'productable_id' => $portfolio->item->id,
                    'productable_type' => $portfolio->item->getMorphClass(),
                    'tiktok_product_id' => Arr::get($product, 'data.product_id')
                ]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array']
        ];
    }

    public function asController(TiktokUser $tiktokUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($tiktokUser, $this->validatedData);
    }
}
