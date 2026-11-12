<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Group;
use App\Models\Web\StaticWebsite;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreStaticWebsite extends OrgAction
{
    public string $commandSignature = 'static_website:create
        {domain : Domain the website is served on, e.g. aw-freedom.com}
        {name : Name shown in Aiku}
        {--source= : Address the content is frozen from, e.g. https://www.aw-freedom.com}';

    public function handle(Group $group, array $modelData): StaticWebsite
    {
        /** @var StaticWebsite $staticWebsite */
        $staticWebsite = $group->staticWebsites()->create($modelData);

        return $staticWebsite;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('group-webmaster.edit');
    }

    public function prepareForValidation(): void
    {
        if ($this->has('domain')) {
            $this->set('domain', self::normaliseDomain((string) $this->get('domain')));
        }
    }

    public function rules(): array
    {
        return [
            'domain'     => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', Rule::unique('static_websites', 'domain')],
            'name'       => ['required', 'string', 'max:255'],
            'source_url' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (Website::where('domain', $this->get('domain'))->exists()) {
            $validator->errors()->add('domain', __('This domain already belongs to a shop website'));
        }
    }

    public static function normaliseDomain(string $domain): string
    {
        return Str::of($domain)->lower()->trim()->after('://')->before('/')->replaceStart('www.', '')->toString();
    }

    public function action(Group $group, array $modelData): StaticWebsite
    {
        $this->asAction = true;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    public function asCommand(Command $command): int
    {
        try {
            $staticWebsite = $this->action(group(), [
                'domain'     => $command->argument('domain'),
                'name'       => $command->argument('name'),
                'source_url' => $command->option('source'),
            ]);
        } catch (ValidationException $e) {
            $command->error(collect($e->errors())->flatten()->join(' '));

            return 1;
        }

        $command->info("Created $staticWebsite->name ($staticWebsite->domain)");

        return 0;
    }
}
