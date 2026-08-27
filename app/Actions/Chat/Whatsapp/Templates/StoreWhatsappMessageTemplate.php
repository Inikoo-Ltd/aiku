<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\WhatsappTemplateTagEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

/**
 * Submits a template to Meta for review. Meta owns the approval, so the local row is
 * written with whatever status the API returns (normally PENDING) and later corrected
 * by the `message_template_status_update` webhook.
 */
class StoreWhatsappMessageTemplate extends OrgAction
{
    use WithWhatsappCredentials;

    public const CATEGORIES = ['MARKETING', 'UTILITY'];

    public const HEADER_FORMATS = ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'];

    public const BUTTON_TYPES = ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'];

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'category'              => ['required', Rule::in(self::CATEGORIES)],
            'language'              => ['required', 'string', 'max:10'],

            'header_format'         => ['sometimes', Rule::in(self::HEADER_FORMATS)],
            'header_text'           => ['required_if:header_format,TEXT', 'nullable', 'string', 'max:60'],
            'header_example'        => ['nullable', 'string', 'max:60'],
            'header_media'          => [
                'required_if:header_format,IMAGE,VIDEO,DOCUMENT',
                'nullable',
                File::default()->max(16 * 1024),
            ],

            'body'                  => ['required', 'string', 'max:1024'],
            'body_examples'         => ['sometimes', 'array', 'max:10'],
            'body_examples.*'       => ['required', 'string', 'max:200'],

            'footer'                => ['nullable', 'string', 'max:60'],

            'buttons'               => ['sometimes', 'array', 'max:10'],
            'buttons.*.type'        => ['required', Rule::in(self::BUTTON_TYPES)],
            'buttons.*.text'        => ['required', 'string', 'max:25'],
            'buttons.*.url'         => ['required_if:buttons.*.type,URL', 'nullable', 'string', 'max:2000'],
            'buttons.*.url_example' => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['required_if:buttons.*.type,PHONE_NUMBER', 'nullable', 'string', 'max:20'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $data = $validator->getData();

        $this->assertTagsAreKnown($validator, 'body', Arr::get($data, 'body', ''));
        $this->assertTagsAreKnown($validator, 'header_text', Arr::get($data, 'header_text', ''));
        $this->assertTagsAreKnown($validator, 'footer', Arr::get($data, 'footer', ''));

        if (count($this->tagsIn(Arr::get($data, 'header_text', ''))) > 1) {
            $validator->errors()->add('header_text', __('The header can hold at most one variable.'));
        }

        if ($this->tagsIn(Arr::get($data, 'footer', ''))) {
            $validator->errors()->add('footer', __('The footer cannot contain variables.'));
        }

        $buttons = collect(Arr::get($data, 'buttons', []));

        if ($buttons->where('type', 'URL')->count() > 2) {
            $validator->errors()->add('buttons', __('WhatsApp allows at most two link buttons.'));
        }

        if ($buttons->where('type', 'PHONE_NUMBER')->count() > 1) {
            $validator->errors()->add('buttons', __('WhatsApp allows at most one call button.'));
        }

        $buttons->each(function (array $button, int $index) use ($validator) {
            if (Arr::get($button, 'type') !== 'URL') {
                return;
            }

            $url        = (string) Arr::get($button, 'url');
            $variables  = $this->placeholders($url);

            if (!$variables) {
                return;
            }

            // A dynamic link may carry exactly one variable and it has to close the URL:
            // Meta appends the value, it does not substitute in the middle.
            if ($variables !== [1] || !str_ends_with(trim($url), '{{1}}')) {
                $validator->errors()->add(
                    "buttons.$index.url",
                    __('A dynamic link takes a single {{1}} at the very end of the URL.')
                );
            }

            if (blank(Arr::get($button, 'url_example'))) {
                $validator->errors()->add(
                    "buttons.$index.url_example",
                    __('Give a full sample link, Meta reviews the destination.')
                );
            }
        });
    }

