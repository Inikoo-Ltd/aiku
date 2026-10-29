<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-22h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistItemStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentDesignRound;
use App\Events\BroadcastProductDevelopmentBadgeUpdate;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\DeferProp;
use Inertia\Inertia;

/**
 * What an active development is waiting for and from whom: required checklist items still missing,
 * design versions to draw or review, the agent's print-limit check and the final sign-offs. One source
 * for the reminders, the "Waiting on me" page and the dashboard widget.
 */
class ProductDevelopmentWaitingOn
{
    public const string KIND_CHECKLIST = 'checklist';
    public const string KIND_DESIGN = 'design';
    public const string KIND_REVIEW = 'review';
    public const string KIND_PRINT_LIMITS = 'print_limits';
    public const string KIND_SIGN_OFF = 'sign_off';

    public const string REMINDED_SETTING = 'product_development_reminded';

    public const int RAIL_BADGE_CACHE_SECONDS = 120;

    public const int WIDGET_LIMIT = 6;

    private ProductDevelopmentAccess $access;

    public function __construct()
    {
        $this->access = new ProductDevelopmentAccess();
    }

    /**
     * Everything the development waits on, grouped by the role that has to act.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function byRole(ProductDevelopment $productDevelopment): array
    {
        if ($productDevelopment->status !== ProductDevelopmentStatusEnum::ACTIVE) {
            return [];
        }

        $rows = collect();

        $productDevelopment->checklistItems()
            ->with('item.tradeUnit')
            ->where('required', true)
            ->where('state', ProductDevelopmentChecklistItemStateEnum::MISSING->value)
            ->get()
            ->groupBy(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->role->value.'|'.$checklistItem->key)
            ->each(function (Collection $items) use ($rows, $productDevelopment) {
                /** @var ProductDevelopmentChecklistItem $first */
                $first = $items->first();
                $skus  = $items->map(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->item?->tradeUnit?->code)->filter()->values();
                $rows->push([
                    'role'   => $first->role->value,
                    'kind'   => self::KIND_CHECKLIST,
                    'items'  => $items->pluck('id')->values()->all(),
                    'label'  => $first->label,
                    'detail' => $skus->count() > 3 ? __(':count SKUs', ['count' => $skus->count()]) : $skus->implode(', '),
                    'route'  => ['name' => 'grp.product_developments.show', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'tab' => $first->role === ProductDevelopmentMemberRoleEnum::COMPLIANCE ? 'compliance' : ($first->level->value === 'family' ? 'brief' : 'products')]],
                ]);
            });

        $this->designRows($productDevelopment, $rows);

        if (SignOffProductDevelopment::isOpenForSignOff($productDevelopment)) {
            foreach (SignOffProductDevelopment::signOffs($productDevelopment) as $role => $signOff) {
                if (!$signOff) {
                    $rows->push([
                        'role'   => $role,
                        'kind'   => self::KIND_SIGN_OFF,
                        'label'  => __('Sign off the final artwork'),
                        'detail' => null,
                        'route'  => ['name' => 'grp.product_developments.show', 'parameters' => ['productDevelopment' => $productDevelopment->slug]],
                    ]);
                }
            }
        }

        return $rows->groupBy('role')->map(fn (Collection $roleRows) => $roleRows->values()->all())->all();
    }

    private function designRows(ProductDevelopment $productDevelopment, Collection $rows): void
    {
        if (ProductDevelopmentDesignRounds::isArtworkLocked($productDevelopment)) {
            if ($productDevelopment->artworks()->whereNotNull('sample_reopened_at')->exists()) {
                $this->artworkRows($productDevelopment, $rows);
            }

            return;
        }

        $round = ProductDevelopmentDesignRoundEnum::CONCEPT;
        if (!ProductDevelopmentDesignRounds::isSettled($productDevelopment, $round)) {
            $latest = ProductDevelopmentDesignRounds::latest($productDevelopment, $round);
            $label  = ProductDevelopmentDesignRoundEnum::labels()[$round->value];
            $route  = ['name' => 'grp.product_developments.design', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'round' => $round->value]];

            if (!$latest || in_array($latest->status, [ProductDevelopmentDesignRoundStatusEnum::DRAFT, ProductDevelopmentDesignRoundStatusEnum::CHANGES_REQUESTED], true)) {
                $rows->push([
                    'role'   => ProductDevelopmentMemberRoleEnum::DESIGNER->value,
                    'kind'   => self::KIND_DESIGN,
                    'label'  => !$latest ? __('Start the :round design', ['round' => $label]) : ($latest->status === ProductDevelopmentDesignRoundStatusEnum::DRAFT ? __('Finish :round v:version and send it for review', ['round' => $label, 'version' => $latest->version]) : __('Make the changes asked on :round v:version', ['round' => $label, 'version' => $latest->version])),
                    'detail' => null,
                    'route'  => $route,
                ]);
            } elseif ($latest->status === ProductDevelopmentDesignRoundStatusEnum::IN_REVIEW) {
                $this->reviewRows($latest, $label, $route, $rows);
            }
        }

        $this->artworkRows($productDevelopment, $rows);
    }

    /**
     * One row per design section: what the designer still has to upload or fix, and what waits for review.
     */
    private function artworkRows(ProductDevelopment $productDevelopment, Collection $rows): void
    {
        $itemIds = $productDevelopment->items()->pluck('id');
        $route   = ['name' => 'grp.product_developments.show', 'parameters' => ['productDevelopment' => $productDevelopment->slug, 'tab' => 'design']];

        foreach ($productDevelopment->designSections()->with('artworks')->get() as $section) {
            $statuses = $itemIds->map(fn (int $itemId) => $section->artworks->firstWhere('product_development_item_id', $itemId)?->status ?? ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED);
            $toMake   = $statuses->filter(fn (ProductDevelopmentArtworkStatusEnum $status) => $status === ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED)->count();
            $toFix    = $statuses->filter(fn (ProductDevelopmentArtworkStatusEnum $status) => $status === ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED)->count();
            $toReview = $statuses->filter(fn (ProductDevelopmentArtworkStatusEnum $status) => $status === ProductDevelopmentArtworkStatusEnum::IN_REVIEW)->count();

            if ($toMake || $toFix) {
                $rows->push([
                    'role'   => ProductDevelopmentMemberRoleEnum::DESIGNER->value,
                    'kind'   => self::KIND_DESIGN,
                    'label'  => $toFix
                        ? trans_choice('Fix :count :section artwork|Fix :count :section artworks', $toFix, ['count' => $toFix, 'section' => $section->name])
                        : trans_choice('Upload :count :section artwork|Upload :count :section artworks', $toMake, ['count' => $toMake, 'section' => $section->name]),
                    'detail' => $toFix && $toMake ? trans_choice(':count more to upload|:count more to upload', $toMake, ['count' => $toMake]) : null,
                    'route'  => $route,
                ]);
            }

            if ($toReview) {
                foreach (ProductDevelopmentAccess::DESIGN_APPROVER_ROLES as $role) {
                    $rows->push([
                        'role'   => $role->value,
                        'kind'   => self::KIND_REVIEW,
                        'label'  => trans_choice('Review :count :section artwork|Review :count :section artworks', $toReview, ['count' => $toReview, 'section' => $section->name]),
                        'detail' => null,
                        'route'  => $route,
                    ]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $route
     */
    private function reviewRows(ProductDevelopmentDesignRound $designRound, string $label, array $route, Collection $rows): void
    {
        if (array_key_exists(ProductDevelopmentDesignRounds::CHECK_PRINT_LIMITS, ProductDevelopmentDesignRounds::checks($designRound->productDevelopment))
            && empty(Arr::get($designRound->data, 'checks.'.ProductDevelopmentDesignRounds::CHECK_PRINT_LIMITS))) {
            $rows->push([
                'role'   => ProductDevelopmentMemberRoleEnum::AGENT->value,
                'kind'   => self::KIND_PRINT_LIMITS,
                'label'  => __('Confirm :round v:version is within the factory print limits', ['round' => $label, 'version' => $designRound->version]),
                'detail' => null,
                'route'  => $route,
            ]);
        }

        foreach (ProductDevelopmentAccess::DESIGN_APPROVER_ROLES as $role) {
            $rows->push([
                'role'   => $role->value,
                'kind'   => self::KIND_REVIEW,
                'label'  => __('Review :round v:version', ['round' => $label, 'version' => $designRound->version]),
                'detail' => null,
                'route'  => $route,
            ]);
        }
    }

    /**
     * What waits on one person across the developments they take part in: the rows of the roles they
     * hold, and design reviews as owner. A row shared by two of their roles shows once.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user, int $limit = 100): array
    {
        $developments = ProductDevelopment::query()
            ->where('status', ProductDevelopmentStatusEnum::ACTIVE->value)
            ->where(fn ($query) => $query->where('owner_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))
            ->with(['stages'])
            ->orderBy('id')
            ->get();

        $rows = collect();
        foreach ($developments as $productDevelopment) {
            $roles = array_map(fn (ProductDevelopmentMemberRoleEnum $role) => $role->value, $this->access->memberRoles($user, $productDevelopment));
            $isOwner = $productDevelopment->owner_id === $user->id;
            $dueAt   = $productDevelopment->stages->where('position', $productDevelopment->current_position)->pluck('due_at')->filter()->min();

            foreach ($this->byRole($productDevelopment) as $role => $roleRows) {
                foreach ($roleRows as $row) {
                    $mine = in_array($role, $roles, true) || ($isOwner && $row['kind'] === self::KIND_REVIEW);
                    if (!$mine) {
                        continue;
                    }
                    $rows->push([
                        ...$row,
                        'role_label'  => ProductDevelopmentMemberRoleEnum::labels()[$role],
                        'development' => ['code' => $productDevelopment->code, 'name' => $productDevelopment->name, 'slug' => $productDevelopment->slug],
                        'due_at'      => $dueAt?->toDateString(),
                        'is_late'     => $dueAt && $dueAt->isPast() && !$dueAt->isToday(),
                    ]);
                }
            }
        }

        return $rows
            ->unique(fn (array $row) => $row['development']['code'].'|'.$row['kind'].'|'.$row['label'])
            ->sortBy(fn (array $row) => ($row['due_at'] ?? '9999-12-31').'|'.$row['development']['code'])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * The dashboard widget, only for someone taking part in an active development; loaded after the page.
     */
    public static function dashboardWidget(User $user): ?DeferProp
    {
        if (!self::takesPart($user)) {
            return null;
        }

        return Inertia::defer(function () use ($user) {
            $rows = (new self())->forUser($user);

            return [
                'rows'  => array_slice($rows, 0, self::WIDGET_LIMIT),
                'total' => count($rows),
                'route' => ['name' => 'grp.product_developments.waiting_on_me', 'parameters' => []],
            ];
        });
    }

    public static function takesPart(User $user): bool
    {
        return ProductDevelopment::query()
            ->where('status', ProductDevelopmentStatusEnum::ACTIVE->value)
            ->where(fn ($query) => $query->where('owner_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))
            ->exists();
    }

    /**
     * The rail badge: how much waits on the person and how much of it is late; null for anyone not taking
     * part in an active development, so the badge never shows for them. It is shown on every full page load,
     * and working it out reads each of the person's developments, so it is kept for two minutes; a reminder
     * and opening the badge refresh it at once.
     *
     * @return array{waiting: int, late: int, reminded: bool}|null
     */
    public static function railBadge(User $user): ?array
    {
        return Cache::remember(self::railBadgeCacheKey($user), self::RAIL_BADGE_CACHE_SECONDS, fn () => self::computeRailBadge($user));
    }

    public static function forgetRailBadge(User $user): void
    {
        Cache::forget(self::railBadgeCacheKey($user));
    }

    private static function railBadgeCacheKey(User $user): string
    {
        return 'product-development-rail-badge:'.$user->id;
    }

    /**
     * @return array{waiting: int, late: int, reminded: bool}|null
     */
    private static function computeRailBadge(User $user): ?array
    {
        if (!self::takesPart($user)) {
            return null;
        }

        $rows = (new self())->forUser($user);

        return [
            'waiting'  => count($rows),
            'late'     => count(array_filter($rows, fn (array $row) => $row['is_late'])),
            'reminded' => (bool) Arr::get($user->settings ?? [], self::REMINDED_SETTING),
        ];
    }

    /**
     * Someone sent this person a reminder: their rail badge pulses until they open it.
     */
    public static function markReminded(User $user): void
    {
        $user->refresh();
        $user->updateQuietly(['settings' => [...($user->settings ?? []), self::REMINDED_SETTING => true]]);
        self::forgetRailBadge($user);
        BroadcastProductDevelopmentBadgeUpdate::dispatch($user->id, self::railBadge($user));
    }

    public static function markSeen(User $user): void
    {
        $user->refresh();
        if (Arr::get($user->settings ?? [], self::REMINDED_SETTING)) {
            $user->updateQuietly(['settings' => [...($user->settings ?? []), self::REMINDED_SETTING => false]]);
        }
        self::forgetRailBadge($user);
    }
}
