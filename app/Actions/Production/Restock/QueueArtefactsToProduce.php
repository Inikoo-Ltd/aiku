<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Restock;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\BatchedUnitsForDemand;
use App\Actions\Production\PartnerShippingList\SetToProduceItemPreparing;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class QueueArtefactsToProduce extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
        ]);
    }

    /**
     * Put our own restock lines on the To produce board: no partner, no order, just work to do.
     *
     * @param array<int, array{artefact_id: int, quantity?: float, units?: int, priority?: string}> $lines
     *
     * @return array{queued: int, skipped: array<int, array{artefact_id: int, reason: string}>, not_prepared: array<int, string>, artefact_ids: array<int, int>}
     */
    public function handle(Organisation $seller, Production $production, array $lines): array
    {
        $artefacts = Artefact::query()
            ->where('production_id', $production->id)
            ->whereIn('id', collect($lines)->pluck('artefact_id'))
            ->whereNotNull('org_stock_id')
            ->with('orgStock')
            ->get()
            ->keyBy('id');

        $queued      = 0;
        $skipped     = [];
        $notPrepared = [];
        $queuedIds   = [];

        foreach ($lines as $line) {
            $artefact = $artefacts->get($line['artefact_id']);
            if (!$artefact) {
                $skipped[] = ['artefact_id' => $line['artefact_id'], 'reason' => 'no artefact with an org stock in this factory'];
                continue;
            }

            $quantity = isset($line['units'])
                ? $this->quantityForUnits((int) $line['units'], $artefact)
                : round((float) ($line['quantity'] ?? 0), 3);
            if ($quantity <= 0) {
                $skipped[] = ['artefact_id' => $line['artefact_id'], 'reason' => 'nothing to make'];
                continue;
            }

            $alreadyOpen = PartnerShoppingListItem::where('stock_id', $artefact->orgStock->stock_id)
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->whereNull('pre_picked_at')
                ->where(function ($query) use ($seller) {
                    $query->where('partner_organisation_id', $seller->id)
                        ->orWhere(function ($query) use ($seller) {
                            $query->whereNull('partner_organisation_id')->where('organisation_id', $seller->id);
                        });
                })
                ->exists();

            if ($alreadyOpen) {
                $skipped[] = ['artefact_id' => $line['artefact_id'], 'reason' => 'already on the To produce board'];
                continue;
            }

            $item = PartnerShoppingListItem::create([
                'group_id'         => $seller->group_id,
                'organisation_id'  => $seller->id,
                'stock_id'         => $artefact->orgStock->stock_id,
                'org_stock_id'     => $artefact->org_stock_id,
                'quantity'         => $quantity,
                'priority'         => ShoppingListItemPriorityEnum::tryFrom($line['priority'] ?? '') ?? ShoppingListItemPriorityEnum::NORMAL,
                'state'            => ShoppingListItemStateEnum::OPEN,
                'added_by_user_id' => request()->user()?->id,
            ]);

            $queued++;
            $queuedIds[] = $artefact->id;

            try {
                SetToProduceItemPreparing::make()->action($production, $item);
            } catch (ValidationException) {
                $notPrepared[] = $artefact->orgStock->code;
            }
        }

        return ['queued' => $queued, 'skipped' => $skipped, 'not_prepared' => $notPrepared, 'artefact_ids' => $queuedIds];
    }

    private function quantityForUnits(int $units, Artefact $artefact): float
    {
        $packedIn = max(1, (int) $artefact->orgStock->packed_in);
        $quantum  = BatchedUnitsForDemand::make()->quantumInSkos($packedIn, $artefact->recommended_batch_size) * $packedIn;

        return (float) ((int) ceil(max(1, $units) / $quantum) * $quantum / $packedIn);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines'               => ['required', 'array', 'min:1'],
            'lines.*.artefact_id' => ['required', 'integer'],
            'lines.*.quantity'    => ['required_without:lines.*.units', 'nullable', 'numeric', 'min:0.001'],
            'lines.*.units'       => ['sometimes', 'nullable', 'integer', 'min:1'],
            'lines.*.priority'    => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation, $production, $this->validatedData['lines']);
    }

    /**
     * @param array<int, array{artefact_id: int, quantity?: float, units?: int, priority?: string}> $lines
     *
     * @return array{queued: int, skipped: array<int, array{artefact_id: int, reason: string}>, not_prepared: array<int, string>, artefact_ids: array<int, int>}
     */
    public function action(Organisation $seller, Production $production, array $lines): array
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, ['lines' => $lines]);

        return $this->handle($seller, $production, $this->validatedData['lines']);
    }

    /** @param array{queued: int, skipped: array<int, array{artefact_id: int, reason: string}>, not_prepared: array<int, string>, artefact_ids: array<int, int>} $result */
    public function htmlResponse(array $result, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => $result['queued'] ? 'success' : 'warning',
            'title'       => $result['queued']
                ? __(':count sent to Prepare', ['count' => $result['queued']])
                : __('Nothing was sent to Prepare'),
            'description' => trim(
                ($result['not_prepared'] ? __('Left in Backlog, type their batch code on the board: :codes', ['codes' => implode(', ', $result['not_prepared'])]).' · ' : '')
                .__('Open board').': '.route('grp.org.productions.show.to_produce.index', $request->route()->originalParameters())
            ),
        ]);
    }
}
