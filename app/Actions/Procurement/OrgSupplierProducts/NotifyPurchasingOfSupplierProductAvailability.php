<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductAvailabilityStatusEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductUnavailableReasonEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Helpers\Language;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\User;
use App\Notifications\SupplierProductAvailabilityChangedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsAction;
use NumberFormatter;
use Spatie\Permission\Models\Permission;

/**
 * Tells the purchasing lead of every organisation that buys a product, and whoever last ordered it there, when its
 * agent changes its availability or the supplier proposes a new price from its page.
 */
class NotifyPurchasingOfSupplierProductAvailability
{
    use AsAction;
    use Localizable;

    /**
     * @var array<int, string>
     */
    private array $languageCodes = [];

    public function handle(SupplierProduct $supplierProduct, ?User $changedBy): int
    {
        $orgSupplierProducts = $supplierProduct->orgSupplierProducts()
            ->whereHas('organisation', fn ($query) => $query->where('type', '!=', OrganisationTypeEnum::AGENT))
            ->with('organisation')
            ->get();

        $notifiedUserIds = [];
        foreach ($orgSupplierProducts as $orgSupplierProduct) {
            foreach ($this->purchasingLeads($orgSupplierProduct, $changedBy) as $user) {
                if (isset($notifiedUserIds[$user->id])) {
                    continue;
                }
                $this->withLocale($this->userLocale($user), function () use ($supplierProduct, $changedBy, $orgSupplierProduct, $user) {
                    $title = __(':code: :status', ['code' => $supplierProduct->code, 'status' => SupplierProductAvailabilityStatusEnum::labels()[$supplierProduct->availability_status->value]]);
                    $body  = $this->body($supplierProduct, $changedBy);

                    NotifyUserOnChosenChannels::run(
                        $user,
                        UserNotificationEnum::SUPPLIER_PRODUCT_AVAILABILITY,
                        new SupplierProductAvailabilityChangedNotification($orgSupplierProduct, $title, $body),
                        $title,
                        $body,
                        route('grp.org.procurement.org_supplier_products.show', [$orgSupplierProduct->organisation->slug, $orgSupplierProduct->slug])
                    );
                });
                $notifiedUserIds[$user->id] = true;
            }
        }

        return count($notifiedUserIds);
    }

    private function userLocale(User $user): string
    {
        return $this->languageCodes[$user->language_id] ??= Language::find($user->language_id)?->code ?: config('app.locale');
    }

    private function body(SupplierProduct $supplierProduct, ?User $changedBy): string
    {
        $parts = array_filter([
            $supplierProduct->name,
            $supplierProduct->unavailable_reason ? __('Reason: :reason', ['reason' => SupplierProductUnavailableReasonEnum::labels()[$supplierProduct->unavailable_reason->value]]) : null,
            $supplierProduct->available_from ? __('Available from :date', ['date' => $supplierProduct->available_from->toDateString()]) : null,
            $supplierProduct->availability_new_cost !== null ? __('New cost asked: :cost', ['cost' => $this->unitCost((float) $supplierProduct->availability_new_cost, $supplierProduct->currency->code)]) : null,
            $supplierProduct->availability_note,
            $changedBy ? __('Recorded by :user', ['user' => $changedBy->contact_name ?: $changedBy->username]) : null,
        ]);

        return implode('. ', $parts).'.';
    }

    private function unitCost(float $amount, string $currencyCode): string
    {
        $formatter = new NumberFormatter(app()->getLocale(), NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 4);

        return $formatter->formatCurrency($amount, $currencyCode);
    }

    /**
     * @return Collection<int, User>
     */
    private function purchasingLeads(OrgSupplierProduct $orgSupplierProduct, ?User $changedBy): Collection
    {
        $permissions = Permission::where('name', "org-supervisor.$orgSupplierProduct->organisation_id.procurement")->pluck('name')->all();
        $buyerId     = DB::table('purchase_order_transactions as pot')
            ->join('purchase_orders as po', 'po.id', 'pot.purchase_order_id')
            ->where('pot.org_supplier_product_id', $orgSupplierProduct->id)
            ->whereNull('po.deleted_at')
            ->whereNotNull('po.submitted_at')
            ->orderByDesc('po.submitted_at')
            ->value('po.buyer_id');
        if (!$permissions && !$buyerId) {
            return collect();
        }

        return User::where('status', true)
            ->where(fn ($query) => $query
                ->when($buyerId, fn ($query) => $query->where('users.id', $buyerId))
                ->when($permissions, fn ($query) => $query->orWhereIn('users.id', User::permission($permissions)->select('users.id'))))
            ->when($changedBy, fn ($query) => $query->where('users.id', '!=', $changedBy->id))
            ->get();
    }
}
