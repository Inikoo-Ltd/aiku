<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 20:24:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateInvoiceCategories implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return (string) $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_invoice_categories' => $organisation->invoiceCategories()->count(),
        ];

        // Per-state stats
        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'invoice_categories',
                field: 'state',
                enum: InvoiceCategoryStateEnum::class,
                models: InvoiceCategory::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        // Current = not closed (active + cooldown)
        $stats['number_current_invoice_categories'] =
            ($stats['number_invoice_categories_state_'.InvoiceCategoryStateEnum::ACTIVE->snake()] ?? 0)
            + ($stats['number_invoice_categories_state_'.InvoiceCategoryStateEnum::COOLDOWN->snake()] ?? 0);

        $organisation->accountingStats()->update($stats);
    }
}
