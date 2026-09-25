<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairAuroraPurchaseOrderBuyers
{
    use AsAction;

    public string $commandSignature = 'repair:aurora_purchase_order_buyers {organisations?*} {--N|dry_run}';
    public string $commandDescription = 'Fill the buyer of purchase orders fetched from Aurora from their Aurora main buyer';

    /** @var array<string, int|null> keyed by organisation id and Aurora staff key, staff keys repeat across Aurora databases */
    private array $userIdsByStaffKey = [];

    /**
     * @return array{organisation: string, filled: int, unmatched_staff: int}
     */
    public function handle(Organisation $organisation, bool $dryRun = false): array
    {
        $buyers    = $this->auroraBuyers($organisation);
        $handles   = $this->auroraUserHandles($organisation);
        $filled    = 0;
        $unmatched = 0;

        PurchaseOrder::where('organisation_id', $organisation->id)
            ->whereNull('buyer_id')
            ->whereNotNull('source_id')
            ->chunkById(500, function ($purchaseOrders) use ($organisation, $buyers, $handles, $dryRun, &$filled, &$unmatched) {
                foreach ($purchaseOrders as $purchaseOrder) {
                    $buyer = $buyers[explode(':', $purchaseOrder->source_id)[1] ?? ''] ?? null;
                    if (!$buyer) {
                        continue;
                    }

                    $userId = $this->userIdForStaff($organisation, (string) $buyer['staff_key'], $handles[$buyer['staff_key']] ?? null, $buyer['name']);
                    if (!$userId) {
                        $unmatched++;

                        continue;
                    }

                    if (!$dryRun) {
                        UpdatePurchaseOrder::make()->action($purchaseOrder, ['buyer_id' => $userId], strict: false, audit: false);
                    }
                    $filled++;
                }
            });

        return [
            'organisation'    => $organisation->slug,
            'filled'          => $filled,
            'unmatched_staff' => $unmatched,
        ];
    }

    /**
     * @return array<string, array{staff_key: int|string, name: ?string}> Aurora purchase order key => its main buyer
     */
    protected function auroraBuyers(Organisation $organisation): array
    {
        $this->connectAurora($organisation);

        return DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->where('Purchase Order Main Buyer Key', '>', 0)
            ->get(['Purchase Order Key', 'Purchase Order Main Buyer Key', 'Purchase Order Main Buyer Name'])
            ->mapWithKeys(fn (object $row) => [
                $row->{'Purchase Order Key'} => [
                    'staff_key' => $row->{'Purchase Order Main Buyer Key'},
                    'name'      => $row->{'Purchase Order Main Buyer Name'},
                ],
            ])
            ->all();
    }

    /**
     * @return array<int|string, string> Aurora staff key => its Aurora login handle
     */
    protected function auroraUserHandles(Organisation $organisation): array
    {
        $this->connectAurora($organisation);

        return DB::connection('aurora')
            ->table('User Dimension')
            ->where('User Type', 'Staff')
            ->where('User Parent Key', '>', 0)
            ->pluck('User Handle', 'User Parent Key')
            ->all();
    }

    private function connectAurora(Organisation $organisation): void
    {
        $organisationSource = new AuroraOrganisationService();
        $organisationSource->initialisation($organisation);
    }

    /**
     * Directors and group staff buy for companies they are not employees of, so after the employee link comes the
     * Aurora login handle (the aiku username) and, last, the buyer's name: a full name against the user's name, a
     * single name against the username, only when exactly one user matches.
     */
    private function userIdForStaff(Organisation $organisation, string $staffKey, ?string $handle, ?string $name): ?int
    {
        $cacheKey = $organisation->id.':'.$staffKey;
        if (array_key_exists($cacheKey, $this->userIdsByStaffKey)) {
            return $this->userIdsByStaffKey[$cacheKey];
        }

        $employee = Employee::withTrashed()->where('source_id', $organisation->id.':'.$staffKey)->first();
        $userId   = $employee?->users()->first()?->id ?? $employee?->user_id;

        if (!$userId && $handle) {
            $userId = User::where('group_id', $organisation->group_id)->whereRaw('lower(username) = ?', [mb_strtolower(trim($handle))])->value('id');
        }

        if (!$userId && $name) {
            $column  = str_contains(trim($name), ' ') ? 'contact_name' : 'username';
            $matches = User::where('group_id', $organisation->group_id)->whereRaw("lower($column) = ?", [mb_strtolower(trim($name))])->limit(2)->pluck('id');
            $userId  = $matches->count() === 1 ? $matches->first() : null;
        }

        return $this->userIdsByStaffKey[$cacheKey] = $userId;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $command->option('dry_run');

        $query = Organisation::query()
            ->where('type', OrganisationTypeEnum::SHOP->value)
            ->whereNotNull('source');
        if ($command->argument('organisations')) {
            $query->whereIn('slug', $command->argument('organisations'));
        }

        foreach ($query->get() as $organisation) {
            if (!Arr::get($organisation->source, 'db_name')) {
                continue;
            }
            $result = $this->handle($organisation, $dryRun);
            $command->info(sprintf(
                '%s: %d purchase orders %s a buyer, %d with a buyer who has no aiku user',
                $result['organisation'],
                $result['filled'],
                $dryRun ? 'would get' : 'got',
                $result['unmatched_staff']
            ));
        }

        return 0;
    }
}
