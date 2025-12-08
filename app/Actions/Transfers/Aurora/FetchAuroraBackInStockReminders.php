<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Oct 2024 16:40:54 Central Indonesia Time, Office, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\BackInStockReminder\StoreBackInStockReminder;
use App\Actions\CRM\BackInStockReminder\UpdateBackInStockReminder;
use App\Models\CRM\BackInStockReminder;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraBackInStockReminders extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:back_in_stock_reminder {organisations?*} {--S|shop= : Shop slug} {--s|source_id=}  {--N|only_new : Fetch only new} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?BackInStockReminder
    {
        if ($backInStockReminderData = $organisationSource->fetchBackInStockReminder($organisationSourceId)) {
            if (empty($backInStockReminderData['back_in_stock_reminder'])) {
                return null;
            }

            if ($backInStockReminder = BackInStockReminder::where('source_id', $backInStockReminderData['back_in_stock_reminder']['source_id'])
                ->first()) {
                try {
                    $backInStockReminder = UpdateBackInStockReminder::make()->action(
                        backInStockReminder: $backInStockReminder,
                        modelData: $backInStockReminderData['back_in_stock_reminder'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $backInStockReminder->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $backInStockReminderData['back_in_stock_reminder'], 'BackInStockReminder', 'update');

                    return null;
                }
            } else {
                data_set($modelData, 'parent_id', $backInStockReminderData['back_in_stock_reminder'], overwrite: false);
                try {
                    $backInStockReminder = StoreBackInStockReminder::make()->action(
                        customer: $backInStockReminderData['customer'],
                        product: $backInStockReminderData['product'],
                        modelData: $backInStockReminderData['back_in_stock_reminder'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false
                    );

                    $sourceData = explode(':', $backInStockReminder->source_id);
                    DB::connection('aurora')->table('Back in Stock Reminder Fact')
                        ->where('Back in Stock Reminder Key', $sourceData[1])
                        ->update(['aiku_id' => $backInStockReminder->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $backInStockReminderData['back_in_stock_reminder'], 'BackInStockReminder', 'store');

                    return null;
                }
            }

            return $backInStockReminder;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Back in Stock Reminder Fact')
            ->select('Back in Stock Reminder Key as source_id')
            ->orderBy('Back in Stock Reminder Creation Date');

        if ($this->onlyNew) {
            $query->whereNull('Back in Stock Reminder Fact.aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Back in Stock Reminder Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Back in Stock Reminder Fact');

        if ($this->onlyNew) {
            $query->whereNull('Back in Stock Reminder Fact.aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Back in Stock Reminder Store Key', $sourceData[1]);
        }

        return $query->count();
    }
}
