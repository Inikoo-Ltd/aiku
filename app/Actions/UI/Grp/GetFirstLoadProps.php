<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Feb 2024 07:08:53 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp;

use App\Actions\SysAdmin\User\GetUserOrderAlerts;
use App\Actions\Helpers\Ticket\GetTicketBadgeData;
use App\Actions\Tasks\GetStaffTaskBadgeData;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn;
use App\Actions\HumanResources\Leave\GetUserLeaveCovers;
use App\Actions\Helpers\TimeZone\Json\IndexTimeZones;
use App\Actions\Catalogue\Shop\External\Faire\GetFaireSkippedBadgeData;
use App\Actions\Catalogue\Product\GetProductsNeedReviewBadgeData;
use App\Actions\Dispatching\WaitingItems\GetCrmReturnedBadgeData;
use App\Actions\Dispatching\WaitingItems\GetCrmWaitingBadgeData;
use App\Actions\Masters\MasterAsset\GetMasterUpdatedBadgeData;
use App\Actions\Dispatching\WaitingItems\GetDispatchingWaitingBadgeData;
use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\UI\Grp\Layout\GetLayout;
use App\Actions\UI\Grp\Layout\GetOrganisationsLayout;
use App\Http\Resources\SysAdmin\NotificationsResource;
use App\Models\DevOps\AppDeployment;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;
use Sentry;
use Throwable;

class GetFirstLoadProps
{
    use AsObject;

    private const array MENU_SOURCES = [
        'Actions/UI/Grp/Layout/*.php',
        'Actions/UI/Grp/GetFirstLoadProps.php',
        'Actions/SysAdmin/User/UI/GetUserOrganisationLayout.php',
        'Actions/Chat/WithChatNavigation.php',
        'Actions/Chat/WithChatAgentAuthorisation.php',
        'Actions/Goods/ProductDevelopment/ProductDevelopmentAccess.php',
        'Actions/Traits/Authorisations/WithShopPpcPermissions.php',
    ];

    /** @var array<string, string> */
    private static array $menuVersions = [];

    /**
     * Badges and notifications are not here: the layout fetches them from grp.json.layout.badges
     * after the page has drawn. Only the menu of the organisation the page is in is sent; the
     * layout fetches another organisation's from grp.json.layout.organisation_navigation when the
     * user moves to it.
     */
    public function handle(?User $user, ?Organisation $organisation = null): array
    {
        $language = $this->language($user);

        $props = $this->remember($user, $this->cacheKey($user, $language), fn () => $this->getUserUiProps($user, $language));

        if ($user && $organisation && $this->isAuthorisedFor($user, $organisation)) {
            data_set($props, 'layout.navigation.org', [$organisation->slug => $this->organisationNavigation($user, $organisation)]);
        }

        $lastDeployment = AppDeployment::latest()->first();
        data_set($props, 'last_deployment_at', $lastDeployment?->created_at);
        data_set($props, 'last_deployment_hash', $lastDeployment?->commit_hash);
        data_set($props, 'last_deployment_version', $lastDeployment?->semantic_version);

        // Set outside the cached props so changing the group's clocks does not need a recache of every user
        if ($user) {
            data_set($props, 'layout.group.timezones', collect($user->group->world_clock_timezones)
                ->map(fn (string $timezone) => [
                    'timezone' => $timezone,
                    'place'    => IndexTimeZones::make()->clockNameFor($timezone),
                ])
                ->all());
        }


        return $props;
    }

    public function isAuthorisedFor(User $user, Organisation $organisation): bool
    {
        return $user->authorisedOrganisations()->where('organisations.id', $organisation->id)->exists();
    }

