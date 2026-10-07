<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\Docs;

use App\Actions\Web\Webpage\PurgeVarnishPath;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class PurgeIrisDocsFromVarnish
{
    use AsAction;
    use WithIrisDocs;

    public string $commandSignature = 'iris:purge-docs';

    public string $commandDescription = 'Purge the dropshipping websites /docs pages from Varnish after the guides change';

    /**
     * @return array<int, string>
     */
    public function handle(Website $website): array
    {
        $paths = $this->everything()
            ->filter(fn (array $doc) => $this->isForWebsite($doc, $website))
            ->map(fn (array $doc) => '/'.self::PATH.'/'.$doc['slug'])
            ->prepend('/'.self::PATH)
            ->values()
            ->all();

        foreach ($paths as $path) {
            PurgeVarnishPath::make()->handle($website, $path);
        }

        return $paths;
    }

    public static function forShop(Shop $shop): void
    {
        if ($shop->type === ShopTypeEnum::DROPSHIPPING && $shop->website && config('iris.cache.varnish')) {
            self::dispatch($shop->website);
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        Website::query()
            ->whereHas('shop', fn ($query) => $query->where('type', ShopTypeEnum::DROPSHIPPING)->where('state', ShopStateEnum::OPEN))
            ->get()
            ->each(fn (Website $website) => $command->info($website->domain.': '.count($this->handle($website)).' paths purged'));

        return 0;
    }
}
