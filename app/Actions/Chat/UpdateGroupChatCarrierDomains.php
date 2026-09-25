<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The email domains of the couriers. A conversation from one of them, or from any subdomain, is
 * filed in the Couriers folder of the inbox. Couriers serve every shop, so the list is the group's.
 */
class UpdateGroupChatCarrierDomains extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userSupervisesChatOnOrganisation($request->user(), $this->organisation);
    }

    public function rules(): array
    {
        return [
            'domains' => ['present', 'nullable', 'string', 'max:10000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $invalid = self::parse((string) $this->get('domains'))['invalid'];

        if ($invalid) {
            $validator->errors()->add('domains', __('Not an email domain: :domains', ['domains' => implode(', ', $invalid)]));
        }
    }

    /**
     * One domain per line. Pasted addresses and links are cut down to their domain, so
     * "ops@gls-spain.es" and "https://www.dsv.com/contact" are read as gls-spain.es and dsv.com.
     *
     * @return array{domains: array<int, string>, invalid: array<int, string>}
     */
    public static function parse(string $text): array
    {
        $domains = [];
        $invalid = [];

        foreach (preg_split('/[\r\n,;\s]+/', mb_strtolower($text)) as $line) {
            $domain = preg_replace(['#^[a-z]+://#', '#^[^@]*@#', '#^www\.#', '#[/?\#:].*$#'], '', trim($line));
            $domain = trim($domain, '.');

            if ($domain === '') {
                continue;
            }

            if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
                $domains[] = $domain;
            } else {
                $invalid[] = trim($line);
            }
        }

        $domains = array_values(array_unique($domains));
        sort($domains);

        return ['domains' => $domains, 'invalid' => $invalid];
    }

    public function handle(Group $group, array $modelData): Group
    {
        $settings = $group->settings ?? [];
        data_set($settings, 'chat.carrier_domains', self::parse((string) ($modelData['domains'] ?? ''))['domains']);
        $group->update(['settings' => $settings]);

        return $group;
    }

    public function asController(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        $this->handle($organisation->group, $this->validatedData);

        return back()->with('notification', [
            'status' => 'success',
            'title'  => __('Courier domains saved'),
        ]);
    }
}
