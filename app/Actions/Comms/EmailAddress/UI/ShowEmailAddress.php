<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Comms\EmailAddress\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Mail\EmailAddressResource;
use App\Models\Comms\EmailAddress;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowEmailAddress extends OrgAction
{
    public function handle(EmailAddress $emailAddress): EmailAddress
    {
        return $emailAddress;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("group-overview");
    }

    public function asController(EmailAddress $emailAddress, ActionRequest $request): EmailAddress
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($emailAddress);
    }

    public function htmlResponse(EmailAddress $emailAddress, ActionRequest $request): Response
    {
        $title = __('Email Address');
        $icon  = [
            'icon'  => ['fal', 'fa-envelope'],
            'title' => __('Email Addresses')
        ];

        return Inertia::render(
            'Comms/EmailAddress',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $emailAddress,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('email address'),
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => $icon,
                ],

                'data' => EmailAddressResource::make($emailAddress)
            ]
        );
    }


    public function getBreadcrumbs(EmailAddress $emailAddress, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) use ($emailAddress) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $emailAddress->email,
                    ],
                ],
            ];
        };

        return array_merge(
            IndexEmailAddress::make()->getBreadcrumbs(
                'grp.overview.comms-marketing.email-addresses.index',
                []
            ),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}
