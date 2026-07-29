<?php

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateStockDeliveries implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';
    public string $commandSignature = 'hydrate:organisation_stock_deliveries';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id.'-stock_deliveries';
    }

    public function handle(Organisation $organisation): void
    {
        $queryBase = DB::table('stock_deliveries')
            ->where('organisation_id', $organisation->id)
            ->whereNull('deleted_at');

        $stats = [
            'number_stock_deliveries'         => $queryBase->clone()->count(),
            'number_current_stock_deliveries' => $queryBase->clone()->whereNotIn('state', [
                StockDeliveryStateEnum::CANCELLED->value,
                StockDeliveryStateEnum::NOT_RECEIVED->value
            ])->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'stock_deliveries',
                field: 'state',
                enum: StockDeliveryStateEnum::class,
                models: StockDelivery::class,
                where: function ($q) use ($organisation) {
                    $q->whereNull('deleted_at')
                        ->where('organisation_id', $organisation->id);
                }
            )
        );

        $organisation->procurementStats()->update($stats);
    }

    public function asCommand(): void
    {
        foreach (Organisation::all() as $organisation) {
            $this->handle($organisation);
        }
    }
}
