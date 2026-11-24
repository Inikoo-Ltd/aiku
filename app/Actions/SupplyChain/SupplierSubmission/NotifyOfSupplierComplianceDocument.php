<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-24 10:02
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\Procurement\OrgSupplierProducts\NotifyPurchasingOfSupplierProductAvailability;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Helpers\Language;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Models\SupplyChain\SupplierSubmission;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\User;
use App\Notifications\SupplierComplianceDocumentNotification;
use App\Notifications\SupplierComplianceDocumentSentToOwnerNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Traits\Localizable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A supplier sent a compliance document for one of its products. The people who review it are told: the purchasing
 * lead and last buyer of every organisation that buys the product, and the compliance team. The supplier's owner
 * (the portal contact with the supplier's own email) gets an email too, unless the owner sent it.
 */
class NotifyOfSupplierComplianceDocument
{
    use AsAction;
    use Localizable;

    public const array COMPLIANCE_ROLES = [
        RolesEnum::COMPLIANCE_MANAGER,
        RolesEnum::COMPLIANCE_SUPERVISOR,
        RolesEnum::COMPLIANCE_WORKER,
    ];

    /**
     * @var array<int, string>
     */
    private array $languageCodes = [];

    public function handle(SupplierSubmission $submission): int
    {
        $submission->loadMissing(['supplier', 'supplierProduct', 'portalContact']);

        $reviewers = $this->reviewers($submission);
        foreach ($reviewers as $user) {
            $this->withLocale($this->userLocale($user), function () use ($submission, $user) {
                $title = __(':supplier sent :document for :code', [
                    'supplier' => $submission->supplier->code,
                    'document' => $this->documentLabel($submission),
                    'code'     => $submission->supplierProduct?->code,
                ]);
                $body  = $this->body($submission);
                $why   = __('You get this email because you buy this product, lead purchasing in an organisation that buys it, or review compliance documents.');

                NotifyUserOnChosenChannels::run(
                    $user,
                    UserNotificationEnum::SUPPLIER_COMPLIANCE_DOCUMENT,
                    new SupplierComplianceDocumentNotification($submission, $title, $body, $why),
                    $title,
                    $body,
                    SupplierComplianceDocumentNotification::url($submission)
                );
            });
        }

        $this->tellOwner($submission);

        return $reviewers->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function reviewers(SupplierSubmission $submission): Collection
    {
        setPermissionsTeamId($submission->group_id);

        $buyers = $submission->supplierProduct?->orgSupplierProducts()
            ->whereHas('organisation', fn ($query) => $query->where('type', '!=', OrganisationTypeEnum::AGENT))
            ->get()
            ->flatMap(fn ($orgSupplierProduct) => NotifyPurchasingOfSupplierProductAvailability::make()->purchasingLeads($orgSupplierProduct, null)) ?? collect();

        $roleNames  = Role::whereIn('name', array_map(fn (RolesEnum $role) => $role->value, self::COMPLIANCE_ROLES))->pluck('name')->all();
        $compliance = $roleNames ? User::where('status', true)->whereIn('id', User::role($roleNames)->select('users.id'))->get() : collect();

        return collect($buyers)->concat($compliance)
            ->unique('id')
            ->filter(fn (User $user) => DecideSupplierSubmission::canDecide($user, $submission) || $user->authTo(['supply-chain.view', 'compliance.view']))
            ->values();
    }

    /**
     * Tells the person whose decision the compliance team changed, with the reason, if they may still see it.
     */
    public function tellOverriddenDecider(SupplierSubmission $submission, int $userId, SupplierSubmissionStateEnum $previousState, ?string $reason): void
    {
        $user = User::where('status', true)->find($userId);
        if (!$user || !DecideSupplierSubmission::canDecide($user, $submission)) {
            return;
        }

        $submission->loadMissing(['supplier', 'supplierProduct'])->load('decidedBy');
        $states = SupplierSubmissionStateEnum::labels();

        $this->withLocale($this->userLocale($user), function () use ($submission, $user, $previousState, $reason, $states) {
            $title = __(':user changed your decision on :document for :code', [
                'user'     => $submission->decidedBy?->contact_name ?: $submission->decidedBy?->username,
                'document' => $this->documentLabel($submission),
                'code'     => $submission->supplierProduct?->code,
            ]);
            $body  = __(':from → :to. :reason', [
                'from'   => $states[$previousState->value],
                'to'     => $states[$submission->state->value],
                'reason' => (string) $reason,
            ]);
            $why   = __('You get this email because you decided this supplier compliance document.');

            NotifyUserOnChosenChannels::run(
                $user,
                UserNotificationEnum::SUPPLIER_COMPLIANCE_OVERRIDDEN,
                new SupplierComplianceDocumentNotification($submission, $title, $body, $why),
                $title,
                $body,
                SupplierComplianceDocumentNotification::url($submission)
            );
        });
    }

    public function owner(SupplierSubmission $submission): ?SupplierPortalContact
    {
        $email = mb_strtolower(trim((string) $submission->supplier->email));
        if ($email === '') {
            return null;
        }

        return SupplierPortalContact::authorised()
            ->where('supplier_id', $submission->supplier_id)
            ->where('email', $email)
            ->first();
    }

    private function tellOwner(SupplierSubmission $submission): void
    {
        $owner = $this->owner($submission);
        if (!$owner || $owner->id === $submission->supplier_portal_contact_id) {
            return;
        }

        $sender = $submission->portalContact?->name ?: $submission->portalContact?->email;
        $title  = __(':sender sent :document for :code', [
            'sender'   => $sender ?: __('A contact of :supplier', ['supplier' => $submission->supplier->officialName()]),
            'document' => $this->documentLabel($submission),
            'code'     => $submission->supplierProduct?->code,
        ]);
        $why    = __('You get this email because you are the main contact of :supplier on the :group supplier portal.', [
            'supplier' => $submission->supplier->officialName(),
            'group'    => $submission->group->name,
        ]);

        Notification::route('mail', $owner->email)->notify(
            (new SupplierComplianceDocumentSentToOwnerNotification($title, $this->body($submission), $why))->locale(app()->getLocale())
        );
    }

    private function documentLabel(SupplierSubmission $submission): string
    {
        $documentType = (string) Arr::get($submission->data, 'document_type');

        return TradeAttachmentScopeEnum::labels()[$documentType] ?? $documentType;
    }

    private function body(SupplierSubmission $submission): string
    {
        $parts = array_filter([
            $submission->supplierProduct?->name,
            Arr::get($submission->data, 'purchase_order_reference') ? __('For purchase order :reference', ['reference' => Arr::get($submission->data, 'purchase_order_reference')]) : null,
            $submission->expires_on ? __('Expires :date', ['date' => $submission->expires_on->toDateString()]) : null,
            Arr::get($submission->data, 'note'),
        ]);

        return $parts ? implode('. ', $parts).'.' : $this->documentLabel($submission);
    }

    private function userLocale(User $user): string
    {
        return $this->languageCodes[$user->language_id] ??= Language::find($user->language_id)?->code ?: config('app.locale');
    }
}
