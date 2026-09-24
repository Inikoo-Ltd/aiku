<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 08 Dec 2023 22:08:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Actions\SysAdmin\User\UI\GetUserOrganisationLayout;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLayout
{
    use AsAction;

    public function handle(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return [
            'group'          => $this->getGroupData($user->group),
            'has_group_access' => $user->hasGroupAccess(),
            'organisations'  => GetUserOrganisationLayout::make()->getOrganisations($user),
            'agents'         => GetUserOrganisationLayout::make()->getAgents($user),
            'digital_agency' => GetUserOrganisationLayout::make()->getDigitalAgencies($user),
            'bookmarks'      => $user->bookmarks,
            'navigation'     => [
                'grp' => GetGroupNavigation::run($user),
                'org' => GetOrganisationsLayout::run($user),
            ],
            'app_theme'      => $user->settings['app_theme'] ?? null,
            'org_themes'     => $this->getOrganisationThemes($user),
            'chat_theme'     => $user->settings['chat_theme'] ?? 'dracula',
            'staff_chat'     => [
                'quick_replies' => Arr::get($user->group->settings, 'staff_chat.quick_replies') ?: null,
            ],


        ];
    }

    /**
     * Keyed by organisation slug because the front end only knows which organisation it is in from
     * the route parameter, and the layout props are cached per user, never per organisation.
     *
     * @return array<string, string>
     */
    public function getOrganisationThemes(User $user): array
    {
        if (!Arr::get($user->settings, 'org_themes.enabled')) {
            return [];
        }

        $themes = Arr::get($user->settings, 'org_themes.themes', []);
        if (!is_array($themes) || $themes === []) {
            return [];
        }

        $slugs = $user->authorisedOrganisations()
            ->get(['organisations.id', 'organisations.slug'])
            ->pluck('slug', 'id')
            ->all();

        $coloursBySlug = [];
        foreach ($themes as $organisationTheme) {
            $slug   = Arr::get($slugs, (int) Arr::get($organisationTheme, 'organisation_id'));
            $colour = Arr::get($organisationTheme, 'colour');

            if ($slug && is_string($colour) && $colour !== '') {
                $coloursBySlug[$slug] = $colour;
            }
        }

        return $coloursBySlug;
    }

    public function getGroupData(Group $group): array
    {
        $currency = $group->currency;
        return [
            'id'       => $group->id,
            'slug'     => $group->slug,
            'label'    => $group->name,
            'logo'     => $group->imageSources(48, 48),
            'currency' => [
                'id'     => $currency->id,
                'code'   => $currency->code,
                'name'   => $currency->name,
                'symbol' => $currency->symbol
            ]
        ];
    }
}
