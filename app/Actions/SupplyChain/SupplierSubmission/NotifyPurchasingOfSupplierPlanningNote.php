<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 20:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\Procurement\OrgSupplierProducts\NotifyPurchasingOfSupplierProductAvailability;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Helpers\Language;
use App\Models\SupplyChain\SupplierSubmission;
use App\Models\SysAdmin\User;
use App\Notifications\SupplierPlanningNoteNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tells the purchasing lead of every organisation that buys the product, and whoever last ordered it there, when the
 * supplier sends material planning for it from the Plan for next orders page. Overdue notes go to the people set in
 * the supplier page settings instead (EscalateOverdueSupplierPlanningNotes).
 */
class NotifyPurchasingOfSupplierPlanningNote
{
    use AsAction;
    use Localizable;

    /**
     * @var array<int, string>
     */
    private array $languageCodes = [];

    public function handle(SupplierSubmission $submission): int
    {
        $supplierProduct = $submission->supplierProduct;
        if (!$supplierProduct) {
            return 0;
        }

        $users = $supplierProduct->orgSupplierProducts()
            ->whereHas('organisation', fn ($query) => $query->where('type', '!=', OrganisationTypeEnum::AGENT))
            ->with('organisation')
            ->get()
            ->flatMap(fn ($orgSupplierProduct) => NotifyPurchasingOfSupplierProductAvailability::make()->purchasingLeads($orgSupplierProduct, null))
            ->unique('id');

        return $this->send($submission, $users, UserNotificationEnum::SUPPLIER_MATERIAL_PLANNING);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public function send(SupplierSubmission $submission, Collection $users, UserNotificationEnum $event): int
    {
        $submission->loadMissing(['supplier', 'supplierProduct']);

        foreach ($users as $user) {
            $this->withLocale($this->userLocale($user), function () use ($submission, $user, $event) {
                $title = $event === UserNotificationEnum::SUPPLIER_PLANNING_OVERDUE
                    ? __(':supplier: material planning for :code still not answered', ['supplier' => $submission->supplier->code, 'code' => $submission->supplierProduct?->code])
                    : __(':supplier: material planning for :code', ['supplier' => $submission->supplier->code, 'code' => $submission->supplierProduct?->code]);
                $body  = $this->body($submission);
                $why   = $event === UserNotificationEnum::SUPPLIER_PLANNING_OVERDUE
                    ? __('You get this email because you are set to hear about supplier planning notes nobody has answered.')
                    : __('You get this email because you buy this product or lead purchasing in an organisation that buys it.');

                NotifyUserOnChosenChannels::run(
                    $user,
                    $event,
                    new SupplierPlanningNoteNotification($submission, $title, $body, $why),
                    $title,
                    $body,
                    SupplierPlanningNoteNotification::url($submission)
                );
            });
        }

        return $users->count();
    }

    private function userLocale(User $user): string
    {
        return $this->languageCodes[$user->language_id] ??= Language::find($user->language_id)?->code ?: config('app.locale');
    }

    private function body(SupplierSubmission $submission): string
    {
        $data  = $submission->data ?? [];
        $parts = array_filter([
            $submission->supplierProduct?->name,
            Arr::get($data, 'material') ? __('Material: :material', ['material' => Arr::get($data, 'material')]) : null,
            Arr::get($data, 'reserve_by') ? __('Reserves it until :date', ['date' => Arr::get($data, 'reserve_by')]) : null,
            Arr::get($data, 'note'),
        ]);

        return implode('. ', $parts).'.';
    }
}
