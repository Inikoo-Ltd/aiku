<?php

namespace App\Actions\Dashboard\Hydrators;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\OrganisationStats;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateRevenue implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'hydrate:organisation-revenue {organisation}';

    public function asCommand(Command $command): void
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        if (!$organisation) {
            $command->error("Organisation not found");
            return;
        }

        $this->handle($organisation->id);

        $command->info("Organisation successfully hydrated");
    }

    public function handle(int $organisationId): void
    {
        $organisation = Organisation::find($organisationId);

        if (!$organisation) {
            return;
        }

        $shops = $organisation->shops()->get();

        if ($shops->isEmpty()) {
            return;
        }

        $stats = [
            'revenue_amount' => $shops->sum(fn($c) => $c->orderingStats->revenue_amount ?? 0),
            'lost_revenue_other_amount' => $shops->sum(fn($c) => $c->orderingStats->lost_revenue_other_amount ?? 0),
            'lost_revenue_out_of_stock_amount' => $shops->sum(fn($c) => $c->orderingStats->lost_revenue_out_of_stock_amount ?? 0),
            'lost_revenue_replacements_amount' => $shops->sum(fn($c) => $c->orderingStats->lost_revenue_replacements_amount ?? 0),
            'lost_revenue_compensations_amount' => $shops->sum(fn($c) => $c->orderingStats->lost_revenue_compensations_amount ?? 0),
        ];

        $organisationStats = $organisation->orderingStats ?? new OrganisationStats(['organisation_id' => $organisation->id]);
        $organisationStats->fill($stats);
        $organisationStats->save();
    }
}
