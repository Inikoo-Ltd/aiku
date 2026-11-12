<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierAgreement;

use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementStatusEnum;
use App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierAgreementAcceptance;
use App\Models\SupplyChain\SupplierAgreementVersion;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The one place that says where a supplier stands with the Supplier Agreement, so every page shows the same:
 * the version to have accepted is the newest one published asking for a new acceptance (the first one always does);
 * a supplier whose latest acceptance is that version or later is Accepted, an earlier one is Outdated, none is Not
 * accepted. Before any version is published there is nothing to accept and no status.
 */
class GetSupplierAgreementStatus
{
    use AsObject;

    /** @var array<int, array{required: int|null, current: SupplierAgreementVersion|null}> */
    private array $versionsByGroup = [];

    public function currentVersion(Group|int $group): ?SupplierAgreementVersion
    {
        return $this->versions($group)['current'];
    }

    public function requiredVersionNumber(Group|int $group): ?int
    {
        return $this->versions($group)['required'];
    }

    /**
     * @return array{required: int|null, current: SupplierAgreementVersion|null}
     */
    private function versions(Group|int $group): array
    {
        $groupId = $group instanceof Group ? $group->id : $group;

        if (!array_key_exists($groupId, $this->versionsByGroup)) {
            $released = SupplierAgreementVersion::where('group_id', $groupId)->released();
            $first    = (clone $released)->min('version');

            $this->versionsByGroup[$groupId] = [
                'required' => $first === null ? null : (int) max($first, (int) (clone $released)->where('requires_reacceptance', true)->max('version')),
                'current'  => SupplierAgreementVersion::where('group_id', $groupId)->where('state', SupplierAgreementVersionStateEnum::PUBLISHED)->first(),
            ];
        }

        return $this->versionsByGroup[$groupId];
    }

    public function statusOf(?int $acceptedVersion, ?int $requiredVersion): ?SupplierAgreementStatusEnum
    {
        return match (true) {
            $requiredVersion === null         => null,
            $acceptedVersion === null         => SupplierAgreementStatusEnum::NOT_ACCEPTED,
            $acceptedVersion < $requiredVersion => SupplierAgreementStatusEnum::OUTDATED,
            default                           => SupplierAgreementStatusEnum::ACCEPTED,
        };
    }