    /**
     * @return array<int, int>
     */
    protected function placeholders(?string $text): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', (string) $text, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * Every `[Tag]` in the text, in the order it appears.
     *
     * @return array<int, string>
     */
    protected function tagsIn(?string $text): array
    {
        preg_match_all(WhatsappTemplateTagEnum::tokenPattern(), (string) $text, $matches);

        return $matches[1] ?? [];
    }

    /**
     * A bracketed word that is not a known tag is almost always a typo, and Meta would
     * happily approve it as literal text that never gets replaced.
     */
    protected function assertTagsAreKnown(Validator $validator, string $field, ?string $text): void
    {
        foreach ($this->tagsIn($text) as $tag) {
            if (!WhatsappTemplateTagEnum::tryFrom($tag)) {
                $validator->errors()->add($field, __(':tag is not a known variable.', ['tag' => '['.$tag.']']));
            }
        }
    }

    /**
     * Turns the editable `[Tag]` text into what WhatsApp expects. Each distinct tag keeps
     * one position, so the same tag used twice reuses its own `{{n}}` rather than eating
     * a second slot, and the returned order is what send time fills in.
     *
     * @return array{text: string, tags: array<int, string>, examples: array<int, string>}
     */
    protected function compileTags(?string $text, array $tags = []): array
    {
        $text = (string) $text;

        foreach ($this->tagsIn($text) as $tag) {
            if (!WhatsappTemplateTagEnum::tryFrom($tag)) {
                continue;
            }

            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }

            $position = array_search($tag, $tags, true) + 1;
            $text     = str_replace('['.$tag.']', '{{'.$position.'}}', $text);
        }

