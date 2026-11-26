<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * The second, separate yes (INI-429): asks Slack for the private scopes on top of the public ones, never inside the public connection.
 */
class StartSlackPrivateConnection
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return SlackWriteAccess::allows($request->user()) && SlackWriteAccess::privateOptInOpen();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'understood'      => ['required', 'accepted'],
            'consent_version' => ['required', 'string', Rule::in([SlackWriteAccess::PRIVATE_CONSENT_VERSION])],
            'terms_version'   => ['required', 'string', Rule::in([SlackWriteAccess::PRIVATE_TERMS_VERSION])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return [
            'understood.accepted' => __('Tick "I understand" first'),
            'consent_version.in'  => __('The texts have changed since you opened them. Reload the page and read them again'),
            'terms_version.in'    => __('The texts have changed since you opened them. Reload the page and read them again'),
        ];
    }

    public function asController(ActionRequest $request): Response
    {
        if ($reason = StartSlackConnection::unavailableReason($request->user())) {
            return StartSlackConnection::backToSettings(StartSlackConnection::ERROR_KEY, $reason);
        }

        return Inertia::location(StartSlackConnection::authorizeUrl(
            $request->user(),
            [...SlackWriteAccess::USER_SCOPES, ...SlackWriteAccess::PRIVATE_SCOPES],
            ['consent_version' => SlackWriteAccess::PRIVATE_CONSENT_VERSION, 'terms_version' => SlackWriteAccess::PRIVATE_TERMS_VERSION]
        ));
    }
}
