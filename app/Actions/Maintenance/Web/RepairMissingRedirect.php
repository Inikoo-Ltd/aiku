<?php

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Redirect\UpdateRedirect;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;

class RepairMissingRedirect
{
    use WithActionUpdate;

    public function handle(Webpage $webpage, int $storefront_id, Command $command)
    {
        // Check if webpage already has redirect_webpage_id, if not, use storefront
        $redirectedWebpageId = $webpage->redirectWebpage()->where('state', WebpageStateEnum::LIVE)->first()?->id ?? $storefront_id;

        $redirect = Redirect::where('from_path', $webpage->url)->where('website_id', $webpage->website->id)->first();

        if ($webpage->state === WebpageStateEnum::LIVE && !$redirect) {
            // If live & don't have redirect, just ignore, no?
            return;
        }

        $command->info('---------');
        $command->info("Webpage : {$webpage->slug}");
        $command->info("Webpage State: {$webpage->state->value}");

        if ($webpage->state === WebpageStateEnum::CLOSED) {
            if (!$redirect) {
                $command->info("Creating Redirect");
                $redirect = StoreRedirect::make()->action($webpage, [
                    'type'          => RedirectTypeEnum::TEMPORAL,
                    'to_webpage_id' => $redirectedWebpageId
                ]);
            } else {
                $command->info("Updating Redirect");
                $redirect = UpdateRedirect::make()->action($redirect, [
                    'type'          => RedirectTypeEnum::TEMPORAL,
                    'to_webpage_id' => $redirectedWebpageId
                ]);
            }
            // Ensure webpage redirect_webpage_id is the same
            $webpage->update([
                'redirect_webpage_id'   => $redirectedWebpageId
            ]);
        } elseif ($webpage->state === WebpageStateEnum::LIVE && $redirect) {
            // If it's live and has redirect, delete
            $command->info("Deleting redirect");
            $redirect->delete();
        }
    }

    public string $commandSignature = 'repair:website_missing_redirect {--website_id=} {--sub_type=}';

    public function asCommand(Command $command)
    {
        $parent = Website::where('state', WebsiteStateEnum::LIVE)
            ->when(
                $command->option('website_id'),
                fn ($q) => $q->where('id', $command->option('website_id'))
            );

        $subType = $command->option('sub_type');

        if ($subType && !in_array(
            $subType,
            [
                'product',
                'family',
                'department',
                'sub_department',
                'collection',
            ]
        )
        ) {
            $subType = null;
        }

        foreach ($parent->get() as $website) {
            $website
                ->webpages()
                ->where('type', WebpageTypeEnum::CATALOGUE->value)
                ->when(
                    $subType,
                    fn ($q) => $q->where('sub_type', $subType)
                )
                ->orderBy('id')
                ->chunkById(1000, function ($webpages) use ($command, $website) {
                    foreach ($webpages as $webpage) {
                        $this->handle($webpage, $website->storefront_id, $command);
                    }
                });
        }

    }
}