    public function organisationNavigation(User $user, Organisation $organisation): array
    {
        $language = $this->language($user);

        return $this->remember(
            $user,
            $this->cacheKey($user, $language).':org:'.$organisation->slug,
            function () use ($user, $organisation, $language) {
                $localeOfCaller = App::getLocale();
                App::setLocale($language->code);

                try {
                    return GetOrganisationsLayout::run($user, $organisation);
                } finally {
                    App::setLocale($localeOfCaller);
                }
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function badges(User $user): array
    {
        return [
            'notifications'              => NotificationsResource::collection($user->notifications()->orderBy('created_at', 'desc')->limit(10)->get())->collection,
            'ticket_badges'              => GetTicketBadgeData::run($user),
            'task_badges'                => GetStaffTaskBadgeData::run($user),
            'development_badges'         => ProductDevelopmentWaitingOn::railBadge($user),
            'leave_covers'               => GetUserLeaveCovers::run($user),
            'dispatching_waiting_count'  => GetDispatchingWaitingBadgeData::make()->totalCount($user),
            'crm_waiting_count'          => GetCrmWaitingBadgeData::make()->totalCount($user),
            'crm_return_count'           => GetCrmReturnedBadgeData::make()->totalCount($user),
            'master_updated_count'       => GetMasterUpdatedBadgeData::make()->totalCount($user),
            'products_need_review_count' => GetProductsNeedReviewBadgeData::make()->totalCount($user),
            'faire_skipped_count'        => GetFaireSkippedBadgeData::make()->totalCount($user),
        ];
    }

    public function cacheKey(?User $user, Language $language): string
    {
        return 'grp-first-load-props:'.($user?->id ?? 'guest').':'.$language->code.':'.self::menuVersion($language);
    }

    /**
     * ponytail: a deploy that leaves the menu builders, the language file and the route names alone
     * keeps every cached menu. A menu change made from a file outside MENU_SOURCES shows after
     * `php artisan ui:recache-user-props`; add that file to MENU_SOURCES if it happens again.
     */
    public static function menuVersion(Language $language): string
    {
        return self::$menuVersions[$language->code] ??= md5(implode('|', [
            ...array_map('md5_file', collect(self::MENU_SOURCES)->flatMap(fn (string $pattern) => glob(app_path($pattern)))->sort()->all()),
            is_file(lang_path($language->code.'.json')) ? md5_file(lang_path($language->code.'.json')) : '',
            md5(implode(',', array_keys(app('router')->getRoutes()->getRoutesByName()))),
        ]));
    }

    private function remember(?User $user, string $cacheKey, Closure $compute): mixed
    {
        if (!config('ui.cache.layout') || $user?->permissionsLender()) {
            return $compute();
        }

        try {
            return Cache::tags('grp-first-load-props:'.($user?->id ?? 'guest'))->remember($cacheKey, now()->addDays(7), $compute);
        } catch (Throwable $e) {
            Sentry::captureException($e);

            return $compute();
        }
    }

    private function language(?User $user): Language
    {
        $language = $user ? $user->language : Language::where('code', App::currentLocale())->first();

        return $language ?? Language::where('code', 'en')->first();
    }

    /**
     * These props are cached per language, so they must be built in that language rather than
     * in whatever locale the request that happens to warm the cache is running under. Recaches
     * triggered from the console, from queues, or by another user would otherwise store one
     * user's language under another's cache key.
     */
    public function getUserUiProps(?User $user, Language $language): array
    {
        $localeOfCaller = App::getLocale();
        App::setLocale($language->code);

        try {
            return $this->buildUserUiProps($user, $language);
        } finally {
            App::setLocale($localeOfCaller);
        }
    }

    private function buildUserUiProps(?User $user, Language $language): array
    {
        $availableLanguages = Language::where('status', true)->pluck('id')->toArray();

        $image = null;
        if ($user && !blank($user->image_id)) {
            $image = $user->imageSources(0, 48);
        }

        return [
            'localeData' => [
                'locale_iso'            => getIsoLocale($language->code),
                'language'              => [
                    'id'          => $language->id,
                    'code'        => $language->code,
                    'name'        => $language->name,
                    'native_name' => $language->native_name,
                    'flag'        => $language->flag

                ],
                'languageOptions'       => GetLanguagesOptions::make()->getExtraGroupLanguages($availableLanguages),
                'languageAssetsOptions' => GetLanguagesOptions::make()->translated(),
            ],

            'layout'           => GetLayout::run($user),
            'environment'      => app()->environment(),
            'help_portal_url'  => config('app.help_portal_url'),
            'avatar_thumbnail' => $image,
            'order_alerts'     => $user ? GetUserOrderAlerts::run($user) : null,
        ];
    }

}
