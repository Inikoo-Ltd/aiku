<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 08 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotUtmParameterEnum;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotUrlUtm extends OrgAction
{
    use WithActionUpdate;

    private Mailshot $mailshot;

    public function handle(Mailshot $mailshot, array $modelData): Mailshot
    {
        $url    = Arr::get($modelData, 'url');
        $newUrl = trim((string) Arr::get($modelData, 'new_url', ''));
        $utm    = array_filter(Arr::only($modelData, MailshotUtmParameterEnum::values()), fn ($value) => filled($value));

        $utmLinks = collect(Arr::get($mailshot->data, 'utm_links', []))
            ->reject(fn (array $link) => in_array(Arr::get($link, 'url'), [$url, $newUrl], true))
            ->values();

        $data = ['utm_links' => []];
        if ($newUrl !== '' && $newUrl !== $url) {
            $replacedLinks = $this->rememberReplacement($this->replacedLinks($mailshot), $url, $newUrl);
            $data['replaced_links'] = array_map(fn (string $from) => ['from' => $from, 'to' => $replacedLinks[$from]], array_keys($replacedLinks));
            $this->replaceUrlsInEmail($mailshot, $replacedLinks);
            $url = $newUrl;
        }

        if ($utm !== []) {
            $utmLinks->push([
                'url' => $url,
                'utm' => $utm,
            ]);
        }

        $data['utm_links'] = $utmLinks->all();

        return $this->update($mailshot, ['data' => $data], ['data']);
    }

    /**
     * Every address change of the email, old => new. The editor keeps its own copy of the layout and
     * autosaves it, so a save already on its way could bring an old address back; the workshop save
     * and publish run every layout through this list, which makes a change stick whatever arrives.
     *
     * @param array<string, string> $replacedLinks
     *
     * @return array<string, string>
     */
    private function rememberReplacement(array $replacedLinks, string $url, string $newUrl): array
    {
        unset($replacedLinks[$newUrl]);
        $replacedLinks = array_map(fn (string $target) => $target === $url ? $newUrl : $target, $replacedLinks);
        $replacedLinks[$url] = $newUrl;

        return $replacedLinks;
    }

    /**
     * Kept as a list of pairs: as map keys the dots of each address would be read as nesting.
     *
     * @return array<string, string>
     */
    private function replacedLinks(Mailshot $mailshot): array
    {
        return collect(Arr::get($mailshot->data, 'replaced_links', []))->pluck('to', 'from')->all();
    }

    /**
     * @param array<string, string> $replacedLinks
     */
    private function replaceUrlsInEmail(Mailshot $mailshot, array $replacedLinks): void
    {
        $email = $mailshot->email;

        foreach (array_filter([$email?->unpublishedSnapshot, $email?->liveSnapshot]) as $snapshot) {
            $snapshot->update(array_filter([
                'layout'          => $this->replaceUrlsInLayout($snapshot->layout ?? [], $replacedLinks),
                'compiled_layout' => is_string($snapshot->compiled_layout) ? $this->replaceUrlsInHtml($snapshot->compiled_layout, $replacedLinks) : null,
            ], fn ($value) => $value !== null));
        }
    }

    /**
     * @param array<string, mixed> $modelData
     *
     * @return array<string, mixed>
     */
    public function applyReplacedLinks(Mailshot $mailshot, array $modelData): array
    {
        $replacedLinks = $this->replacedLinks($mailshot);

        if (!$replacedLinks) {
            return $modelData;
        }

        if (is_array(Arr::get($modelData, 'layout'))) {
            $modelData['layout'] = $this->replaceUrlsInLayout($modelData['layout'], $replacedLinks);
        }
        if (is_string(Arr::get($modelData, 'compiled_layout'))) {
            $modelData['compiled_layout'] = $this->replaceUrlsInHtml($modelData['compiled_layout'], $replacedLinks);
        }

        return $modelData;
    }

    /**
     * @param array<string, string> $replacedLinks
     */
    private function replaceUrlsInLayout(array $layout, array $replacedLinks): array
    {
        array_walk_recursive($layout, function (&$value, $key) use ($replacedLinks) {
            if (!is_string($value)) {
                return;
            }
            if ($key === 'href') {
                $value = $replacedLinks[trim(html_entity_decode($value))] ?? $value;

                return;
            }
            $value = $this->replaceUrlsInHtml($value, $replacedLinks);
        });

        return $layout;
    }

    /**
     * @param array<string, string> $replacedLinks
     */
    private function replaceUrlsInHtml(string $html, array $replacedLinks): string
    {
        return preg_replace_callback(
            '/href=(["\'])([^"\']+)\1/i',
            function (array $match) use ($replacedLinks) {
                $newUrl = $replacedLinks[trim(html_entity_decode($match[2]))] ?? null;

                return $newUrl === null
                    ? $match[0]
                    : 'href='.$match[1].(str_contains($match[2], '&amp;') ? htmlspecialchars($newUrl, ENT_QUOTES) : $newUrl).$match[1];
            },
            $html
        ) ?? $html;
    }

    public function afterValidator(Validator $validator): void
    {
        if (filled($this->get('new_url')) && in_array($this->mailshot->state, [MailshotStateEnum::SENDING, MailshotStateEnum::SENT, MailshotStateEnum::CANCELLED, MailshotStateEnum::STOPPED], true)) {
            $validator->errors()->add('new_url', __('This email has already gone out, so its links can no longer be changed.'));
        }
    }

    public function rules(): array
    {
        $rules = [
            'url'     => ['required', 'string', 'max:2048'],
            'new_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
        ];

        foreach (MailshotUtmParameterEnum::values() as $parameter) {
            $rules[$parameter] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function action(Mailshot $mailshot, array $modelData): Mailshot
    {
        $this->asAction = true;
        $this->mailshot = $mailshot;
        $this->initialisationFromShop($mailshot->shop, $modelData);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->mailshot = $mailshot;
        $this->initialisationFromShop($mailshot->shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }

    public function jsonResponse(Mailshot $mailshot): array
    {
        return [
            'utm_links' => Arr::get($mailshot->data, 'utm_links', []),
        ];
    }
}
