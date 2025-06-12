<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop as CatalogueShop;
use App\Models\CRM\Shop;
use App\Models\Dropshipping\AmazonUser;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreAmazonUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:store-amazon-user {shop} {name} {data?}';

    public function handle(CatalogueShop $shop, array $modelData): AmazonUser
    {
        $amazonUser = AmazonUser::create(
            array_merge(
                [
                    'shop_id' => $shop->id,
                    // 'slug'    => $modelData['slug'] ?? slug($modelData['name']),
                ],
                Arr::only($modelData, ['name']),
                [
                    'data'     => $modelData['data'] ?? [],
                    'settings' => $modelData['settings'] ?? [],
                ]
            )
        );

        return $amazonUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'slug'     => ['sometimes', 'string', 'max:255'],
            'data'     => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    public function asController(ActionRequest $request): AmazonUser
    {
        $this->initialisationFromShop($request->user()->customer->shop, $request);
        $modelData = $request->validated();

        return $this->handle($this->shop, $modelData);
    }

    public function htmlResponse(AmazonUser $amazonUser): RedirectResponse
    {
        return redirect()->route(
            'org.crm.shop.show',
            [
                'shop' => $amazonUser->shop->slug
            ]
        );
    }

    // public function asCommand(Command $command): int
    // {
    //     $this->setCommand($command);
    //     $shop = Shop::where('slug', $command->argument('shop'))->first();

    //     if (!$shop) {
    //         $this->error('Shop not found');
    //         return 1;
    //     }

    //     $modelData = [
    //         'name' => $command->argument('name')
    //     ];

    //     if ($command->argument('data')) {
    //         $modelData = array_merge($modelData, json_decode($command->argument('data'), true));
    //     }

    //     $amazonUser = $this->handle($shop, $modelData);
    //     $this->info('Amazon user created successfully 🎉');
    //     $this->line("ID: {$amazonUser->id}");

    //     return 0;
    // }
}
