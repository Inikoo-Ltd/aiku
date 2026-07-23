<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\ShopPermissionsEnum;
use App\Models\CRM\WebUser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Overview of a shop\'s website: webpage counts by state, registered web users, and failed login totals.')]
class WebsiteOverviewTool extends AikuTool
{
    protected function permission(): ShopPermissionsEnum
    {
        return ShopPermissionsEnum::WEB_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'shop' => ['required', 'string'],
        ]);

        $shop = $this->authorisedShop($request);
        if (!$shop) {
            return Response::error('Shop not found or permission denied.');
        }

        $website = $shop->website;
        if (!$website) {
            return Response::error('Shop has no website.');
        }

        $stats = $website->webStats;

        $failedLogins = WebUser::where('website_id', $website->id)
            ->join('web_user_stats', 'web_users.id', '=', 'web_user_stats.web_user_id')
            ->selectRaw('coalesce(sum(web_user_stats.number_failed_logins), 0) as total_failed_logins')
            ->first();

        return Response::json([
            'website_name'       => $website->name,
            'domain'             => $website->domain,
            'webpages'           => [
                'total'      => (int) $stats->number_webpages,
                'in_process' => (int) $stats->number_webpages_state_in_process,
                'ready'      => (int) $stats->number_webpages_state_ready,
                'live'       => (int) $stats->number_webpages_state_live,
                'closed'     => (int) $stats->number_webpages_state_closed,
            ],
            'web_users'          => (int) $stats->number_web_users,
            'current_web_users'  => (int) $stats->number_current_web_users,
            'total_failed_logins' => (int) $failedLogins->total_failed_logins,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'shop' => $schema->string()->description('Shop slug')->required(),
        ];
    }
}