        return [
            'text'     => $text,
            'tags'     => $tags,
            'examples' => array_map(
                fn (string $tag) => WhatsappTemplateTagEnum::from($tag)->example(),
                $tags
            ),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, template?: MetaMessageTemplate}
     */
    public function handle(Shop $shop, array $modelData): array
    {
        $wabaId = (string) Arr::get($shop->settings, 'whatsapp.waba_id');

        ['access_token' => $accessToken] = $this->whatsappCredentials($shop);

        if ($wabaId === '' || $accessToken === '') {
            return ['ok' => false, 'message' => __('Set the WhatsApp WABA ID and access token before creating templates.')];
        }

        $components = $this->buildComponents($modelData, $accessToken);

        if (isset($components['error'])) {
            return ['ok' => false, 'message' => $components['error']];
        }

        $modelData['merge_tags'] = $components['tags'];

        $response = Http::withToken($accessToken)->post($this->whatsappEndpoint($wabaId.'/message_templates'), [
            'name'       => $modelData['name'],
            'language'   => $modelData['language'],
            'category'   => $modelData['category'],
            'components' => $components['components'],
        ]);

        if ($response->failed()) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.error_user_msg')
                    ?: Arr::get($response->json(), 'error.message')
                    ?: __('Meta rejected the template.'),
            ];
        }

        return ['ok' => true, 'template' => $this->storeLocally($shop, $modelData, $components['components'], $response->json())];
    }

    /**
     * @return array{components?: array<int, array<string, mixed>>, error?: string}
     */
    protected function buildComponents(array $modelData, string $accessToken): array
    {
        $components = [];
        $format     = Arr::get($modelData, 'header_format', 'NONE');

        // The header shares the numbering with the body, so it is compiled first and its
        // tags seed the list the body continues from.
        $compiledHeader = $this->compileTags(Arr::get($modelData, 'header_text'));
        $tags           = $compiledHeader['tags'];

        if ($format === 'TEXT' && filled(Arr::get($modelData, 'header_text'))) {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $compiledHeader['text']];

            if ($compiledHeader['examples']) {
                $header['example'] = ['header_text' => $compiledHeader['examples']];
            }

            $components[] = $header;
        } else {
            $tags = [];
        }

        if (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $media = Arr::get($modelData, 'header_media');

            if (!$media instanceof UploadedFile) {
                return ['error' => __('Upload the sample file Meta will review.')];
            }

            $handle = UploadWhatsappTemplateMedia::run($media, $accessToken);

            if (!$handle) {
                return ['error' => __('The sample file could not be uploaded to Meta.')];
            }

            $components[] = [
                'type'    => 'HEADER',
                'format'  => $format,
                'example' => ['header_handle' => [$handle]],
            ];
        }

        $compiledBody = $this->compileTags(Arr::get($modelData, 'body'), $tags);
        $bodyTags     = array_slice($compiledBody['tags'], count($tags));

        $body = ['type' => 'BODY', 'text' => $compiledBody['text']];

        // Meta numbers the body's own variables from 1, independently of the header.
        $bodySamples = $bodyTags
            ? array_map(fn (string $tag) => WhatsappTemplateTagEnum::from($tag)->example(), $bodyTags)
            : array_values(Arr::get($modelData, 'body_examples', []));

        if ($bodySamples) {
            $body['example'] = ['body_text' => [$bodySamples]];
        }

        if ($bodyTags) {
            $body['text'] = $this->compileTags(Arr::get($modelData, 'body'))['text'];
        }

        $components[] = $body;

        if (filled(Arr::get($modelData, 'footer'))) {
            $components[] = ['type' => 'FOOTER', 'text' => $modelData['footer']];
        }

        $buttons = collect(Arr::get($modelData, 'buttons', []))
            ->map(fn (array $button) => match ($button['type']) {
                'URL'          => $this->urlButton($button),
                'PHONE_NUMBER' => ['type' => 'PHONE_NUMBER', 'text' => $button['text'], 'phone_number' => $button['phone_number']],
                default        => ['type' => 'QUICK_REPLY', 'text' => $button['text']],
            })
            ->values()
            ->all();

        if ($buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return [
            'components' => $components,
            'tags'       => [
                'header' => $compiledHeader['tags'],
                'body'   => $bodyTags,
            ],
        ];
    }

    /**
     * A link whose URL ends in a variable is a dynamic link, and Meta wants a complete
     * sample so the reviewer can see where it actually leads.
     *
     * @param  array<string, mixed>  $button
     * @return array<string, mixed>
     */
    protected function urlButton(array $button): array
    {
        $url = (string) Arr::get($button, 'url');

        $urlButton = ['type' => 'URL', 'text' => $button['text'], 'url' => $url];

        if ($this->placeholders($url) && filled(Arr::get($button, 'url_example'))) {
            $urlButton['example'] = [$button['url_example']];
        }

        return $urlButton;
    }

    protected function storeLocally(Shop $shop, array $modelData, array $components, array $response): MetaMessageTemplate
    {
        $metaChannel = MetaChannel::where('code', 'whatsapp')->firstOrFail();

        return MetaMessageTemplate::updateOrCreate(
            ['template_id' => (string) Arr::get($response, 'id')],
            [
                'group_id'        => $shop->group_id,
                'organisation_id' => $shop->organisation_id,
                'shop_id'         => $shop->id,
                'meta_channel_id' => $metaChannel->id,
                'name'            => $modelData['name'],
                'language'        => $modelData['language'],
                'category'        => Arr::get($response, 'category', $modelData['category']),
                'status'          => Arr::get($response, 'status', 'PENDING'),
                'data'            => [
                    'merge_tags' => Arr::get($modelData, 'merge_tags', []),
                    'id'         => Arr::get($response, 'id'),
                    'name'       => $modelData['name'],
                    'language'   => $modelData['language'],
                    'category'   => Arr::get($response, 'category', $modelData['category']),
                    'status'     => Arr::get($response, 'status', 'PENDING'),
                    'components' => $components,
                ],
                'synchronize_at'  => now(),
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $result = $this->handle($shop, $this->validatedData);

        if (!$result['ok']) {
            return Redirect::back()->withInput()->with('notification', [
                'status'      => 'error',
                'title'       => __('Template not created'),
                'description' => $result['message'],
            ]);
        }

        return Redirect::route('grp.org.shops.show.chat.whatsapp_templates.index', [
            'organisation' => $organisation->slug,
            'shop'         => $shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Template submitted'),
            'description' => __('Meta is reviewing :name. Approval usually takes a few minutes to a few hours.', [
                'name' => $result['template']->name,
            ]),
        ]);
    }
}
