<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 20:21:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateInvoiceCategories implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return (string) $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_invoice_categories' => $group->invoiceCategories()->count(),
        ];

        // Per-state stats
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'invoice_categories',
                field: 'state',
                enum: InvoiceCategoryStateEnum::class,
                models: InvoiceCategory::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        // Current = not closed (active + in_process + cooldown)
        $stats['number_current_invoice_categories'] =
            ($stats['number_invoice_categories_state_'.InvoiceCategoryStateEnum::ACTIVE->snake()] ?? 0)
            + ($stats['number_invoice_categories_state_'.InvoiceCategoryStateEnum::COOLDOWN->snake()] ?? 0);

        $group->accountingStats()->update($stats);
    }
}