    /**
     * The latest acceptance of each supplier, keyed by supplier id.
     *
     * @param  iterable<int>  $supplierIds
     *
     * @return Collection<int, SupplierAgreementAcceptance>
     */
    public function latestAcceptances(iterable $supplierIds): Collection
    {
        $ids = collect($supplierIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return SupplierAgreementAcceptance::whereIn('supplier_id', $ids)
            ->with('version:id,version,title')
            ->orderBy('supplier_id')
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('supplier_id')
            ->keyBy('supplier_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(Supplier $supplier): ?array
    {
        return $this->forSuppliers($supplier->group_id, [$supplier->id])[$supplier->id] ?? null;
    }

    /**
     * The status of several suppliers of one group with two reads, keyed by supplier id; empty while no version is
     * published.
     *
     * @param  iterable<int>  $supplierIds
     *
     * @return array<int, array<string, mixed>>
     */
    public function forSuppliers(int $groupId, iterable $supplierIds): array
    {
        $required = $this->requiredVersionNumber($groupId);
        if ($required === null) {
            return [];
        }

        $current     = $this->currentVersion($groupId);
        $acceptances = $this->latestAcceptances($supplierIds);

        return collect($supplierIds)->unique()->mapWithKeys(function (int $supplierId) use ($acceptances, $required, $current) {
            /** @var SupplierAgreementAcceptance|null $acceptance */
            $acceptance = $acceptances->get($supplierId);

            return [$supplierId => $this->present($this->statusOf($acceptance?->version?->version, $required), $acceptance, $current)];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(SupplierAgreementStatusEnum $status, ?SupplierAgreementAcceptance $acceptance, ?SupplierAgreementVersion $current): array
    {
        $acceptedVersion = $acceptance?->version?->version;

        return [
            'status'           => $status->value,
            'label'            => $this->label($status, $acceptedVersion),
            'colour'           => $status->colour(),
            'icon'             => $status->icon(),
            'accepted_version' => $acceptedVersion,
            'accepted_at'      => $acceptance?->accepted_at?->toIso8601String(),
            'authorised_person' => $acceptance?->authorised_person,
            'position'         => $acceptance?->position,
            'current_version'  => $current?->version,
            'newer_version_to_read' => $status === SupplierAgreementStatusEnum::ACCEPTED && $current && $acceptedVersion !== null && $acceptedVersion < $current->version,
        ];
    }

    public function label(SupplierAgreementStatusEnum $status, ?int $acceptedVersion): string
    {
        return match ($status) {
            SupplierAgreementStatusEnum::ACCEPTED     => __('Accepted v:version', ['version' => $acceptedVersion]),
            SupplierAgreementStatusEnum::OUTDATED     => __('Outdated (v:version)', ['version' => $acceptedVersion]),
            SupplierAgreementStatusEnum::NOT_ACCEPTED => __('Not accepted'),
        };
    }

    /**
     * The latest version each supplier accepted, as a correlated subquery on suppliers.id.
     */
    public static function acceptedVersionSql(string $supplierIdColumn = 'suppliers.id'): string
    {
        return '(select max(sav.version) from supplier_agreement_acceptances saa join supplier_agreement_versions sav on sav.id = saa.supplier_agreement_version_id where saa.supplier_id = '.$supplierIdColumn.')';
    }

    /**
     * The status as SQL, for lists that sort or filter on it; null while nothing is published.
     */
    public function statusSql(int $groupId, string $supplierIdColumn = 'suppliers.id'): ?string
    {
        $required = $this->requiredVersionNumber($groupId);
        if ($required === null) {
            return null;
        }

        $accepted = self::acceptedVersionSql($supplierIdColumn);

        return "case when $accepted is null then '".SupplierAgreementStatusEnum::NOT_ACCEPTED->value."' when $accepted < $required then '".SupplierAgreementStatusEnum::OUTDATED->value."' else '".SupplierAgreementStatusEnum::ACCEPTED->value."' end";
    }

    /**
     * @param  Builder|\Illuminate\Database\Query\Builder|\Spatie\QueryBuilder\QueryBuilder  $query
     * @param  array<int, string>  $statuses
     */
    public function whereStatus(object $query, int $groupId, array $statuses, string $supplierIdColumn = 'suppliers.id'): void
    {
        $sql = $this->statusSql($groupId, $supplierIdColumn);
        if ($sql === null) {
            return;
        }

        $query->whereIn(\Illuminate\Support\Facades\DB::raw($sql), $statuses);
    }

    /**
     * The Supplier Agreement filter of a suppliers list, with how many of the list's active suppliers stand where;
     * null while nothing is published.
     *
     * @param  callable(\Illuminate\Database\Eloquent\Builder): mixed  $scope  narrows the suppliers to the list's own
     *
     * @return array{label: string, elements: array<string, array<int, mixed>>, engine: callable}|null
     */
    public function elementGroup(int $groupId, callable $scope): ?array
    {
        $sql = $this->statusSql($groupId);
        if ($sql === null) {
            return null;
        }

        $counts = Supplier::query()->where('status', true)->tap($scope)
            ->selectRaw("$sql as agreement_status, count(*) as number")
            ->groupByRaw($sql)
            ->toBase()
            ->pluck('number', 'agreement_status');

        return [
            'label'    => __('Supplier Agreement'),
            'elements' => collect(SupplierAgreementStatusEnum::cases())->mapWithKeys(fn (SupplierAgreementStatusEnum $status) => [
                $status->value => [
                    $status->label(),
                    (int) ($counts[$status->value] ?? 0),
                    null,
                    ['icon' => $status->icon(), 'class' => 'text-'.$status->colour().'-500'],
                ],
            ])->all(),
            'engine'   => fn ($query, $elements) => $this->whereStatus($query, $groupId, $elements),
        ];
    }

    /**
     * Adds the status and the accepted version to a suppliers list query, for {@see badge()}.
     */
    public function selectStatus(object $query, int $groupId): void
    {
        $sql = $this->statusSql($groupId);
        if ($sql === null) {
            return;
        }

        $query->selectRaw("$sql as agreement_status")->selectRaw(self::acceptedVersionSql().' as agreement_accepted_version');
    }

    /**
     * @return array{status: string, label: string, colour: string, icon: string}|null
     */
    public function badge(?string $status, ?int $acceptedVersion): ?array
    {
        $status = $status ? SupplierAgreementStatusEnum::tryFrom($status) : null;
        if (!$status) {
            return null;
        }

        return [
            'status' => $status->value,
            'label'  => $this->label($status, $acceptedVersion),
            'colour' => $status->colour(),
            'icon'   => $status->icon(),
        ];
    }

    /**
     * Every acceptance of the supplier, newest first, for the staff pages.
     *
     * @return list<array<string, mixed>>
     */
    public function history(Supplier $supplier): array
    {
        return SupplierAgreementAcceptance::where('supplier_id', $supplier->id)
            ->with(['version:id,version,title', 'ipGeolocation:id,city,country'])
            ->orderByDesc('accepted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SupplierAgreementAcceptance $acceptance) => [
                'id'                  => $acceptance->id,
                'version'             => $acceptance->version->version,
                'supplier_legal_name' => $acceptance->supplier_legal_name,
                'authorised_person'   => $acceptance->authorised_person,
                'position'            => $acceptance->position,
                'accepted_at'         => $acceptance->accepted_at->toIso8601String(),
                'ip'                  => $acceptance->ip,
                'country_code'        => $acceptance->country_code,
                'city'                => $acceptance->ipGeolocation?->city,
                'locale'              => $acceptance->locale,
                'pdf_route'           => ['name' => 'grp.supply-chain.suppliers.agreement_acceptance.pdf', 'parameters' => ['supplier' => $supplier->slug, 'supplierAgreementAcceptance' => $acceptance->id]],
            ])
            ->all();
    }
}
