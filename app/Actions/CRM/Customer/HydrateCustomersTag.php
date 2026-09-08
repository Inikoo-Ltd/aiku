<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 14 Nov 2025 13:35:40 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateRfm;
use App\Actions\Helpers\Tag\Hydrators\TagHydrateModels;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class HydrateCustomersTag
{
    use AsAction;

    public string $commandSignature = 'hydrate:customers-tag';

    public function handle(): void
    {
        $tableName     = (new Customer())->getTable();
        $morphClass    = (new Customer())->getMorphClass();
        $dateThreshold = now()->subYear();

        $rfmTagIds = array_values(GetCustomerRfmTagIds::run());

        DB::table($tableName)
            ->select("$tableName.id")
            ->whereNull("$tableName.deleted_at")
            ->whereExists(function ($query) use ($tableName, $dateThreshold) {
                $query->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.customer_id', "$tableName.id")
                    ->where('invoices.in_process', false)
                    ->whereNull('invoices.deleted_at')
                    ->where('invoices.date', '>=', $dateThreshold);
            })
            ->orderBy("$tableName.id", 'desc')
            ->chunk(
                1000,
                function (Collection $modelsData) {
                    foreach ($modelsData as $modelData) {
                        try {
                            CustomerHydrateRfm::run($modelData->id, false);
                        } catch (Exception $e) {
                            Log::info("Failed to Hydrate Customers Tag: ".$e->getMessage());
                        }
                    }
                }
            );

        if (!empty($rfmTagIds)) {
            DB::table($tableName)
                ->select("$tableName.id")
                ->whereExists(function ($query) use ($tableName, $rfmTagIds, $morphClass) {
                    $query->select(DB::raw(1))
                        ->from('model_has_tags')
                        ->whereColumn('model_has_tags.model_id', "$tableName.id")
                        ->where('model_has_tags.model_type', $morphClass)
                        ->whereIn('model_has_tags.tag_id', $rfmTagIds);
                })
                ->where(function ($query) use ($tableName, $dateThreshold) {
                    $query->whereNotNull("$tableName.deleted_at")
                        ->orWhereNotExists(function ($invoices) use ($tableName, $dateThreshold) {
                            $invoices->select(DB::raw(1))
                                ->from('invoices')
                                ->whereColumn('invoices.customer_id', "$tableName.id")
                                ->where('invoices.in_process', false)
                                ->whereNull('invoices.deleted_at')
                                ->where('invoices.date', '>=', $dateThreshold);
                        });
                })
                ->chunkById(
                    1000,
                    function (Collection $modelsData) use ($rfmTagIds, $morphClass) {
                        try {
                            DB::table('model_has_tags')
                                ->where('model_type', $morphClass)
                                ->whereIn('model_id', $modelsData->pluck('id'))
                                ->whereIn('tag_id', $rfmTagIds)
                                ->delete();
                        } catch (Exception $e) {
                            Log::error("Failed to cleanup RFM tags for customers: ".$e->getMessage());
                        }
                    },
                    "$tableName.id",
                    'id'
                );

            foreach ($rfmTagIds as $tagId) {
                TagHydrateModels::dispatch($tagId);
            }
        }

        HydrateCustomerRfmSnapshot::run();
    }
}
