<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\UI\WithInertia;
use App\Http\Resources\UI\LoggedUserResource;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EditProfile
{
    use AsAction;
    use WithInertia;

    public function asController(ActionRequest $request): User
    {
        return $request->user();
    }

    public function jsonResponse(User $user): array
    {
        return $this->generateBlueprint($user);
    }

    public function generateBlueprint(User $user): array
    {
        return [
            'title' => __('Edit Profile'),
            'pageHead' => [
                'title' => __('Edit Profile'),

            ],
            'formData' => [
                'blueprint' => [
                    [
                        'label' => __('profile'),
                        'icon' => 'fa-light fa-user-circle',
                        'current' => true,
                        'fields' => [
                            'email' => [
                                'type' => 'input',
                                'label' => __('email'),
                                'value' => $user->email,
                            ],
                            'password' => [
                                'type' => 'password',
                                'label' => __('password'),
                                'value' => '',
                            ],
                            'about' => [
                                'type' => 'textarea',
                                'label' => __('about'),
                                'value' => $user->about,
                                'maxLength' => 48,
                                'counter' => true,
                                'rows' => 5,
                                'placeholder' => __('Enter up to 50 characters'),
                            ],
                            'image' => [
                                'type' => 'image_crop_square',
                                'label' => __('Logo'),
                                'value' => $user->imageSources(320, 320),
                            ],
                        ],
                    ],

                ],
                'args' => [
                    'updateRoute' => [
                        'name' => 'grp.models.profile.update',
                    ],
                ],
            ],
            'auth' => [
                'user' => LoggedUserResource::make($user)->getArray(),
            ],
        ];
    }

    public function htmlResponse(User $user): Response
    {

        return Inertia::render('EditModel', $this->generateBlueprint($user));
    }
}
