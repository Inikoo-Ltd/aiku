<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-01h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\Goods\ProductDevelopment\UpdateProductDevelopmentStageDueDate;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistItemStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFileScopeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTypeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Models\Goods\PackagingFamily;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Helpers\Audit;
use App\Models\Production\Production;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductDevelopmentActivity
{
    use AsAction;

    public const int LIMIT = 100;

    private bool $canOpenProfiles = false;

    private bool $canSeeCosts = true;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(ProductDevelopment $productDevelopment, int $limit = self::LIMIT, ?User $viewer = null): array
    {
        $this->canOpenProfiles = (bool) $viewer?->authTo('sysadmin.view');
        $this->canSeeCosts     = !$viewer || (new ProductDevelopmentAccess())->canSeeCosts($viewer, $productDevelopment);

        $checklistItemIds = $productDevelopment->checklistItems()->pluck('id');

        $audits = Audit::query()
            ->where(function (Builder $query) use ($productDevelopment, $checklistItemIds) {
                $query->where(fn (Builder $query) => $query
                    ->where('auditable_type', 'ProductDevelopment')
                    ->where('auditable_id', $productDevelopment->id))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('auditable_type', 'ProductDevelopmentChecklistItem')
                        ->whereIn('auditable_id', $checklistItemIds)
                        ->where('event', '!=', 'created'));
            })
            ->with('user')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $checklistItems = ProductDevelopmentChecklistItem::with('item.tradeUnit')
            ->whereIn('id', $audits->where('auditable_type', 'ProductDevelopmentChecklistItem')->pluck('auditable_id'))
            ->get()
            ->keyBy('id');

        return $audits
            ->map(fn (Audit $audit) => $this->entry($audit, $productDevelopment, $checklistItems->get($audit->auditable_id)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entry(Audit $audit, ProductDevelopment $productDevelopment, ?ProductDevelopmentChecklistItem $checklistItem): ?array
    {
        $old = $audit->old_values ?? [];
        $new = $audit->new_values ?? [];

        if ($audit->auditable_type === 'ProductDevelopmentChecklistItem') {
            [$sentence, $changes] = $this->checklistSentence($checklistItem, $new);
        } else {
            [$sentence, $changes] = match ($audit->event) {
                'created' => [__('Created :code', ['code' => $productDevelopment->code]), []],
                RecordProductDevelopmentActivity::STAGE_MOVED => [__('Moved from :from to :to', ['from' => $old['stage'] ?? '—', 'to' => $new['stage'] ?? '—']), []],
                RecordProductDevelopmentActivity::SKUS_ADDED => [trans_choice('Added :count SKU|Added :count SKUs', count($new['skus'] ?? []), ['count' => count($new['skus'] ?? [])]), [['label' => __('SKUs'), 'old' => null, 'new' => implode(', ', $new['skus'] ?? [])]]],
                RecordProductDevelopmentActivity::MEMBERS_ADDED => [__('Added members'), [['label' => __('Members'), 'old' => null, 'new' => implode(', ', $new['members'] ?? [])]]],
                RecordProductDevelopmentActivity::FILES_UPLOADED => [$this->filesSentence($new), [['label' => __('Files'), 'old' => null, 'new' => implode(', ', $new['files'] ?? [])]]],
                UpdateProductDevelopmentStageDueDate::STAGE_DUE_CHANGED => [__('Changed the due date of :stage', ['stage' => $new['stage'] ?? '']), [['label' => __('Due'), 'old' => $this->displayValue('target_online_date', $old['due_at'] ?? null), 'new' => $this->displayValue('target_online_date', $new['due_at'] ?? null)]]],
                RecordProductDevelopmentActivity::SUPPLIER_PRODUCT_LINKED => [($new['created'] ?? false)
                    ? __('Created supplier product :code for :sku', ['code' => $new['supplier_product'] ?? '', 'sku' => $new['sku'] ?? ''])
                    : __('Linked supplier product :code to :sku', ['code' => $new['supplier_product'] ?? '', 'sku' => $new['sku'] ?? '']), []],
                RecordProductDevelopmentActivity::DESIGN_VERSION_STARTED => [__('Started :round v:version', $this->designWords($new)), []],
                RecordProductDevelopmentActivity::DESIGN_SUBMITTED => [__('Sent :round v:version for review', $this->designWords($new)), []],
                RecordProductDevelopmentActivity::DESIGN_APPROVED => [__('Approved :round v:version', $this->designWords($new)), []],
                RecordProductDevelopmentActivity::DESIGN_CHANGES_REQUESTED => [__('Requested changes on :round v:version', $this->designWords($new)), []],
                RecordProductDevelopmentActivity::SIGNED_OFF => [($new['locked'] ?? false)
                    ? __('Signed off the artwork as :role, the artwork is now locked', ['role' => mb_strtolower(ProductDevelopmentMemberRoleEnum::labels()[$new['role'] ?? ''] ?? '')])
                    : __('Signed off the artwork as :role', ['role' => mb_strtolower(ProductDevelopmentMemberRoleEnum::labels()[$new['role'] ?? ''] ?? '')]), []],
                RecordProductDevelopmentActivity::SIGN_OFF_WITHDRAWN => [__('Withdrew the :role sign-off, the artwork is unlocked', ['role' => mb_strtolower(ProductDevelopmentMemberRoleEnum::labels()[$new['role'] ?? ''] ?? '')]), []],
                RecordProductDevelopmentActivity::REMINDERS_SENT => [($new['roles'] ?? []) ? __('Sent reminders to :roles', ['roles' => implode(', ', $new['roles'])]) : __('Sent reminders, but nobody holds the roles it is waiting on'), []],
                RecordProductDevelopmentActivity::DESIGN_ROUND_SKIPPED => [__('Skipped the :round round', $this->designWords($new)), []],
                RecordProductDevelopmentActivity::DESIGN_ROUND_UNSKIPPED => [__('Brought back the :round round', $this->designWords($new)), []],
                RecordProductDevelopmentActivity::PURCHASE_ORDER_LINKED => [__('Linked purchase order :reference', ['reference' => $new['purchase_order'] ?? '']), []],
                RecordProductDevelopmentActivity::PURCHASE_ORDER_CREATED => [__('Raised purchase order :reference', ['reference' => $new['purchase_order'] ?? '']), []],
                RecordProductDevelopmentActivity::PURCHASE_ORDER_UNLINKED => [__('Unlinked purchase order :reference', ['reference' => $new['purchase_order'] ?? '']), []],
                RecordProductDevelopmentActivity::DESIGN_SECTION_ADDED => [__('Added the :section design section', ['section' => $new['section'] ?? '']), []],
                RecordProductDevelopmentActivity::DESIGN_SECTION_CHANGED => [__('Changed the :section design section', ['section' => $new['section'] ?? '']), $this->sectionChanges($old, $new)],
                RecordProductDevelopmentActivity::DESIGN_SECTION_REMOVED => [__('Removed the :section design section', ['section' => $old['section'] ?? '']), []],
                RecordProductDevelopmentActivity::ARTWORK_UPLOADED => [__('Uploaded :artwork v:version', ['artwork' => $new['artwork'] ?? '', 'version' => $new['version'] ?? '']), $this->noteChange(__('What changed'), $new['note'] ?? null)],
                RecordProductDevelopmentActivity::ARTWORK_SUBMITTED => [__('Sent :artwork v:version for review', ['artwork' => $new['artwork'] ?? '', 'version' => $new['version'] ?? '']), $this->noteChange(__('What changed'), $new['note'] ?? null)],
                RecordProductDevelopmentActivity::ARTWORK_APPROVED => [__('Approved :artwork v:version', ['artwork' => $new['artwork'] ?? '', 'version' => $new['version'] ?? '']), $this->noteChange(__('Comment'), $new['comment'] ?? null)],
                RecordProductDevelopmentActivity::SAMPLE_JOB_CREATED => [__('Asked :person for samples of :skus', ['person' => $new['assignee'] ?? '', 'skus' => implode(', ', $new['skus'] ?? [])]), $this->noteChange(__('Instructions'), $new['instructions'] ?? null)],
                RecordProductDevelopmentActivity::COMPONENT_ADDED => [__('Added :component to :sku (:quantity)', ['component' => $new['component'] ?? '', 'sku' => $new['sku'] ?? '', 'quantity' => $new['quantity'] ?? '']), []],
                RecordProductDevelopmentActivity::COMPONENT_CHANGED => [__('Changed :component on :sku', ['component' => $new['component'] ?? '', 'sku' => $new['sku'] ?? '']), array_values(array_filter([
                    isset($old['component']) && ($old['component'] !== ($new['component'] ?? null)) ? ['label' => __('Component'), 'old' => $old['component'], 'new' => $new['component'] ?? null] : null,
                    isset($old['quantity']) && ($old['quantity'] !== ($new['quantity'] ?? null)) ? ['label' => __('Quantity'), 'old' => $old['quantity'], 'new' => $new['quantity'] ?? null] : null,
                ]))],
                RecordProductDevelopmentActivity::COMPONENT_REMOVED => [__('Removed :component from :sku', ['component' => $new['component'] ?? '', 'sku' => $new['sku'] ?? '']), []],
                RecordProductDevelopmentActivity::COMPONENT_APPROVED => [($new['approved'] ?? true) ? __('Approved :component for purchasing', ['component' => $new['component'] ?? '']) : __('Took back the approval of :component', ['component' => $new['component'] ?? '']), []],
                RecordProductDevelopmentActivity::COMPONENT_PURCHASE_CONFIRMED => [($new['confirmed'] ?? true) ? __('Confirmed :component is bought', ['component' => $new['component'] ?? '']) : __('Took back the purchase of :component', ['component' => $new['component'] ?? '']), $this->noteChange(__('Note'), $new['note'] ?? null)],
                RecordProductDevelopmentActivity::SAMPLE_STARTED => [__('Started :sample', ['sample' => $new['sample'] ?? '']), []],
                RecordProductDevelopmentActivity::SAMPLE_READY => [__(':sample is ready for review', ['sample' => $new['sample'] ?? '']), $this->noteChange(__('Notes'), $new['notes'] ?? null)],
                RecordProductDevelopmentActivity::SAMPLE_APPROVED => [__('Approved :sample', ['sample' => $new['sample'] ?? '']), $this->noteChange(__('Comment'), $new['comment'] ?? null)],
                RecordProductDevelopmentActivity::SAMPLE_CHANGES_REQUIRED => [__('Asked for changes on :sample', ['sample' => $new['sample'] ?? '']), $this->noteChange(__('Comment'), $new['comment'] ?? null)],
                RecordProductDevelopmentActivity::ARTWORK_CHANGES_REQUIRED => [__('Asked for changes on :artwork v:version', ['artwork' => $new['artwork'] ?? '', 'version' => $new['version'] ?? '']), $this->noteChange(__('Comment'), $new['comment'] ?? null)],
                RecordProductDevelopmentActivity::SKU_BRIEF_CHANGED => [__('Changed the brief of :sku', ['sku' => $new['sku'] ?? '']), [['label' => __('Brief'), 'old' => $this->displayValue('brief', $old['brief'] ?? null), 'new' => $this->displayValue('brief', $new['brief'] ?? null)]]],
                RecordProductDevelopmentActivity::FILE_REMOVED => [__('Removed :file', ['file' => $old['file'] ?? '']), []],
                RecordProductDevelopmentActivity::MEMBERS_REMOVED => [__('Removed members'), [['label' => __('Members'), 'old' => implode(', ', $old['members'] ?? []), 'new' => null]]],
                'updated' => [__('Updated the development'), $this->fieldChanges($old, $new)],
                default => [Str::headline($audit->event), []],
            };
        }

        if ($sentence === null) {
            return null;
        }

        $user     = $audit->user instanceof User ? $audit->user : null;
        $userName = $user ? ($user->contact_name ?: $user->username) : __('System');

        return [
            'id'          => $audit->id,
            'user'        => $userName,
            'user_id'     => $user?->id,
            'username'    => $user?->username,
            'avatar'      => $user?->image_id ? $user->imageSources(48, 48) : null,
            'profile_url' => $user && $this->canOpenProfiles ? route('grp.sysadmin.users.show', ['user' => $user->slug]) : null,
            'initials'   => Str::upper(Str::substr(Str::of($userName)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->implode(''), 0, 2)),
            'created_at' => $audit->created_at?->toIso8601String(),
            'sentence'   => $sentence,
            'changes'    => $changes,
        ];
    }

    private function filesSentence(array $new): string
    {
        $count = count($new['files'] ?? []);
        $scope = ProductDevelopmentFileScopeEnum::labels()[$new['scope'] ?? ''] ?? __('files');
        $where = filled($new['sku'] ?? null) ? ' '.__('for :sku', ['sku' => $new['sku']]) : '';
        $which = filled($new['version'] ?? null) ? ' (v'.$new['version'].')' : '';

        return trans_choice('Uploaded :count file|Uploaded :count files', $count, ['count' => $count]).': '.$scope.$which.$where;
    }

    /**
     * @return array{0: string|null, 1: array<int, array<string, mixed>>}
     */
    private function checklistSentence(?ProductDevelopmentChecklistItem $checklistItem, array $new): array
    {
        if (!$checklistItem || !array_key_exists('state', $new)) {
            return [null, []];
        }

        if (!$this->canSeeCosts && ProductDevelopmentTargetFieldEnum::tryFrom((string) $checklistItem->target_field)?->isCost()) {
            return [null, []];
        }

        $target = $checklistItem->item?->tradeUnit?->code;
        $label  = $target ? $checklistItem->label.' ('.$target.')' : $checklistItem->label;

        $sentence = match ($new['state']) {
            ProductDevelopmentChecklistItemStateEnum::COMPLETE->value => __(':item is done', ['item' => $label]),
            ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE->value => __(':item marked N/A', ['item' => $label]),
            default => __(':item is missing again', ['item' => $label]),
        };

        $changes = filled($new['na_reason'] ?? null) ? [['label' => __('Reason'), 'old' => null, 'new' => $new['na_reason']]] : [];

        return [$sentence, $changes];
    }

    /**
     * @return array<int, array{label: string, old: string|null, new: string|null}>
     */
    private function sectionChanges(array $old, array $new): array
    {
        $labels = ['name' => __('Name'), 'description' => __('Description'), 'packaging_supplier' => __('Packaging supplier')];

        return collect($labels)
            ->filter(fn (string $label, string $field) => array_key_exists($field, $new))
            ->map(fn (string $label, string $field) => ['label' => $label, 'old' => $old[$field] ?? null, 'new' => $new[$field] ?? null])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, old: string|null, new: string|null}>
     */
    private function noteChange(string $label, ?string $note): array
    {
        return $note ? [['label' => $label, 'old' => null, 'new' => $note]] : [];
    }

    /**
     * @return array<int, array{label: string, old: string|null, new: string|null}>
     */
    private function fieldChanges(array $old, array $new): array
    {
        $labels = [
            'name'                 => __('Name'),
            'kind'                 => __('Kind'),
            'type'                 => __('Type'),
            'status'               => __('Status'),
            'brief'                => __('Brief'),
            'markets'              => __('Markets'),
            'owner_id'             => __('Owner'),
            'supplier_id'          => __('Supplier'),
            'agent_id'             => __('Agent'),
            'production_id'        => __('Production'),
            'trade_unit_family_id' => __('Trade unit family'),
            'packaging_family_id'  => __('Packaging family'),
            'target_landing_date'  => __('Target landing'),
            'target_online_date'   => __('Target online'),
            'checklist_category'   => __('Checklist category'),
        ];

        return collect($new)
            ->keys()
            ->merge(array_keys($old))
            ->unique()
            ->filter(fn (string $field) => isset($labels[$field]))
            ->map(fn (string $field) => [
                'label' => $labels[$field],
                'old'   => $this->displayValue($field, $old[$field] ?? null),
                'new'   => $this->displayValue($field, $new[$field] ?? null),
            ])
            ->values()
            ->all();
    }

    private function displayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($field) {
            'kind' => ProductDevelopmentKindEnum::labels()[$value] ?? (string) $value,
            'type' => ProductDevelopmentTypeEnum::labels()[$value] ?? (string) $value,
            'status' => ProductDevelopmentStatusEnum::labels()[$value] ?? (string) $value,
            'markets' => collect(is_string($value) ? json_decode($value, true) : $value)->map(fn ($market) => TradeUnitMarketEnum::labels()[$market] ?? $market)->implode(', '),
            'owner_id' => User::find($value)?->contact_name ?? (string) $value,
            'supplier_id' => Supplier::find($value)?->name ?? (string) $value,
            'agent_id' => Agent::find($value)?->name ?? (string) $value,
            'production_id' => Production::find($value)?->name ?? (string) $value,
            'trade_unit_family_id' => TradeUnitFamily::find($value)?->code ?? (string) $value,
            'packaging_family_id' => PackagingFamily::find($value)?->name ?? (string) $value,
            'target_landing_date', 'target_online_date' => Carbon::parse($value)->format('j M Y'),
            'brief' => Str::limit((string) $value, 140),
            default => (string) $value,
        };
    }

    /**
     * @param array<string, mixed> $new
     *
     * @return array{round: string, version: string}
     */
    private function designWords(array $new): array
    {
        $round = ProductDevelopmentDesignRoundEnum::labels()[$new['round'] ?? ''] ?? (string) ($new['round'] ?? '');

        return [
            'round'   => !empty($new['sku']) ? $round.' '.$new['sku'] : $round,
            'version' => (string) ($new['version'] ?? ''),
        ];
    }
}
