<?php

namespace App\Actions\Web\WebsiteDialog\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Enums\Web\WebsiteDialog\WebsiteDialogDisplayFrequencyEnum;
use App\Enums\Web\WebsiteDialog\WebsiteDialogTriggerEnum;
use App\Http\Resources\Web\WebsiteDialogResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use App\Models\Web\WebsiteDialog;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use App\Actions\Web\WebsiteDialog\WithWebsiteDialogScope;
use Lorisleiva\Actions\ActionRequest;

class ShowWebsiteDialogWorkshop extends OrgAction
{
    use WithWebsiteDialogScope;
    use WithWebEditAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, Website $website, WebsiteDialog $websiteDialog, ActionRequest $request): WebsiteDialog
    {
        $this->ensureWebsiteDialogScope($shop, $website, $websiteDialog);
        $this->initialisationFromShop($shop, $request);

        return $websiteDialog;
    }

    public function htmlResponse(WebsiteDialog $websiteDialog, ActionRequest $request): Response
    {
        $modelRouteParameters = [
            'shop'          => $websiteDialog->website->shop_id,
            'website'       => $websiteDialog->website_id,
            'websiteDialog' => $websiteDialog->id
        ];

        return Inertia::render(
            'Websites/WebsiteDialogWorkshop',
            [
                'title'               => __("Dialog's workshop"),
                'breadcrumbs' => ShowWebsiteDialog::make()->getBreadcrumbs(
                    $websiteDialog,
                    $request->route()->originalParameters(),
                    '('.__('Workshop').')'
                ),
                'pageHead'            => [
                    'title'       => __('Workshop'),
                    'container'   => [
                        'icon'    => ['fal', 'fa-window-restore'],
                        'tooltip' => __('Dialog'),
                        'label'   => Str::possessive($websiteDialog->name)
                    ],
                    'icon'        => [
                        'icon'  => ['fal', 'drafting-compass'],
                        'title' => __("Dialog's workshop")
                    ],
                    'iconRight'   => $websiteDialog->status->statusIcon()[$websiteDialog->status->value],
                    'actions'     => [
                        [
                            'type'  => 'button',
                            'style' => 'exit',
                            'label' => __('Exit workshop'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.web.website_dialogs.show',
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ],
                    ],
                ],
                'website_dialog'      => WebsiteDialogResource::make($websiteDialog)->getArray(),
                'website'             => [
                    'name' => $websiteDialog->website->name,
                    'url'  => $websiteDialog->website->getUrl(),
                ],
                'triggers'            => collect(WebsiteDialogTriggerEnum::labels())
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values()
                    ->all(),
                'display_frequencies' => collect(WebsiteDialogDisplayFrequencyEnum::labels())
                    ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
                    ->values()
                    ->all(),
                'routes_list'         => [
                    'update_route'                 => [
                        'name'       => 'grp.models.shop.website.website_dialog.update',
                        'parameters' => $modelRouteParameters,
                        'method'     => 'patch'
                    ],
                    'publish_route'                => [
                        'name'       => 'grp.models.shop.website.website_dialog.publish',
                        'parameters' => $modelRouteParameters,
                        'method'     => 'patch'
                    ],
                    'reset_route'                  => [
                        'name'       => 'grp.models.shop.website.website_dialog.reset',
                        'parameters' => $modelRouteParameters,
                        'method'     => 'patch'
                    ],
                    'toggle_route'                 => [
                        'name'       => 'grp.models.shop.website.website_dialog.toggle',
                        'parameters' => $modelRouteParameters,
                        'method'     => 'patch'
                    ],
                    'upload_image_route'           => [
                        'name'       => 'grp.models.shop.website.website_dialog.upload-images.store',
                        'parameters' => $modelRouteParameters,
                        'method'     => 'post'
                    ],
                    'fetch_clashing_dialogs_route' => [
                        'name'       => 'grp.json.website_dialogs.clashing',
                        'parameters' => [
                            'website'       => $websiteDialog->website_id,
                            'websiteDialog' => $websiteDialog->id,
                        ],
                    ],
                ],
            ]
        );
    }
}
