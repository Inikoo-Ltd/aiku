<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\PartnerShipment;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StorePartnerShipment extends OrgAction
{
    /**
     * Notes of a partner that can go on a truck: packed or later, from any of our shops (each shop
     * has its own customer record for the partner), not on another shipment. Dispatched ones only for
     * 30 days, so a shipment can still be made up after the truck left.
     *
     * @return Builder<DeliveryNote>
     */
    public static function eligibleDeliveryNotesQuery(OrgPartner $orgPartner): Builder
    {
        return DeliveryNote::query()
            ->where('delivery_notes.organisation_id', $orgPartner->organisation_id)
            ->whereIn(
                'delivery_notes.customer_id',
                Customer::query()->select('id')
                    ->where('organisation_id', $orgPartner->organisation_id)
                    ->where('as_organisation_id', $orgPartner->partner_id)
            )
            ->whereIn('delivery_notes.state', [DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED])
            ->where(fn (Builder $query) => $query->where('delivery_notes.state', '!=', DeliveryNoteStateEnum::DISPATCHED)
                ->orWhere('delivery_notes.dispatched_at', '>=', now()->subDays(30)))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('partner_shipment_delivery_notes')
                ->whereColumn('partner_shipment_delivery_notes.delivery_note_id', 'delivery_notes.id'));
    }

    /**
     * @return array{prefix: string, first_number: int}
     */
    public static function nextNumber(OrgPartner $orgPartner): array
    {
        $last = PartnerShipment::where('org_partner_id', $orgPartner->id)->orderByDesc('id')->first();

        if ($last) {
            return ['prefix' => $last->prefix, 'first_number' => $last->first_number + count($last->splits)];
        }

        return [
            'prefix'       => strtoupper(substr($orgPartner->organisation->code, 0, 3).$orgPartner->partner->code),
            'first_number' => 1,
        ];
    }

    /**
     * @param array{delivery_note_ids: array<int, int>, prefix: string, first_number: int, date: string, number_pallets?: int|null} $modelData
     *
     * @throws \Throwable
     */
    public function handle(OrgPartner $orgPartner, array $modelData): PartnerShipment
    {
        return DB::transaction(function () use ($orgPartner, $modelData) {
            DB::select('select pg_advisory_xact_lock(4482, ?)', [$orgPartner->id]);

            $requestedIds = array_values(array_unique(array_map('intval', $modelData['delivery_note_ids'])));

            $eligibleIds = self::eligibleDeliveryNotesQuery($orgPartner)
                ->whereIn('delivery_notes.id', $requestedIds)
                ->pluck('delivery_notes.id')
                ->all();

            if (count($eligibleIds) !== count($requestedIds)) {
                throw ValidationException::withMessages([
                    'delivery_note_ids' => __('Some delivery notes are not packed, are not for this partner or are already on another shipment'),
                ]);
            }

            $lines      = GetPartnerShipmentCustomsLines::make()->handle($orgPartner, $requestedIds);
            $incomplete = $lines->flatten(1)->where('is_incomplete', true);
            if ($incomplete->isNotEmpty()) {
                $parts = $incomplete->flatMap(fn ($line) => collect(json_decode($line->offenders ?? '[]', true))->pluck('part'))->filter()->unique()->sort()->values();

                throw ValidationException::withMessages([
                    'delivery_note_ids' => __('Set the tariff code and country of origin of: :parts', ['parts' => $parts->implode(', ')]),
                ]);
            }

            $splits      = $lines->keys()->values()->all();
            $firstNumber = (int) $modelData['first_number'];
            $overlaps    = PartnerShipment::where('org_partner_id', $orgPartner->id)
                ->where('prefix', $modelData['prefix'])
                ->where('first_number', '<', $firstNumber + count($splits))
                ->whereRaw('first_number + jsonb_array_length(splits) > ?', [$firstNumber])
                ->exists();
            if ($overlaps) {
                throw ValidationException::withMessages([
                    'first_number' => __('These invoice numbers are already used, next free one is :number', ['number' => self::nextNumber($orgPartner)['first_number']]),
                ]);
            }

            /** @var PartnerShipment $partnerShipment */
            $partnerShipment = PartnerShipment::create([
                'group_id'        => $orgPartner->group_id,
                'organisation_id' => $orgPartner->organisation_id,
                'org_partner_id'  => $orgPartner->id,
                'prefix'          => $modelData['prefix'],
                'first_number'    => $firstNumber,
                'splits'          => $splits,
                'date'            => $modelData['date'],
                'number_pallets'  => $modelData['number_pallets'] ?? null,
            ]);
            $partnerShipment->deliveryNotes()->attach($requestedIds);

            return $partnerShipment;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'delivery_note_ids'   => ['required', 'array', 'min:1'],
            'delivery_note_ids.*' => ['integer'],
            'prefix'              => ['required', 'string', 'max:32', 'alpha_num'],
            'first_number'        => ['required', 'integer', 'min:1'],
            'date'                => ['required', 'date'],
            'number_pallets'      => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): PartnerShipment
    {
        abort_unless($orgPartner->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->validatedData);
    }

    public function htmlResponse(PartnerShipment $partnerShipment): RedirectResponse
    {
        return redirect()->route('grp.org.procurement.org_partners.show.customs_invoices.index', [
            $partnerShipment->organisation->slug,
            $partnerShipment->org_partner_id,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(OrgPartner $orgPartner, array $modelData): PartnerShipment
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, $modelData);

        return $this->handle($orgPartner, $this->validatedData);
    }
}
