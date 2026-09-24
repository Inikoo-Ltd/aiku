<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\ProcurementNote\StoreProcurementNote;
use App\Models\Procurement\ProcurementNote;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraPurchaseOrderNotes extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:purchase_order_notes {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?ProcurementNote
    {
        $noteData = $organisationSource->fetchPurchaseOrderNote($organisationSourceId);
        if (!$noteData) {
            return null;
        }

        if ($note = ProcurementNote::where('source_id', $noteData['note']['source_id'])->first()) {
            $note->update(['new_values' => ['note' => $noteData['note']['note']]]);
            $this->recordChange($organisationSource, $note->wasChanged());

            return $note;
        }

        try {
            return StoreProcurementNote::make()->action($noteData['purchase_order'], array_filter($noteData['note'], fn ($value) => $value !== null), strict: false);
        } catch (Exception $e) {
            $this->recordError($organisationSource, $e, $noteData['note'], 'ProcurementNote', 'store');

            return null;
        }
    }

    public function getModelsQuery(): Builder
    {
        return $this->notesQuery()
            ->select('History Dimension.History Key as source_id')
            ->orderBy('History Dimension.History Date');
    }

    public function count(): ?int
    {
        return $this->notesQuery()->count();
    }

    private function notesQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('History Dimension')
            ->join('Purchase Order History Bridge', 'Purchase Order History Bridge.History Key', '=', 'History Dimension.History Key')
            ->join('Purchase Order Dimension', 'Purchase Order Dimension.Purchase Order Key', '=', 'Purchase Order History Bridge.Purchase Order Key')
            ->where('Purchase Order History Bridge.Type', 'Notes')
            ->whereIn('Purchase Order Dimension.Purchase Order Type', ['Parcel', 'Container']);
    }
}
