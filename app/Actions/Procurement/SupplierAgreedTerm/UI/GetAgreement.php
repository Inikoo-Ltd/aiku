<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 03:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierAgreedTerm\UI;

use App\Actions\Procurement\SupplierAgreedTerm\GetEffectiveSupplierAgreedTerms;
use App\Actions\Procurement\WithAgreedTermsAuthorisation;
use App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentOutcomeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteSeverityEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermTypeEnum;
use App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermValueTypeEnum;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementStatusEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Http\Resources\Procurement\SupplierAgreedTermResource;
use App\Models\Helpers\Currency;
use App\Models\Procurement\ProcurementNote;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetAgreement
{
    use AsObject;
    use WithAgreedTermsAuthorisation;

    /**
     * What a page needs to show and edit the agreement with a supplier, supplier product or agent:
     * the terms in force, the notes to see first (pinned or critical), the open incidents, and for
     * who may write, the routes and the options of the forms. An agent organisation gets only what
     * was marked as visible to the agent, and nothing to write with.
     *
     * @return array<string, mixed>
     */
    public function handle(Supplier|SupplierProduct|Agent $subject, ?Organisation $organisation, User $user, bool $forAgent = false): array
    {
        $supplier = $subject instanceof SupplierProduct ? $subject->supplier : ($subject instanceof Supplier ? $subject : null);

        $terms = GetEffectiveSupplierAgreedTerms::run($subject, $organisation)
            ->when($forAgent, fn ($terms) => $terms->where('visibility', ProcurementNoteVisibilityEnum::AGENT))
            ->each(fn ($term) => $term->loadMissing('attachments'));

        $notes = ProcurementNote::active()
            ->forOrganisation($organisation)
            ->where(function (Builder $query) use ($subject, $supplier) {
                foreach (array_filter([$subject, $supplier]) as $model) {
                    $query->orWhere(fn (Builder $query) => $query->where('subject_type', $model->getMorphClass())->where('subject_id', $model->id));
                }
            })
            ->when($forAgent, fn (Builder $query) => $query->where('visibility', ProcurementNoteVisibilityEnum::AGENT))
            ->with('author');

        $canWrite = !$forAgent && $this->canWriteProcurementNotes($user, $subject->group);
        $routeKey = match (true) {
            $subject instanceof SupplierProduct => 'supplier-product',
            $subject instanceof Agent           => 'agent',
            default                             => 'supplier',
        };
        $routeParameter = $subject instanceof SupplierProduct ? 'supplierProduct' : $routeKey;

        return [
            'terms'          => SupplierAgreedTermResource::collection($terms)->resolve(),
            'notes'          => ProcurementNoteResource::collection(
                (clone $notes)->where(fn (Builder $query) => $query->where('is_pinned', true)->orWhere('severity', ProcurementNoteSeverityEnum::CRITICAL))
                    ->orderByDesc('is_pinned')->latest('id')->limit(20)->get()
            )->resolve(),
            'open_incidents' => (clone $notes)->where('category', ProcurementNoteCategoryEnum::INCIDENT)->where('incident_outcome', ProcurementNoteIncidentOutcomeEnum::OPEN)->count(),
            'suppliers'      => $subject instanceof Agent ? $this->suppliersWithWarnings($subject, $organisation, $forAgent) : null,
            'supplier_agreement' => $this->supplierAgreement($subject, $forAgent),
            'can_write'      => $canWrite,
            'is_supervisor'  => $canWrite && $this->isProcurementSupervisor($user, $subject->group),
            'user_id'        => $user->id,
            'routes'         => $canWrite ? [
                'store_term' => ['name' => "grp.models.$routeKey.agreed_term.store", 'parameters' => [$routeParameter => $subject->id]],
                'store_note' => ['name' => "grp.models.$routeKey.procurement_note.store", 'parameters' => [$routeParameter => $subject->id]],
            ] : null,
            'options'        => $canWrite ? [
                'term_types'    => SupplierAgreedTermTypeEnum::valuesWithLabels(),
                'value_types'   => collect(SupplierAgreedTermValueTypeEnum::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label(), 'has_amount' => $case->hasAmount()])->all(),
                'categories'    => collect(ProcurementNoteCategoryEnum::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label(), 'colour' => $case->colour()])->all(),
                'incident_types' => ProcurementNoteIncidentTypeEnum::valuesWithLabels(),
                'severities'    => ProcurementNoteSeverityEnum::valuesWithLabels(),
                'visibilities'  => ProcurementNoteVisibilityEnum::valuesWithLabels(),
                'currencies'    => Currency::orderBy('code')->get(['id', 'code'])->map(fn (Currency $currency) => ['value' => $currency->id, 'label' => $currency->code])->all(),
                'organisations' => $subject->group->organisations()->where('type', '!=', OrganisationTypeEnum::AGENT)->orderBy('code')->get(['id', 'code', 'name'])
                    ->map(fn (Organisation $organisation) => ['value' => $organisation->id, 'label' => $organisation->name])->all(),
                'organisation_id' => $organisation?->id,
            ] : null,
        ];
    }

    /**
     * What the Notes tab of a purchase order or stock delivery needs to offer the full note form
     * and the pin and resolve buttons. The note always belongs to the document's organisation. Goods in staff
     * who are not buyers get the form with the incident category only.
     *
     * @return array<string, mixed>
     */
    public function noteForm(User $user, Group $group, bool $forAgent = false, ?int $organisationId = null): array
    {
        $canWriteNotes   = !$forAgent && $this->canWriteProcurementNotes($user, $group);
        $canWrite        = $canWriteNotes || (!$forAgent && $this->canReportIncidents($user, $organisationId));
        $categories      = $canWriteNotes ? ProcurementNoteCategoryEnum::cases() : [ProcurementNoteCategoryEnum::INCIDENT];

        return [
            'can_write'     => $canWrite,
            'is_supervisor' => $canWriteNotes && $this->isProcurementSupervisor($user, $group),
            'user_id'       => $user->id,
            'routes'        => null,
            'options'       => $canWrite ? [
                'categories'      => collect($categories)->map(fn ($case) => ['value' => $case->value, 'label' => $case->label(), 'colour' => $case->colour()])->all(),
                'incident_types'  => ProcurementNoteIncidentTypeEnum::valuesWithLabels(),
                'severities'      => ProcurementNoteSeverityEnum::valuesWithLabels(),
                'visibilities'    => ProcurementNoteVisibilityEnum::valuesWithLabels(),
                'currencies'      => Currency::orderBy('code')->get(['id', 'code'])->map(fn (Currency $currency) => ['value' => $currency->id, 'label' => $currency->code])->all(),
                'organisation_id' => null,
            ] : null,
        ];
    }

    /**
     * Where a supplier stands with the group's Supplier Agreement with every acceptance it made, or for an agent each
     * of its active suppliers' status, the ones still to accept first.
     *
     * @return array<string, mixed>|null
     */
    private function supplierAgreement(Supplier|SupplierProduct|Agent $subject, bool $forAgent = false): ?array
    {
        $statuses = GetSupplierAgreementStatus::make();

        if ($subject instanceof Supplier) {
            $status = $statuses->handle($subject);
            if (!$status) {
                return null;
            }

            return $forAgent
                ? collect($status)->only(['status', 'label', 'colour', 'icon', 'accepted_version', 'accepted_at', 'current_version', 'newer_version_to_read'])->all()
                : [...$status, 'history' => $statuses->history($subject)];
        }

        if ($subject instanceof Agent) {
            $suppliers = Supplier::where('agent_id', $subject->id)->where('status', true)->orderBy('code')->get(['id', 'slug', 'code', 'name', 'group_id']);
            $byId      = $statuses->forSuppliers($subject->group_id, $suppliers->pluck('id'));
            if ($byId === []) {
                return null;
            }

            $rows = $suppliers->map(fn (Supplier $supplier) => [
                'code' => $supplier->code,
                'name' => $supplier->name,
                'slug' => $forAgent ? null : $supplier->slug,
                ...collect($byId[$supplier->id])->except($forAgent ? ['authorised_person', 'position'] : [])->all(),
            ])->sortBy(fn (array $row) => [$row['status'] === SupplierAgreementStatusEnum::ACCEPTED->value ? 1 : 0, $row['code']])->values();

            return [
                'suppliers'     => $rows->all(),
                'not_accepted'  => $rows->where('status', '!=', SupplierAgreementStatusEnum::ACCEPTED->value)->count(),
            ];
        }

        return null;
    }

    /**
     * The agent's suppliers that carry a warning, a critical note or an open incident.
     *
     * @return list<array{code: string, name: string, warnings: int, open_incidents: int}>
     */
    private function suppliersWithWarnings(Agent $agent, ?Organisation $organisation, bool $forAgent): array
    {
        return ProcurementNote::active()
            ->forOrganisation($organisation)
            ->when($forAgent, fn (Builder $query) => $query->where('procurement_notes.visibility', ProcurementNoteVisibilityEnum::AGENT))
            ->where('procurement_notes.subject_type', 'Supplier')
            ->join('suppliers', 'suppliers.id', 'procurement_notes.subject_id')
            ->where('suppliers.agent_id', $agent->id)
            ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name')
            ->selectRaw('suppliers.code, suppliers.name')
            ->selectRaw('count(*) filter (where procurement_notes.severity in (?, ?)) as warnings', [ProcurementNoteSeverityEnum::WARNING->value, ProcurementNoteSeverityEnum::CRITICAL->value])
            ->selectRaw('count(*) filter (where procurement_notes.category = ? and procurement_notes.incident_outcome = ?) as open_incidents', [ProcurementNoteCategoryEnum::INCIDENT->value, ProcurementNoteIncidentOutcomeEnum::OPEN->value])
            ->havingRaw('count(*) filter (where procurement_notes.severity in (?, ?)) > 0 or count(*) filter (where procurement_notes.category = ? and procurement_notes.incident_outcome = ?) > 0', [
                ProcurementNoteSeverityEnum::WARNING->value, ProcurementNoteSeverityEnum::CRITICAL->value, ProcurementNoteCategoryEnum::INCIDENT->value, ProcurementNoteIncidentOutcomeEnum::OPEN->value,
            ])
            ->orderBy('suppliers.code')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['code' => $row->code, 'name' => $row->name, 'warnings' => (int) $row->warnings, 'open_incidents' => (int) $row->open_incidents])
            ->all();
    }
}
