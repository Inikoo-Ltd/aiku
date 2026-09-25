<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-14h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Helpers\Media\UI;

use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Helpers\Media;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprover;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadAttachment
{
    use AsAction;

    public function handle(Media $media): BinaryFileResponse
    {
        $filename = $media->media_scope == 'labeling_guide' ? $media->name : $media->file_name;

        return response()->download($media->getPath(), $filename);
    }

    public function asController(Media $media, ActionRequest $request): BinaryFileResponse
    {
        if (!$this->canDownload($media, $request->user())) {
            abort(404);
        }

        return $this->handle($media);
    }

    public function canDownload(Media $media, User $user): bool
    {
        $verdicts = $this->owners($media)
            ->map(fn (Model $owner) => $this->canViewOwner($owner, $user))
            ->reject(fn (?bool $verdict) => $verdict === null);

        return $verdicts->isEmpty() || $verdicts->contains(true);
    }

    /**
     * @return Collection<int, Model>
     */
    private function owners(Media $media): Collection
    {
        return DB::table('model_has_attachments')
            ->where('media_id', $media->id)
            ->get(['model_type', 'model_id'])
            ->push((object) ['model_type' => $media->model_type, 'model_id' => $media->model_id])
            ->map(function (object $owner) {
                $class = Relation::getMorphedModel($owner->model_type) ?? $owner->model_type;

                return $owner->model_id && class_exists($class) ? $class::find($owner->model_id) : null;
            })
            ->filter()
            ->values();
    }

    private function canViewOwner(Model $owner, User $user): ?bool
    {
        return match (true) {
            $owner instanceof Employee => $this->isHumanResources($user, $owner->organisation_id)
                || $this->isOwnEmployee($user, $owner->id),
            $owner instanceof Leave, $owner instanceof AttendanceAdjustment => $this->isHumanResources($user, $owner->organisation_id)
                || $this->isOwnEmployee($user, $owner->employee_id)
                || $this->isLeaveApprover($user, $owner->organisation_id),
            $owner instanceof Clocking => $this->isHumanResources($user, $owner->organisation_id),
            $owner instanceof Customer => $user->authTo(array_filter([
                "crm.$owner->shop_id.view",
                "accounting.$owner->organisation_id.view",
                $owner->shop?->fulfilment ? "fulfilment-shop.{$owner->shop->fulfilment->id}.view" : null,
            ])),
            $owner instanceof Order, $owner instanceof Invoice => $user->authTo([
                "orders.$owner->shop_id.view",
                "crm.$owner->shop_id.view",
                "accounting.$owner->organisation_id.view",
            ]),
            $owner instanceof PalletDelivery, $owner instanceof PalletReturn => $user->authTo(array_filter([
                "fulfilment-shop.$owner->fulfilment_id.view",
                "supervisor-fulfilment-shop.$owner->fulfilment_id",
                "accounting.$owner->organisation_id.view",
                $owner->warehouse_id ? "fulfilment.$owner->warehouse_id.view" : null,
                $owner->warehouse_id ? "supervisor-fulfilment.$owner->warehouse_id" : null,
            ])),
            $owner instanceof StockDelivery => $user->authTo([
                "procurement.$owner->organisation_id.view",
                "accounting.$owner->organisation_id.view",
                'supply-chain.view',
                ...$this->goodsInPermissions($owner->organisation_id),
            ]),
            $owner instanceof PurchaseOrder => $user->authTo([
                "procurement.$owner->organisation_id.view",
                "accounting.$owner->organisation_id.view",
                'supply-chain.view',
            ]),
            $owner instanceof Supplier => $user->authTo(['supply-chain.view']),
            default => null,
        };
    }

    private function isHumanResources(User $user, int $organisationId): bool
    {
        return $user->authTo(["human-resources.$organisationId.view", "org-supervisor.$organisationId.human-resources"]);
    }

    /**
     * @return array<int, string>
     */
    private function goodsInPermissions(int $organisationId): array
    {
        return Warehouse::where('organisation_id', $organisationId)
            ->pluck('id')
            ->flatMap(fn (int $warehouseId) => ["incoming.$warehouseId.view", "supervisor-incoming.$warehouseId"])
            ->all();
    }

    private function isOwnEmployee(User $user, int $employeeId): bool
    {
        return $user->employees()->whereKey($employeeId)->exists();
    }

    private function isLeaveApprover(User $user, int $organisationId): bool
    {
        return LeaveApprover::where('organisation_id', $organisationId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}
