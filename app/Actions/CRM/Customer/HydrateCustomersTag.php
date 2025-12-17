<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 14 Nov 2025 13:35:40 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateRfm;
use App\Models\CRM\Customer;
use App\Models\Helpers\Tag;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Log;
use Sentry;

class HydrateCustomersTag
{
    use AsAction;

    private string $model = Customer::class;

    public string $commandSignature = 'hydrate:customers-tag';

    public function handle(): void
    {
        $tableName = (new $this->model())->getTable();
        $dateThreshold = now()->subYear();

        // Get RFM tag IDs
        $rfmTagIds = Tag::whereIn('data->type', ['recency', 'frequency', 'monetary'])
            ->pluck('id')
            ->toArray();

        // Process customers with recent invoices (hydrate RFM tags)
        $query = DB::table($tableName)
            ->select("$tableName.id")
            ->whereExists(function ($q) use ($tableName, $dateThreshold) {
                $q->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.customer_id', "$tableName.id")
                    ->where('invoices.in_process', false)
                    ->where('invoices.created_at', '>=', $dateThreshold);
            })
            ->orderBy("$tableName.id", 'desc');

        $query->chunk(
            1000,
            function (Collection $modelsData) {
                foreach ($modelsData as $modelData) {
                    try {
                        CustomerHydrateRfm::run($modelData->id);
                    } catch (Exception $e) {
                        Log::info("Failed to Hydrate Customers Tag: " . $e->getMessage());
                        Sentry::captureMessage("Failed to Hydrate Customers Tag to: " . $e->getMessage());
                    }
                }
            }
        );

        // Process customers WITHOUT recent invoices but have RFM tags (detach RFM tags)
        if (!empty($rfmTagIds)) {
            $customersToCleanup = DB::table($tableName)
                ->select("$tableName.id")
                ->whereExists(function ($q) use ($tableName, $rfmTagIds) {
                    $q->select(DB::raw(1))
                        ->from('taggables')
                        ->whereColumn('taggables.taggable_id', "$tableName.id")
                        ->where('taggables.taggable_type', 'Customer')
                        ->whereIn('taggables.tag_id', $rfmTagIds);
                })
                ->whereNotExists(function ($q) use ($tableName, $dateThreshold) {
                    $q->select(DB::raw(1))
                        ->from('invoices')
                        ->whereColumn('invoices.customer_id', "$tableName.id")
                        ->where('invoices.in_process', false)
                        ->where('invoices.created_at', '>=', $dateThreshold);
                })
                ->orderBy("$tableName.id", 'desc');

            $customersToCleanup->chunk(
                1000,
                function (Collection $modelsData) use ($rfmTagIds) {
                    foreach ($modelsData as $modelData) {
                        try {
                            $customer = Customer::find($modelData->id);
                            if ($customer) {
                                $customer->tags()->detach($rfmTagIds);
                            }
                        } catch (Exception $e) {
                            Log::info("Failed to cleanup RFM tags for customer: " . $e->getMessage());
                            Sentry::captureMessage("Failed to cleanup RFM tags for customer: " . $e->getMessage());
                        }
                    }
                }
            );
        }

        HydrateCustomerRfmSnapshot::run();
    }
}
