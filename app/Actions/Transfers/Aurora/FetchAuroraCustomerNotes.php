<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Oct 2024 10:17:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\CustomerNote\StoreCustomerNote;
use App\Actions\CRM\CustomerNote\UpdateCustomerNote;
use App\Models\CRM\CustomerNote;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraCustomerNotes extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:customer-notes {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new}  {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?CustomerNote
    {
        if ($CustomerNoteData = $organisationSource->fetchCustomerNote($organisationSourceId)) {
            if ($CustomerNote = CustomerNote::where('source_id', $CustomerNoteData['customer_note']['source_id'])
                ->first()) {
                try {

                    $CustomerNote = UpdateCustomerNote::make()->action(
                        CustomerNote: $CustomerNote,
                        modelData: $CustomerNoteData['customer_note'],
                        hydratorsDelay: 60,
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $CustomerNote->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $CustomerNoteData['customer_note'], 'CustomerNote', 'update');

                    return null;
                }
            } else {


                try {
                    $CustomerNote = StoreCustomerNote::make()->action(
                        customer: $CustomerNoteData['customer'],
                        modelData: $CustomerNoteData['customer_note'],
                        hydratorsDelay: 60,
                        strict: false,
                    );
                    $sourceData     = explode(':', $CustomerNote->source_id);
                    DB::connection('aurora')->table('History Dimension')
                        ->where('History Key', $sourceData[1])
                        ->update(['aiku_id' => $CustomerNote->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $CustomerNoteData['customer_note'], 'CustomerNote', 'store');
                    return null;
                }
            }


            return $CustomerNote;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('History Dimension')
            ->where('Direct Object', 'Note')
            ->where('Indirect Object', 'Customer')
            ->select('History Key as source_id')
            ->orderBy('source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_notes_id');
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('History Dimension')
            ->where('Direct Object', 'Note')
            ->where('Indirect Object', 'Customer');

        if ($this->onlyNew) {
            $query->whereNull('aiku_notes_id');
        }

        return $query->count();
    }



}
