<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\PostRoom\UI;

use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\OrgAction;
use App\Enums\Comms\PostRoom\PostRoomsTabsEnum;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Http\Resources\Mail\MailshotResource;
use App\Http\Resources\Mail\PostRoomResource;
use App\Http\Resources\Mail\OutboxesResource;
use App\Models\Comms\PostRoom;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property PostRoom $postRoom
 */
class ShowPostRoom extends OrgAction
{
    public function handle(PostRoom $postRoom): PostRoom
    {
        return $postRoom;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("group-overview");
    }


    public function asController(PostRoom $postRoom, ActionRequest $request): PostRoom
    {
        $this->initialisationFromGroup($postRoom->group, $request)->withTab(PostRoomsTabsEnum::values());

        return $this->handle($postRoom);
    }



    public function htmlResponse(PostRoom $postRoom, ActionRequest $request): Response
    {

        return Inertia::render(
            'Comms/PostRoom',
            [
                'title'       => __('post room'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'  => 'fal fa-cash-register',
                    'title' => $this->postRoom->code,
                    'meta'  => [
                        [
                            'name'     => trans_choice('outbox | outboxes', $this->postRoom->stats->id),
                            'number'   => $this->postRoom->stats->id,
                            'route'     => [
                                'mail.post_rooms.show.outboxes.index',
                                $this->postRoom->id
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-money-check-alt',
                                'tooltip' => __('outboxes')
                            ]
                        ],
                        [
                            'name'     => trans_choice('mailshot | mailshots', $this->postRoom->stats->number_mailshots),
                            'number'   => $this->postRoom->stats->number_mailshots,
                            'route'     => [
                                'mail.post_rooms.show.mailshots.index',
                                $this->postRoom->id
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-credit-card',
                                'tooltip' => __('mailshots')
                            ]
                        ],
                        [
                            'name'     => trans_choice('dispatched email | dispatched emails', $this->postRoom->stats->number_dispatched_emails),
                            'number'   => $this->postRoom->stats->number_dispatched_emails,
                            'route'     => [
                                'mail.post_rooms.show.dispatched-emails.index',
                                $this->postRoom->id
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-credit-card',
                                'tooltip' => __('dispatched emails')
                            ]
                        ]

                    ]

                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => PostRoomsTabsEnum::navigation(),
                ],
                PostRoomsTabsEnum::SHOWCASE->value => $this->tab == PostRoomsTabsEnum::SHOWCASE->value ?
                    fn () => GetPostRoomShowcase::run($postRoom)
                    : Inertia::lazy(fn () => GetPostRoomShowcase::run($postRoom)),

                PostRoomsTabsEnum::OUTBOXES->value => $this->tab == PostRoomsTabsEnum::OUTBOXES->value ?
                    fn () => OutboxesResource::collection(IndexOutboxes::run($postRoom, PostRoomsTabsEnum::OUTBOXES->value))
                    : Inertia::lazy(fn () => OutboxesResource::collection(IndexOutboxes::run($postRoom, PostRoomsTabsEnum::OUTBOXES->value))),

                PostRoomsTabsEnum::MAILSHOTS->value => $this->tab == PostRoomsTabsEnum::MAILSHOTS->value ?
                    fn () => MailshotResource::collection(IndexMailshots::run($postRoom, PostRoomsTabsEnum::MAILSHOTS->value))
                    : Inertia::lazy(fn () => MailshotResource::collection(IndexMailshots::run($postRoom, PostRoomsTabsEnum::MAILSHOTS->value))),

                PostRoomsTabsEnum::DISPATCHED_EMAILS->value => $this->tab == PostRoomsTabsEnum::DISPATCHED_EMAILS->value ?
                    fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($postRoom, PostRoomsTabsEnum::DISPATCHED_EMAILS->value))
                    : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($postRoom, PostRoomsTabsEnum::DISPATCHED_EMAILS->value))),

                'data'   => PostRoomResource::make($postRoom)
            ]
        )->table(IndexOutboxes::make()->tableStructure(parent:$postRoom, prefix: PostRoomsTabsEnum::OUTBOXES->value))
         ->table(IndexMailshots::make()->tableStructure(parent:$postRoom, prefix: PostRoomsTabsEnum::MAILSHOTS->value))
         ->table(IndexDispatchedEmails::make()->tableStructure(parent:$postRoom, prefix: PostRoomsTabsEnum::DISPATCHED_EMAILS->value));
    }


    public function jsonResponse(): PostRoomResource
    {
        return new PostRoomResource($this->postRoom);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (PostRoom $postRoom, array $routeParameters, $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => $postRoom->name,
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        $postRoom = PostRoom::where('slug', $routeParameters['postRoom'])->first();

        return match ($routeName) {
            'grp.overview.comms-marketing.post-rooms.show' =>
            array_merge(
                IndexPostRooms::make()->getBreadcrumbs('grp.overview.comms-marketing.post-rooms.index', $routeParameters),
                $headCrumb(
                    $postRoom,
                    [

                        'name'       => 'grp.overview.comms-marketing.post-rooms.show',
                        'parameters' => $routeParameters

                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
