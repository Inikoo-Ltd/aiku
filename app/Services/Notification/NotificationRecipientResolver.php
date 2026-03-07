<?php

namespace App\Services\Notification;

use App\Models\Catalogue\Shop;
use App\Models\Notifications\NotificationType;
use App\Models\Notifications\UserNotificationSetting;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;

class NotificationRecipientResolver
{
    /**
     * @param Order $order
     * @param string $notificationTypeSlug
     * @return Collection<User>
     */
    public function resolveForOrder(Order $order, string $notificationTypeSlug): Collection
    {
        $notificationType = NotificationType::where('slug', $notificationTypeSlug)->first();

        if (! $notificationType) {
            return collect();
        }

        $orderGroup = $order->group_id;
        $orderOrg = $order->organisation_id;
        $orderShop = $order->shop_id;
        $orderState = $order->state->value;

        // Fetch settings for active users (employees or guests)
        $settings = UserNotificationSetting::query()
            ->where('notification_type_id', $notificationType->id)
            ->where('is_enabled', true)
            ->whereHas('user', function ($q) {
                $q->where('status', true)
                  ->where(function ($sub) {
                      $sub->whereHas('employees', fn ($e) => $e->where('state', 'working'))
                          ->orWhereHas('guests', fn ($g) => $g->where('guests.status', true));
                  });
            })
            ->with(['user'])
            ->get();

        $validRecipients = collect();

        // Group settings by user to handle hierarchy
        $settingsByUser = $settings->groupBy('user_id');

        foreach ($settingsByUser as $userId => $userSettings) {
            $user = $userSettings->first()->user;

            $matchedSetting = null;

            // 1. Check Global
            $globalSetting = $userSettings->whereNull('scope_type')->whereNull('scope_id')->first();
            if ($globalSetting) {
                $matchedSetting = $globalSetting;
            }

            // 2. Check Group
            if (! $matchedSetting) {
                $groupSetting = $userSettings->where('scope_type', Group::class)->where('scope_id', $orderGroup)->first();
                if ($groupSetting) {
                    $matchedSetting = $groupSetting;
                }
            }

            // 3. Check Organisation
            if (! $matchedSetting) {
                $orgSetting = $userSettings->where('scope_type', Organisation::class)->where('scope_id', $orderOrg)->first();
                if ($orgSetting) {
                    $matchedSetting = $orgSetting;
                }
            }

            // 4. Check Shop
            if (! $matchedSetting) {
                $shopSetting = $userSettings->where('scope_type', Shop::class)->where('scope_id', $orderShop)->first();
                if ($shopSetting) {
                    $matchedSetting = $shopSetting;
                }
            }

            // If we found a matching scope setting, now check its filters
            if ($matchedSetting) {
                if ($this->isFilterMatchingOrder($matchedSetting, $orderState)) {
                    $validRecipients->push($user);
                }
            }
        }

        return $validRecipients;
    }

    private function isFilterMatchingOrder(UserNotificationSetting $setting, string $orderState): bool
    {
        $filters = $setting->filters;

        if (empty($filters)) {
            return true;
        }

        if (isset($filters['state']) && is_array($filters['state']) && ! empty($filters['state'])) {
            return in_array($orderState, $filters['state']);
        }

        return true;
    }
}
