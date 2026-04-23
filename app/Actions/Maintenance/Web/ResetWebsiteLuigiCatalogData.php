<?php

/*
 * author Louis Perez
 * created on 30-03-2026-10h-30m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Web\Website\Luigi\WithLuigis;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetWebsiteLuigiCatalogData
{
    use AsAction;
    use WithOrganisationSource;
    use WithLuigis;

    /**
     * @throws \Exception
     */
    public function handle(Website $website, Command $command): void
    {
        $pageCount = 1;
        $command->info('// Processing page: ' . $pageCount);
        $contents = $this->getContentExport($website);

        while ($contents) {
            $nextPage = $this->getNextPagination(data_get($contents, 'links'));
            $query = parse_url($nextPage, PHP_URL_QUERY);

            $objects = data_get($contents, 'objects', []);
            if (count($objects) > 0) {
                $command->info('Deleting ' . count($objects) . ' items from Luigi catalog (' . data_get($contents, 'total') . ' lefts)');
                $this->deleteItemBatch($website, $objects);
            }

            if (empty($query)) {
                break;
            }

            $pageCount++;
            $command->info('// Processing page: ' . $pageCount);
            $contents = $this->getContentExport($website, $query);
        };
    }

    public function deleteItemBatch(Website $website, array $items): void
    {
        $objects = array_map(fn ($item) => [
            'type' => 'item',
            'identity' => $item['url'],
        ], $items);

        $this->request($website, '/v1/content', [
            'objects' => $objects
        ], 'delete');
    }

    public string $commandSignature = 'reset:website_luigi_data {website?}';

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {
        $website = Website::where('slug', $command->argument('website'))->first();

        if ($website) {
            $accessToken = $this->getAccessToken($website);
            if ($accessToken) {
                if ($command->confirm("Are you sure you want to reset the catalog for: $website->name [$website->domain] ?")) {
                    $command->info('Procesing Reset Catalog Data for website: ' . $website->slug);
                    $this->handle($website, $command);
                } else {
                    $command->info("Aborting command");
                }
            } else {
                $command->info("Access token not found [Website Slug: {$command->argument('website')}]");
            }
        } else {
            $command->info("Website not found [Website Slug: {$command->argument('website')}]");
        }
    }
}
