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
use App\Models\Dropshipping\AmazonUser;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteAmazonUser extends OrgAction
{
    use AsAction;

    public $commandSignature = 'retina:ds:delete-amazon-user {amazonUser}';

    public function handle(AmazonUser $amazonUser): void
    {
        $amazonUser->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("crm.{$this->shop->id}.edit");
    }

    public function asController(ActionRequest $request, AmazonUser $amazonUser): void
    {
        $this->initialisationFromShop($amazonUser->shop, $request);
        $this->handle($amazonUser);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    // public function asCommand(Command $command): int
    // {
    //     $this->setCommand($command);
    //     $amazonUser = AmazonUser::find($command->argument('amazonUser'));

    //     if (!$amazonUser) {
    //         $this->error('Amazon user not found');
    //         return 1;
    //     }

    //     $this->handle($amazonUser);
    //     $this->info('Amazon user deleted successfully 🎉');

    //     return 0;
    // }
}
