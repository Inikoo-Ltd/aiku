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
use App\Models\Dropshipping\AmazonUser;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateAmazonUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:update-amazon-user {amazonUser} {data?}';

    public function handle(AmazonUser $amazonUser, array $modelData): AmazonUser
    {
        $this->update($amazonUser, $modelData, ['data', 'settings']);

        return $amazonUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'data'     => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    public function asController(ActionRequest $request, AmazonUser $amazonUser): AmazonUser
    {
        $this->initialisationFromShop($amazonUser->shop, $request);
        $modelData = $request->validated();

        return $this->handle($amazonUser, $modelData);
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
    //     $amazonUser = AmazonUser::find($command->argument('amazonUser'));

    //     if (!$amazonUser) {
    //         $this->error('Amazon user not found');
    //         return 1;
    //     }

    //     $modelData = [];
    //     if ($command->argument('data')) {
    //         $modelData = json_decode($command->argument('data'), true);
    //     }

    //     $this->handle($amazonUser, $modelData);
    //     $this->info('Amazon user updated successfully 🎉');

    //     return 0;
    // }
}
