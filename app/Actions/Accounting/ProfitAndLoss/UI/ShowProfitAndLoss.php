<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\ProfitAndLoss\UI;

use App\Actions\Accounting\BankBalance\UI\ShowCash;
use App\Actions\Accounting\BankTransaction\UI\IndexBankIncoming;
use App\Actions\Accounting\ProfitAndLoss\GetProfitAndLoss;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingPagesAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The management profit and loss of the last twelve months, this one included, for an organisation or for the
 * organisations of the group the user may see.
 */
class ShowProfitAndLoss extends OrgAction
{
    use WithAccountingPagesAuthorisation {
        authorize as authorizeInOrganisation;
    }

    public const int MONTHS = 12;

    /** @var Collection<int, Organisation>|null */
    private ?Collection $groupOrganisations = null;

    private ?string $organisationSlug = null;

    /**
     * @param Collection<int, Organisation> $organisations
     * @return array<string, mixed>
     */
    public function handle(Organisation|Group $parent, Collection $organisations, ?Carbon $to = null): array
    {
        $to ??= now();

        return GetProfitAndLoss::make()->handle($parent, $organisations, $to->copy()->startOfMonth()->subMonths(self::MONTHS - 1), $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, collect([$organisation]));
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        $this->groupOrganisations = IndexBankIncoming::accountingOrganisations(group(), $request->user());
        $this->initialisationFromGroup(group(), $request);
        $picked                 = is_string($request->query('org')) ? $this->groupOrganisations->firstWhere('slug', $request->query('org')) : null;
        $this->organisationSlug = $picked?->slug;

        return $this->handle($this->group, $picked ? collect([$picked]) : $this->groupOrganisations);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->groupOrganisations === null) {
            return $this->authorizeInOrganisation($request);
        }

        return $this->groupOrganisations->isNotEmpty();
    }

    public function htmlResponse(array $profitAndLoss, ActionRequest $request): Response
    {
        $isGroup = $this->groupOrganisations !== null;

        return Inertia::render(
            'Org/Accounting/ProfitAndLoss',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($isGroup, $request->route()->originalParameters()),
                'title'         => __('Profit and loss'),
                'pageHead'      => [
                    'title' => __('Profit and loss'),
                    'icon'  => ['fal', 'fa-balance-scale'],
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $isGroup ? $this->groupOrganisations->map(fn (Organisation $organisation) => ['value' => $organisation->slug, 'label' => $organisation->code])->all() : [],
                'isGroup'       => $isGroup,
                'notConnected'  => ShowCash::NOT_CONNECTED,
                'profitAndLoss' => $profitAndLoss,
            ]
        );
    }

    public function getBreadcrumbs(bool $isGroup, array $routeParameters): array
    {
        if ($isGroup) {
            return array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.dashboard'], 'label' => __('Financials'), 'icon' => 'fal fa-file-invoice-dollar']],
                    ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.financials.profit_and_loss.index'], 'label' => __('Profit and loss')]],
                ]
            );
        }

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                ['type' => 'simple', 'simple' => ['route' => ['name' => 'grp.org.accounting.profit_and_loss.index', 'parameters' => $routeParameters], 'label' => __('Profit and loss')]],
            ]
        );
    }
}
