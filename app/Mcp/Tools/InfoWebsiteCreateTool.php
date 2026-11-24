<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Mon, 23 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Web\StaticWebsite\StoreStaticWebsite;
use App\Actions\Web\StaticWebsite\StoreStaticWebsiteStarter;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Web\StaticWebsite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Starts a new info website for a domain that has none yet, to be written from scratch: it creates the website with a starter home page (/) and three shared parts, header (site name and menu), footer and styles (the CSS of every page). Before calling, tell the user the domain and name it will get and ask them to confirm; pass their confirmation verbatim as request_text. Then build it with info-websites-tool and info-website-preview-tool: update_page / for the home page, create_page for new pages (they copy the home page; put content_html in <section> elements), update_component header to add menu links like <div class="header-nav-item"><a href="/about">About</a></div>, update_component styles to change fonts and colours. The website is not public until a developer launches it and its DNS points to Aiku. Refused when the domain already belongs to an info website or a shop website. Only for group administrators.')]
class InfoWebsiteCreateTool extends AikuInfoWebsiteTool
{
    use WithMcpChangeLog;

    public function handle(Request $request): Response
    {
        $request->validate([
            'domain'       => ['required', 'string', 'max:255'],
            'name'         => ['required', 'string', 'max:255'],
            'request_text' => ['required', 'string', 'max:4000'],
        ]);

        if (!$request->user()?->can_create_mcp_info_websites) {
            return Response::error('A new info website can only be started by a group administrator. Do not retry; nothing was changed.');
        }

        $user = $request->user();

        try {
            $staticWebsite = DB::transaction(function () use ($request, $user) {
                $staticWebsite = StoreStaticWebsite::make()->action($user->group, [
                    'domain' => (string) $request->string('domain'),
                    'name'   => trim((string) $request->string('name')),
                ]);

                $this->recordChange(
                    $request,
                    McpChangeTypeEnum::INFO_WEBSITE_CONTENT,
                    $staticWebsite->domain.': new info website with a starter home page',
                    ['static_website_id' => $staticWebsite->id, 'pages' => ['/'], 'components' => StoreStaticWebsiteStarter::COMPONENTS],
                    fn () => StoreStaticWebsiteStarter::run($staticWebsite, 'AI change for '.$user->username.': '.$request->string('request_text')),
                    [
                        'domain'              => $staticWebsite->domain,
                        'operation'           => 'create_website',
                        'before_snapshot_ids' => [
                            'pages'      => ['/' => null],
                            'components' => array_fill_keys(StoreStaticWebsiteStarter::COMPONENTS, null),
                        ],
                    ]
                );

                return $staticWebsite;
            });
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was created.');
        }

        /** @var StaticWebsite $staticWebsite */
        return Response::json([
            'created'       => true,
            'website'       => $staticWebsite->domain,
            'name'          => $staticWebsite->name,
            'state'         => $staticWebsite->state->value,
            'pages'         => [['path' => '/', 'preview_url' => $this->previewUrl($staticWebsite)]],
            'shared_parts'  => StoreStaticWebsiteStarter::COMPONENTS,
            'change_log_id' => $this->mcpChange?->id,
            'next'          => 'Show the user the preview_url. Ask what the home page should say and which pages the site needs, then read the home page with info-websites-tool and prepare each change with info-website-preview-tool. Going live (launch and DNS) is done by a developer; tell the user to ask for it once the pages are ready.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'domain'       => $schema->string()->description('Domain the website will be served on, without www, e.g. hop-hare.com')->required(),
            'name'         => $schema->string()->description('Name of the website, shown in Aiku, the header and the browser title, e.g. Hop Hare')->required(),
            'request_text' => $schema->string()->description('The user\'s confirmation, verbatim, kept in the change log')->required(),
        ];
    }
}
