<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\SupplierMessage\Whatsapp\SendSupplierWhatsappMessage;
use App\Enums\Procurement\SupplierMessage\SupplierMessageChannelEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Http\Resources\Procurement\SupplierMessagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierMessage;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSupplierMessages extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation|OrgSupplier|OrgAgent|Supplier $parent, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAny(['supplier_messages.subject', 'supplier_messages.from_address', 'supplier_messages.from_name', 'suppliers.name', 'agents.name', 'partners.name'], 'ILIKE', "%$value%");
            });
        });

        $query = QueryBuilder::for(SupplierMessage::class)
            ->leftJoin('suppliers', 'suppliers.id', 'supplier_messages.supplier_id')
            ->leftJoin('org_suppliers', 'org_suppliers.id', 'supplier_messages.org_supplier_id')
            ->leftJoin('org_agents', 'org_agents.id', 'supplier_messages.org_agent_id')
            ->leftJoin('agents', 'agents.id', 'org_agents.agent_id')
            ->leftJoin('org_partners', 'org_partners.id', 'supplier_messages.org_partner_id')
            ->leftJoin('organisations as partners', 'partners.id', 'org_partners.partner_id')
            ->join('organisations', 'organisations.id', 'supplier_messages.organisation_id');

        match (true) {
            $parent instanceof Organisation => $query->where('supplier_messages.organisation_id', $parent->id),
            $parent instanceof OrgSupplier => $query->where('supplier_messages.org_supplier_id', $parent->id),
            $parent instanceof OrgAgent => $query->where('supplier_messages.org_agent_id', $parent->id),
            $parent instanceof Supplier => $query->where('supplier_messages.supplier_id', $parent->id),
        };

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        return $query
            ->select([
                'supplier_messages.id',
                'supplier_messages.direction',
                'supplier_messages.channel',
                'supplier_messages.phone_number',
                'supplier_messages.from_address',
                'supplier_messages.from_name',
                'supplier_messages.to',
                'supplier_messages.subject',
                'supplier_messages.snippet',
                'supplier_messages.attachments',
                'supplier_messages.sent_at',

                'organisations.slug as organisation_slug',
                'organisations.code as organisation_code',
            ])
            ->selectRaw("coalesce(suppliers.name, agents.name, partners.name) as counterpart_name")
            ->selectRaw("case when supplier_messages.org_agent_id is not null then 'agent' when supplier_messages.org_partner_id is not null then 'partner' when supplier_messages.org_supplier_id is not null then 'supplier' end as counterpart_type")
            ->defaultSort('-sent_at')
            ->allowedSorts(['sent_at', 'subject', 'counterpart_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    protected function getElementGroups(Organisation|OrgSupplier|OrgAgent|Supplier $parent): array
    {
        $groups = [
            'channel'   => [
                'label'    => __('Channel'),
                'elements' => [
                    SupplierMessageChannelEnum::EMAIL->value    => [__('Email'), null],
                    SupplierMessageChannelEnum::WHATSAPP->value => [__('WhatsApp'), null],
                ],
                'engine'   => fn ($query, $elements) => $query->whereIn('supplier_messages.channel', $elements),
            ],
            'direction' => [
                'label'    => __('Direction'),
                'elements' => [
                    SupplierMessageDirectionEnum::INBOUND->value  => [__('Received'), null],
                    SupplierMessageDirectionEnum::OUTBOUND->value => [__('Sent'), null],
                ],
                'engine'   => fn ($query, $elements) => $query->whereIn('supplier_messages.direction', $elements),
            ],
        ];

        if ($parent instanceof Organisation) {
            $groups['routing'] = [
                'label'    => __('Assigned'),
                'elements' => [
                    'assigned'   => [__('Assigned'), null],
                    'unassigned' => [__('Unassigned'), null],
                ],
                'engine'   => function ($query, $elements) {
                    if (count($elements) === 1) {
                        in_array('unassigned', $elements)
                            ? $query->whereNull('supplier_messages.org_supplier_id')->whereNull('supplier_messages.org_agent_id')->whereNull('supplier_messages.org_partner_id')
                            : $query->where(fn ($query) => $query->whereNotNull('supplier_messages.org_supplier_id')->orWhereNotNull('supplier_messages.org_agent_id')->orWhereNotNull('supplier_messages.org_partner_id'));
                    }
                },
            ];
        }

        return $groups;
    }

    public function tableStructure(Organisation|OrgSupplier|OrgAgent|Supplier $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No messages yet'),
                    'description' => $parent instanceof Organisation && ! Arr::get($parent->settings, 'procurement.gmail.email')
                        ? __('Connect the procurement mailbox in Procurement settings to start receiving supplier emails here.')
                        : null,
                ])
                ->column(key: 'direction', label: '', canBeHidden: false)
                ->column(key: 'sent_at', label: __('Date'), canBeHidden: false, sortable: true);

            if (! $parent instanceof OrgSupplier && ! $parent instanceof OrgAgent) {
                $table->column(key: 'counterpart_name', label: __('Supplier / Agent'), canBeHidden: false, sortable: true);
            }

            if ($parent instanceof Supplier) {
                $table->column(key: 'organisation_code', label: __('Organisation'), canBeHidden: false);
            }

            $table
                ->column(key: 'correspondent', label: __('From / To'), canBeHidden: false)
                ->column(key: 'subject', label: __('Subject'), canBeHidden: false, sortable: true);
        };
    }

    public function htmlResponse(LengthAwarePaginator $emails, ActionRequest $request): Response
    {
        $title = __('Supplier inbox');

        return Inertia::render(
            'Procurement/SupplierMessages',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'title'    => $title,
                    'icon'     => ['fal', 'fa-inbox'],
                ],
                'mailbox'     => Arr::get($this->organisation->settings, 'procurement.gmail.email'),
                'data'        => SupplierMessagesResource::collection($emails)->additional(['compose' => self::composeData($this->organisation, $request->user())]),
            ]
        )->table($this->tableStructure($this->organisation));
    }

    /**
     * @return array{email: array<string, mixed>|null, whatsapp: array<string, mixed>|null}|null
     */
    public static function composeData(Organisation $organisation, User $user, OrgSupplier|OrgAgent|null $counterpart = null): ?array
    {
        if (! $user->authTo("procurement.{$organisation->id}.edit")) {
            return null;
        }

        $summary = $counterpart ? ShowSupplierMessage::counterpartSummary($counterpart, $organisation) : null;

        $compose = [
            'email'    => Arr::get($organisation->settings, 'procurement.gmail.email') ? [
                'route'       => ['name' => 'grp.org.procurement.supplier_messages.send', 'parameters' => [$organisation->slug]],
                'to'          => array_values(array_filter([$summary['email'] ?? null])),
                'counterpart' => $summary['key'] ?? null,
            ] : null,
            'whatsapp' => SendSupplierWhatsappMessage::isConnected($organisation) ? [
                'route'        => ['name' => 'grp.org.procurement.supplier_messages.whatsapp', 'parameters' => [$organisation->slug]],
                'phone'        => $summary['phone'] ?? null,
                'counterpart'  => $summary['key'] ?? null,
                'window_open'  => filled($summary['phone'] ?? null) && SendSupplierWhatsappMessage::isWindowOpen($organisation, (string) $summary['phone']),
                'has_template' => filled(Arr::get($organisation->settings, 'procurement.whatsapp.message_template')),
                'template'     => SendSupplierWhatsappMessage::messageTemplate($organisation),
            ] : null,
        ];

        return $compose['email'] || $compose['whatsapp'] ? $compose : null;
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.supplier_messages.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Inbox'),
                    ],
                ],
            ]
        );
    }
}
