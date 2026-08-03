<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Jira;

use App\Actions\Chat\Jira\Concerns\WithChatJiraContext;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateChatAgentJiraSettings
{
    use AsAction;
    use WithChatJiraContext;

    /**
     * @param  array<string, mixed>  $modelData
     *
     * @return array{base_url: string, email: string, has_token: bool, configured: bool}
     */
    public function handle(User $user, array $modelData): array
    {
        $settings = $user->settings ?? [];
        $existingToken = Arr::get($settings, 'jira.api_token');

        $token = filled(Arr::get($modelData, 'api_token'))
            ? Arr::get($modelData, 'api_token')
            : $existingToken;

        $jira = [
            'base_url'  => rtrim((string) Arr::get($modelData, 'base_url'), '/'),
            'email'     => Arr::get($modelData, 'email'),
            'api_token' => $token,
        ];

        data_set($settings, 'jira', $jira);

        $user->settings = $settings;
        $user->save();

        return [
            'base_url'   => $jira['base_url'],
            'email'      => $jira['email'],
            'has_token'  => filled($token),
            'configured' => $this->jiraCredentialsConfigured($jira),
        ];
    }

    public function rules(): array
    {
        return [
            'base_url'  => ['required', 'url', 'max:2048'],
            'email'     => ['required', 'email', 'max:255'],
            'api_token' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $validated = $request->validate($this->rules());

        if (blank(Arr::get($validated, 'api_token')) && blank(Arr::get($user->settings, 'jira.api_token'))) {
            return response()->json([
                'success' => false,
                'message' => 'Jira API token is required',
            ], 422);
        }

        $data = $this->handle($user, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Jira settings saved',
            'data'    => $data,
        ]);
    }
}
