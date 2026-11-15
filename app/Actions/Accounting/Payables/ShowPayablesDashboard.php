<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payables;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * What we owe suppliers and agents today, in the organisation's currency (the group's when the whole group is
 * read): the total and its split by kind of document, when it falls due, how old the documents are, who is owed
 * most, and in which currencies. An amount with no exchange rate known is listed apart, never added as zero.
 * Goods not invoiced yet, money requests awaiting approval and the invoices of agents owed through their wallet are
 * shown as figures of their own and are in none of the totals.
 */
class ShowPayablesDashboard extends OrgAction
{
    use WithPayables;

    public const int TOP_CREDITORS = 10;

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $rows      = $this->payables()->get();
        $owed      = $rows->where('counted', true);
        $converted = $owed->whereNotNull('amount');
        $apart     = function (callable $filter) use ($rows) {
            $group = $rows->where('counted', false)->whereNotNull('amount')->filter($filter);

            return ['count' => $group->count(), 'amount' => round($group->sum(fn (object $row) => (float) $row->amount), 2)];
        };
        $today     = now();
        $sum       = fn (Collection $group) => round($group->sum(fn (object $row) => (float) $row->amount), 2);
        $split     = fn (array $keys, callable $bucket) => collect($keys)->map(function (string $key) use ($converted, $bucket, $sum) {
            $group = $converted->filter(fn (object $row) => $bucket($row) === $key);

            return ['key' => $key, 'count' => $group->count(), 'amount' => $sum($group)];
        })->all();

        $due      = collect($split(GetPayables::DUE_BUCKETS, fn (object $row) => GetPayables::dueBucket($row->due_date, $today)))->keyBy('key');
        $overdue  = $due->only(['overdue_90', 'overdue_61_90', 'overdue_31_60', 'overdue_1_30']);

        return [
            'total'         => ['amount' => $sum($converted), 'count' => $owed->count()],
            'overdue'       => ['amount' => round($overdue->sum('amount'), 2), 'count' => $overdue->sum('count')],
            'due_7'         => ['amount' => $due['due_7']['amount'], 'count' => $due['due_7']['count']],
            'due_30'        => ['amount' => round($due['due_7']['amount'] + $due['due_30']['amount'], 2), 'count' => $due['due_7']['count'] + $due['due_30']['count']],
            'kinds'         => $split(GetPayables::KINDS, fn (object $row) => $row->kind),
            'due'           => $due->values()->all(),
            'age'           => $split(GetPayables::AGE_BUCKETS, fn (object $row) => GetPayables::ageBucket($row->date, $today)),
            'apart'         => [
                'not_invoiced'      => $apart(fn (object $row) => $row->kind === 'not_invoiced'),
                'awaiting_approval' => $apart(fn (object $row) => $row->kind === 'money_requests'),
                'in_wallet'         => $apart(fn (object $row) => $row->kind === 'agent_invoices'),
            ],
            'creditors'     => $converted->groupBy(fn (object $row) => $row->organisation_id.'|'.$row->creditor_key)
                ->map(fn (Collection $group) => [
                    'key'               => $group->first()->organisation_id.'|'.$group->first()->creditor_key,
                    'label'             => $group->first()->creditor_name,
                    'type'              => $group->first()->creditor_type,
                    'slug'              => $group->first()->creditor_slug,
                    'organisation_slug' => $group->first()->organisation_slug,
                    'organisation_code' => $group->first()->organisation_code,
                    'count'             => $group->count(),
                    'oldest'            => $group->min('date'),
                    'amount'            => $sum($group),
                ])
                ->sortByDesc('amount')->take(self::TOP_CREDITORS)->values()->all(),
            'currencies'    => $converted->groupBy('currency_code')
                ->map(fn (Collection $group, string $code) => [
                    'key'    => $code,
                    'label'  => $code,
                    'count'  => $group->count(),
                    'native' => round($group->sum(fn (object $row) => (float) $row->owed), 2),
                    'amount' => $sum($group),
                ])
                ->sortByDesc('amount')->values()->all(),
            'organisations' => $this->isGroup() ? $converted->groupBy('organisation_code')
                ->map(fn (Collection $group, string $code) => ['key' => $code, 'label' => $code, 'count' => $group->count(), 'amount' => $sum($group)])
                ->sortByDesc('amount')->values()->all() : [],
            'unconverted'   => $owed->whereNull('amount')->groupBy('currency_code')
                ->map(fn (Collection $group, string $code) => ['currency' => $code, 'amount' => round($group->sum(fn (object $row) => (float) $row->owed), 2)])
                ->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    /**
     * @return array<string, mixed>
     */
    public function inGroup(ActionRequest $request): array
    {
        $this->readGroup($request);

        return $this->handle();
    }

    public function htmlResponse(array $payables, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/PayablesDashboard',
            [
                'breadcrumbs'   => $this->payablesBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Payables'),
                'pageHead'      => [
                    'model'         => __('Payables'),
                    'title'         => __('Dashboard'),
                    'icon'          => ['fal', 'fa-file-invoice'],
                    'subNavigation' => $this->subNavigation(),
                ],
                'organisation'  => $this->organisationSlug,
                'organisations' => $this->organisationOptions(),
                'isGroup'       => $this->isGroup(),
                'currency'      => $this->parent()->currency->code,
                'kindLabels'    => collect(self::sections())->map(fn (array $section) => $section[0])->all(),
                'payables'      => $payables,
            ]
        );
    }
}
