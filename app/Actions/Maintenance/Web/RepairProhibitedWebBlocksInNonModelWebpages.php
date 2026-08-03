<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairProhibitedWebBlocksInNonModelWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;

    public function handle(Webpage $webpage, Command $command)
    {
        $allTemplateCodes = WebBlockTemplateEnum::allTemplateCodes();

        $delete = $command->option('apply-deletion');
        $hasDeletion = false;

        foreach ($allTemplateCodes as $templateCode) {
            $templateBlocks = $this->getWebpageBlocksByType($webpage, $templateCode);
            $countBlocks = count($templateBlocks);
            if ($countBlocks > 0) {
                $command->info("Detected Prohibited Web Block [$templateCode] (Total Count: {$countBlocks}): on Webpage [$webpage->slug]");
                if ($delete) {
                    $this->deleteWebBlocksByCode($webpage, $templateCode);
                    $hasDeletion = true;
                }
            }
        }

        if ($hasDeletion) {
            $webpage->refresh();
            UpdateWebpageContent::run($webpage);

            if ($webpage->is_dirty) {
                if ($webpage->state == WebpageStateEnum::LIVE) {
                    $command->line('Webpage '.$webpage->code.' is dirty, publishing after upgrade');
                    PublishWebpage::make()->action(
                        $webpage,
                        [
                            'comment' => 'publish after upgrade',
                        ]
                    );
                }
            }
        }
    }

    public string $commandSignature = 'repair:prohibited_web_blocks_in_non_model_webpages {website?} {--webpage_id=} {--apply-deletion}';

    public function asCommand(Command $command): void
    {
        $singleWebpageId = $command->option('webpage_id');

        if ($singleWebpageId) {
            $webpagesID = collect([(object)['id' => (int)$singleWebpageId]]);
        } else {
            $query = DB::table('webpages')->select('id')
                ->whereNull('model_type')
                ->whereIn(
                    'sub_type',
                    ProductCategoryTypeEnum::values()
                );

            if ($command->argument('website')) {
                $website   = Website::where('slug', $command->argument('website'))->first();
                $query->where('website_id', $website->id);
            }

            $webpagesID = $query->get();
        }

        $total   = count($webpagesID);
        $current = 1;
        foreach ($webpagesID as $webpageID) {
            print "[{$current}/{$total}] Webpage id: {$webpageID->id}\n";
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
            $current++;
        }
    }
}
