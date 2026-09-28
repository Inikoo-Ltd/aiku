<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Inventory\OrgStock;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Aurora marks a part as fresh on its available supplier parts; Aiku keeps it on the org stock.
 */
class SetOrgStocksFreshFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'maintenance:set_org_stocks_fresh_from_aurora {organisation}';

    /**
     * @return array{fresh: int, not_fresh: int}
     */
    public function handle(Organisation $organisation): array
    {
        $freshSourceIds = DB::connection('aurora')->table('Supplier Part Dimension')
            ->where('Supplier Part Status', 'Available')
            ->where('Supplier Part Fresh', 'Yes')
            ->distinct()
            ->pluck('Supplier Part Part SKU')
            ->map(fn ($partSku) => $organisation->id.':'.$partSku)
            ->all();

        $sourced = OrgStock::where('organisation_id', $organisation->id)->whereNotNull('source_id');

        return [
            'fresh'     => (clone $sourced)->whereIn('source_id', $freshSourceIds)->update(['is_fresh' => true]),
            'not_fresh' => (clone $sourced)->whereNotIn('source_id', $freshSourceIds)->where('is_fresh', true)->update(['is_fresh' => false]),
        ];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);

        $result = $this->handle($organisation);
        $command->info("Fresh: {$result['fresh']}, no longer fresh: {$result['not_fresh']}");

        return 0;
    }
}
