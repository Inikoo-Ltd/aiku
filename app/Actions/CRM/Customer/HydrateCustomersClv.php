<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 18 Nov 2025 14:29:22 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateClv;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;

class HydrateCustomersClv
{
    use AsAction;

    private string $model = Customer::class;

    public string $commandSignature = 'hydrate:customers-clv {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function handle(): void
    {
        $tableName = (new $this->model())->getTable();

        $query = DB::table($tableName)
            ->select("$tableName.id")
            ->whereExists(function ($q) use ($tableName) {
                $q->select(DB::raw(1))
                    ->from('invoices')
                    ->whereColumn('invoices.customer_id', "$tableName.id");
            })
            ->orderBy("$tableName.id", 'desc');

        $query->chunk(
            1000,
            function (Collection $modelsData) {
                foreach ($modelsData as $modelId) {
                    $model = (new $this->model());

                    if ($this->hasSoftDeletes($model)) {
                        $instance = $model->withTrashed()->find($modelId->id);
                    } else {
                        $instance = $model->find($modelId->id);
                    }

                    try {
                        CustomerHydrateClv::run($instance);
                    } catch (Exception $e) {
                        Log::info("Failed to Hydrate Customers CLV: " . $e->getMessage());
                        Sentry::captureMessage("Failed to Hydrate Customers CLV to: " . $e->getMessage());
                    }
                }
            }
        );
    }

    public function hasSoftDeletes($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
