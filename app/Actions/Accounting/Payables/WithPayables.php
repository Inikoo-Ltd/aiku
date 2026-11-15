<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payables;

use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the Payables dashboard and its lists share: one organisation, or the organisations of the group whose
 * accounting the user may see (or the one of them picked) in the group's currency.
 */
trait WithPayables
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    /** @var Collection<int, Organisation>|null */
    private ?Collection $groupOrganisations = null;

    /** @var Collection<int, Organisation>|null */
    private ?Collection $payablesOrganisations = null;

    private ?string $organisationSlug = null;

    /**
     * @return array<string, array{0: string, 1: array<int, string>}> label and icon by section
     */
    public static function sections(): array
    {
        return [
            'supplier_invoices' => [__('Supplier invoices'), ['fal', 'fa-file-invoice']],
            'agent_wallets'     => [__('Agent wallets'), ['fal', 'fa-wallet']],
            'agent_invoices'    => [__('Agent invoices'), ['fal', 'fa-ship']],
            'money_requests'    => [__('Agent money requests'), ['fal', 'fa-hand-holding-usd']],
            'service_invoices'  => [__('Service invoices'), ['fal', 'fa-truck-container']],
            'not_invoiced'      => [__('Not invoiced yet'), ['fal', 'fa-hourglass-half']],
            'creditors'         => [__('Creditors'), ['fal', 'fa-users']],
        ];
    }

    protected function readGroup(ActionRequest $request): void
    {
        $this->groupOrganisations = IndexBankIncoming::accountingOrganisations(group(), $request->user());
        $this->initialisationFromGroup(group(), $request);
        $picked                   = is_string($request->query('org')) ? $this->groupOrganisations->firstWhere('slug', $request->query('org')) : null;
        $this->organisationSlug   = $picked?->slug;
        if ($picked) {
            $this->payablesOrganisations = collect([$picked]);
        }
    }

    protected function isGroup(): bool
    {
        return $this->groupOrganisations !== null;
    }

    protected function parent(): Organisation|Group
    {
        return $this->isGroup() ? $this->group : $this->organisation;
    }

    protected function payables(): Builder
    {
        $organisations = $this->payablesOrganisations ?? $this->groupOrganisations ?? collect([$this->organisation]);

        return GetPayables::query($organisations->pluck('id')->all(), $this->parent()->currency_id);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || !$this->isGroup()) {
            return $this->authorizeInOrganisation($request);
        }

        return $this->groupOrganisations->isNotEmpty();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function subNavigation(): array
    {
        $counts = $this->payables()->selectRaw('kind, count(*) as documents')->groupBy('kind')->pluck('documents', 'kind')->all();
        $counts['creditors'] = (int) $this->payables()->where('counted', true)->selectRaw('count(distinct (organisation_id, creditor_key)) as creditors')->value('creditors');

        return IndexBankIncoming::sectionsNavigation($this->parent(), 'payables', self::sections(), $counts, array_filter(['org' => $this->organisationSlug]));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function organisationOptions(): array
    {
        return $this->isGroup() ? $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all() : [];
    }

    /**
     * @param array<string, mixed> $routeParameters
     * @return array<int, array<string, mixed>>
     */
    protected function payablesBreadcrumbs(array $routeParameters): array
    {
        if ($this->isGroup()) {
            return array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.payables.index'], 'label' => __('Payables')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.payables.index', 'parameters' => $routeParameters], 'label' => __('Payables')]],
            ]
        );
    }
}
