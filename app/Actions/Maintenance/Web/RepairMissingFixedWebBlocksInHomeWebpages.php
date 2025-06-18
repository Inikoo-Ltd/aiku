<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 12:48:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInHomeWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        $this->processHomeWebpages($webpage, $command);
    }


    protected function processHomeWebpages(Webpage $webpage, Command $command): void
    {

        $collectionsWebBlock = $this->getWebpageBlocksByType($webpage, 'collections-1');

        if (count($collectionsWebBlock) == 0) {
            $command->error('Webpage '.$webpage->code.' Collection Web Block not found');
            $this->createWebBlock($webpage, 'collections-1', $webpage);
        } elseif (count($collectionsWebBlock) > 1) {
            $command->error('Webpage '.$webpage->code.' MORE than 1 Collection Web Block found');
        }


        $webpage->refresh();
        UpdateWebpageContent::run($webpage);
        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";



        PublishWebpage::make()->action(
            $webpage,
            [
                'comment' => 'publish after upgrade',
            ]
        );

    }


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_homepage';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('type', 'storefront')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }

        }
    }

}
