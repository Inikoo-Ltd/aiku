<?php

/*
 * Author Louis Perez
 * Created on 19-11-2026-10h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\UI\Grp\BreakUserUiProps;
use App\Http\Middleware\SetMobileApiLocale;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileProfileLanguage
{
    use AsController;

    public function handle(User $user, string $code): User
    {
        $language = Language::where('code', $code)->firstOrFail();
        $user->update(['language_id' => $language->id]);
        BreakUserUiProps::run($user);

        return $user;
    }

    public function rules(): array
    {
        return [
            'language' => ['required', 'string', Rule::in(SetMobileApiLocale::supportedLocales()), Rule::exists('languages', 'code')],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user = $this->handle($user, $request->validated('language'));

        return response()->json(['data' => app(GetMobileProfile::class)->handle($user->refresh())]);
    }
}
