<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Jan 2023 20:16:44 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateDeliveryNoteItemsSalesType;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteFixedAddress;
use App\Actions\Dispatching\Shipment\StoreShipment;
use App\Actions\Dispatching\Shipment\UpdateShipment;
use App\Actions\Helpers\Address\UpdateAddress;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipment;
use App\Models\Helpers\Address;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeliveryNotes extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:delivery_notes {organisations?*} {--s|source_id=} {--S|shop= : Shop slug}  {--N|only_new : Fetch only new} {--w|with=* : Accepted values: transactions} {--d|db_suffix=} {--r|reset} {--T|only_orders_no_transactions : Fetch only orders with no transactions} {--D|days= : fetch last n days} {--O|order= : order asc|desc}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId, bool $forceWithTransactions = false): ?DeliveryNote
    {
        $deliveryNoteData = $organisationSource->fetchDeliveryNote($organisationSourceId);

        if (!$deliveryNoteData or empty($deliveryNoteData['delivery_note']['source_id'])) {
            return null;
        }


        if ($deliveryNote = DeliveryNote::withTrashed()->where('source_id', $deliveryNoteData['delivery_note']['source_id'])->first()) {
            //  try {
            /** @var Address $deliveryAddress */
            $deliveryAddress = Arr::pull($deliveryNoteData, 'delivery_note.delivery_address');
            if ($deliveryNote->delivery_locked) {
                UpdateDeliveryNoteFixedAddress::make()->action(
                    deliveryNote: $deliveryNote,
                    modelData: [
                        'address' => $deliveryAddress,
                    ],
                    hydratorsDelay: 900,
                    audit: false
                );
            } else {
                UpdateAddress::run($deliveryNote->address, $deliveryAddress->toArray());
            }


            $deliveryNote = UpdateDeliveryNote::make()->action(
                $deliveryNote,
                $deliveryNoteData['delivery_note'],
                hydratorsDelay: 900,
                strict: false,
                audit: false
            );
            $this->recordChange($organisationSource, $deliveryNote->wasChanged());
            //            } catch (Exception $e) {
            //                $this->recordError($organisationSource, $e, $deliveryNoteData['delivery_note'], 'DeliveryNote', 'update');
            //
            //                return null;
            //            }

            if (in_array('transactions', $this->with) || in_array('full', $this->with) ||  $forceWithTransactions) {
                $this->fetchDeliveryNoteTransactions($organisationSource, $deliveryNote);
            }


            return $deliveryNote;
        } else {
            if ($deliveryNoteData['order']) {
                //z        try {
                $deliveryNote = StoreDeliveryNote::make()->action(
                    $deliveryNoteData['order'],
                    $deliveryNoteData['delivery_note'],
                    hydratorsDelay: $this->hydratorsDelay,
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
                //                } catch (Exception|Throwable $e) {
                //                    $this->recordError($organisationSource, $e, $deliveryNoteData['delivery_note'], 'DeliveryNote', 'store');
                //
                //                    return null;
                //                }


                if (in_array('transactions', $this->with) or in_array('full', $this->with) or  $forceWithTransactions) {
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

    private function fetchDeliveryNoteTransactions($organisationSource, $deliveryNote): void
    {
        $organisation         = $organisationSource->getOrganisation();
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
            FetchAuroraDeliveryNoteItems::run($organisationSource, $auroraData->{'Inventory Transaction Key'}, $deliveryNote);
        }
        $deliveryNote->deliveryNoteItems()->whereIn('id', array_keys($transactionsToDelete))->delete();

        DeliveryNoteHydrateDeliveryNoteItemsSalesType::run($deliveryNote);

        DB::connection('aurora')->table('Delivery Note Dimension')
            ->where('Delivery Note Key', $sourceData[1])
            ->update(['aiku_all_id' => $deliveryNote->id]);
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Delivery Note Dimension')
            ->select('Delivery Note Key as source_id');

        $query = $this->commonSelectModelsToFetch($query);
        $query->orderBy('Delivery Note Date', $this->orderDesc ? 'desc' : 'asc');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Delivery Note Dimension');
        $query = $this->commonSelectModelsToFetch($query);
        return $query->count();
    }

    public function commonSelectModelsToFetch($query)
    {
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        } elseif ($this->onlyOrdersNoTransactions) {
            $query->whereNull('aiku_all_id');
        }

        if ($this->fromDays) {
            $query->where('Delivery Note Date', '>=', now()->subDays($this->fromDays)->format('Y-m-d'));
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Delivery Note Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Delivery Note Dimension')->update(['aiku_id' => null]);
    }
}
