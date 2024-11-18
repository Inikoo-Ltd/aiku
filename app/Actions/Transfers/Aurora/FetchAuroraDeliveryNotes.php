<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Jan 2023 20:16:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipment\UpdateShipment;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeliveryNotes extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:delivery_notes {organisations?*} {--s|source_id=} {--S|shop= : Shop slug}  {--N|only_new : Fetch only new} {--w|with=* : Accepted values: transactions} {--d|db_suffix=} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId, bool $forceWithTransactions = false): ?DeliveryNote
    {
        if ($deliveryNoteData = $organisationSource->fetchDeliveryNote($organisationSourceId)) {
            if (!empty($deliveryNoteData['delivery_note']['source_id']) and $deliveryNote = DeliveryNote::withTrashed()->where('source_id', $deliveryNoteData['delivery_note']['source_id'])->first()) {
                try {
                    $deliveryNote = UpdateDeliveryNote::make()->action(
                        $deliveryNote,
                        $deliveryNoteData['delivery_note'],
                        hydratorsDelay: 60,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $deliveryNote->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $deliveryNoteData['delivery_note'], 'DeliveryNote', 'update');

                    return null;
                }

                if (in_array('transactions', $this->with) or $forceWithTransactions) {
                    $this->fetchDeliveryNoteTransactions($organisationSource, $deliveryNote);
                }



                return $deliveryNote;
            } else {
                if ($deliveryNoteData['order']) {
                    try {
                        $deliveryNote = StoreDeliveryNote::make()->action(
                            $deliveryNoteData['order'],
                            $deliveryNoteData['delivery_note'],
                            hydratorsDelay: 60,
                            strict: false,
                            audit: false
                        );

                        DeliveryNote::enableAuditing();
                        $this->saveMigrationHistory(
                            $deliveryNote,
                            Arr::except($deliveryNoteData['delivery_note'], ['fetched_at', 'last_fetched_at', 'source_id'])
                        );

                        $this->recordNew($organisationSource);

                        $sourceData = explode(':', $deliveryNote->source_id);
                        DB::connection('aurora')->table('Delivery Note Dimension')
                            ->where('Delivery Note Key', $sourceData[1])
                            ->update(['aiku_id' => $deliveryNote->id]);

                    } catch (Exception|Throwable $e) {
                        $this->recordError($organisationSource, $e, $deliveryNoteData['delivery_note'], 'DeliveryNote', 'store');

                        return null;
                    }

                    if (in_array('transactions', $this->with) or $forceWithTransactions) {
                        $this->fetchDeliveryNoteTransactions($organisationSource, $deliveryNote);
                    }



                    if ($deliveryNoteData['shipment'] and !Arr::get($deliveryNoteData['order'], 'data.delivery_data.collection')) {
                        if ($shipment = Shipment::withTrashed()->where('source_id', $deliveryNoteData['shipment']['source_id'])->first()) {
                            UpdateShipment::run($shipment, $deliveryNoteData['shipment']);
                        } else {
                            StoreShipment::run($deliveryNote, $deliveryNoteData['shipment']);
                        }
                    }


                    return $deliveryNote;
                }
                print "Warning delivery note $organisationSourceId do not have order\n";
                exit;
            }
        }


        return null;
    }

    private function fetchDeliveryNoteTransactions($organisationSource, $deliveryNote): void
    {
        $organisation = $organisationSource->getOrganisation();
        $transactionsToDelete = $deliveryNote->deliveryNoteItems()->pluck('source_id', 'id')->all();


        $sourceData = explode(':', $deliveryNote->source_id);
        foreach (
            DB::connection('aurora')
                ->table('Inventory Transaction Fact')
                ->select('Inventory Transaction Key')
                ->where('Delivery Note Key', $sourceData[1])
                ->get() as $auroraData
        ) {
            $transactionsToDelete = array_diff($transactionsToDelete, [$organisation->id.':'.$auroraData->{'Inventory Transaction Key'}]);
            FetchDeliveryNoteTransactions::run($organisationSource, $auroraData->{'Inventory Transaction Key'}, $deliveryNote);
        }
        $deliveryNote->deliveryNoteItems()->whereIn('id', array_keys($transactionsToDelete))->delete();
    }



    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Delivery Note Dimension')
            ->select('Delivery Note Key as source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }
        $query->orderBy('Delivery Note Date');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Delivery Note Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Delivery Note Dimension')->update(['aiku_id' => null]);
    }
}
