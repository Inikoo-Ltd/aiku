<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 17:20:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Production;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Artefacts nobody has bought for three years cannot be discontinued (the recipe is still
 * the record of how the thing is made) but should not sit in the working list either. This
 * parks them as dormant, judged by sales of the products behind the artefact's org stock, and
 * wakes a dormant one up again the moment it sells. Flags are not consulted: in aroma
 * is_for_sale means "on the website", not "still sold".
 */
class RepairDormantArtefacts
{
    use AsAction;

    public string $commandSignature = 'repair:dormant_artefacts {production : Production slug} {--months=36} {--fix : Change the states, otherwise only report}';

    protected function soldSince(string $since): \Closure
    {
        return fn ($query) => $query->from('product_has_org_stocks as phos')
            ->join('products as p', 'p.id', 'phos.product_id')
            ->join('invoice_transactions as it', 'it.asset_id', 'p.asset_id')
            ->whereColumn('phos.org_stock_id', 'artefacts.org_stock_id')
            ->whereNull('it.deleted_at')
            ->where('it.date', '>', $since);
    }

    public function toPark(Production $production, string $since): Builder
    {
        return Artefact::where('production_id', $production->id)
            ->where('state', ArtefactStateEnum::ACTIVE)
            ->where(fn ($query) => $query->whereNull('org_stock_id')->orWhereNotExists($this->soldSince($since)))
            ->orderBy('code');
    }

    public function toWake(Production $production, string $since): Builder
    {
        return Artefact::where('production_id', $production->id)
            ->where('state', ArtefactStateEnum::DORMANT)
            ->whereExists($this->soldSince($since))
            ->orderBy('code');
    }

    public function handle(Artefact $artefact, ArtefactStateEnum $state): Artefact
    {
        $artefact->update(['state' => $state]);

        return $artefact;
    }

    public function asCommand(Command $command): int
    {
        $production = Production::where('slug', $command->argument('production'))->firstOrFail();
        $since      = now()->subMonths((int) $command->option('months'))->toDateTimeString();

        $rows = [];
        foreach ($this->toPark($production, $since)->get() as $artefact) {
            $rows[] = [$artefact->code, $artefact->name, 'active → dormant'];
            if ($command->option('fix')) {
                $this->handle($artefact, ArtefactStateEnum::DORMANT);
            }
        }
        $parked = count($rows);
        foreach ($this->toWake($production, $since)->get() as $artefact) {
            $rows[] = [$artefact->code, $artefact->name, 'dormant → active'];
            if ($command->option('fix')) {
                $this->handle($artefact, ArtefactStateEnum::ACTIVE);
            }
        }

        $command->table(['Code', 'Name', 'Change'], $rows);
        $command->info($parked.' artefacts with nothing sold since '.$since.', '.(count($rows) - $parked).' dormant ones sold again.');
        if (!$command->option('fix')) {
            $command->line('Dry run. Pass --fix to change the states.');
        }

        return 0;
    }
}
