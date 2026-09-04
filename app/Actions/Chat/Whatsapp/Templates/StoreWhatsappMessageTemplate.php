<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\WhatsappMediaTypeEnum;
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
            'source_template_id'    => ['sometimes', 'nullable', 'integer', 'exists:meta_message_templates,id'],
            'category'              => ['required', Rule::in(self::CATEGORIES)],
            'language'              => ['required', 'string', 'max:10'],

            'header_format'         => ['sometimes', Rule::in(self::HEADER_FORMATS)],
            'header_text'           => ['required_if:header_format,TEXT', 'nullable', 'string', 'max:60'],
            'header_example'        => ['nullable', 'string', 'max:60'],
            'header_media'          => [
                'required_if:header_format,IMAGE,VIDEO,DOCUMENT',
                'nullable',
                $this->headerMediaRule(),
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
    protected function headerMediaRule(): File
    {
        $type = WhatsappMediaTypeEnum::fromHeaderFormat((string) request('header_format'));

        if (!$type) {
            return File::default()->max(WhatsappMediaTypeEnum::IMAGE->maxKilobytes());
        }

        return File::types($type->extensions())->max($type->maxKilobytes());
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
    public function handle(Shop $shop, array $modelData, ?MetaMessageTemplate $draft = null): array
    {
        $wabaId = (string) Arr::get($shop->settings, 'whatsapp.waba_id');

        ['access_token' => $accessToken] = $this->whatsappCredentials($shop);

        if ($wabaId === '' || $accessToken === '') {
            return ['ok' => false, 'message' => __('Set the WhatsApp WABA ID and access token before creating templates.')];
        }

        // A new language of an existing template reuses its sample file: Meta wants a fresh
        // handle per template, but there is no reason to make someone upload the same
        // picture again for every language.
        $source = filled(Arr::get($modelData, 'source_template_id'))
            ? MetaMessageTemplate::find($modelData['source_template_id'])
            : null;

        if ($source?->headerMedia && !(($modelData['header_media'] ?? null) instanceof UploadedFile)) {
            $modelData['header_handle'] = UploadWhatsappTemplateMedia::make()->fromPath(
                $source->headerMedia->getPath(),
                (string) $source->headerMedia->mime_type,
                $accessToken,
                $shop->organisation
            );

            if (!$modelData['header_handle']) {
                return ['ok' => false, 'message' => __('The sample file could not be uploaded to Meta.')];
            }
        }

        $components = $this->buildComponents($modelData, $accessToken, $shop->organisation);

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

        $template = $this->storeLocally($shop, $modelData, $components['components'], $response->json(), $draft);

        // Meta keeps only an upload handle for the review sample, and that handle cannot be
        // sent to a customer, so the file is kept here to be re-uploaded on every send.
        if (($modelData['header_media'] ?? null) instanceof UploadedFile) {
            $this->storeHeaderMedia($template, $modelData['header_media']);
        } elseif ($source?->header_media_id) {
            $template->update(['header_media_id' => $source->header_media_id]);
        }

        return ['ok' => true, 'template' => $template];
    }

    /**
     * @return array{components?: array<int, array<string, mixed>>, error?: string}
     */
    protected function buildComponents(array $modelData, ?string $accessToken, ?Organisation $organisation = null): array
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

            // A draft never reaches a reviewer, so it records the format and waits for the
            // sample file until the moment it is actually submitted.
            if ($accessToken === null) {
                $components[] = ['type' => 'HEADER', 'format' => $format];
            } else {
                // A submitted draft has already turned its stored file into a handle; a
                // fresh submission still carries the upload itself.
                $handle = Arr::get($modelData, 'header_handle');

                if (!$handle) {
                    if (!$media instanceof UploadedFile) {
                        return ['error' => __('Upload the sample file Meta will review.')];
                    }

                    $handle = UploadWhatsappTemplateMedia::run($media, $accessToken, $organisation);
                }

                if (!$handle) {
                    return ['error' => __('The sample file could not be uploaded to Meta.')];
                }

                $components[] = [
                    'type'    => 'HEADER',
                    'format'  => $format,
                    'example' => ['header_handle' => [$handle]],
                ];
            }
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

    protected function storeHeaderMedia(MetaMessageTemplate $template, UploadedFile $file): void
    {
        $media = StoreMediaFromFile::run(
            $template,
            [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'checksum'     => md5_file($file->getPathName()),
            ],
            'template_header',
            str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file'
        );

        $template->update(['header_media_id' => $media->id]);
    }

    protected function storeLocally(Shop $shop, array $modelData, array $components, array $response, ?MetaMessageTemplate $draft = null): MetaMessageTemplate
    {
        $metaChannel = MetaChannel::where('code', 'whatsapp')->firstOrFail();

        $attributes = [
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
        ];

        // A submitted draft keeps its own row: its header file and any label live there,
        // and a second row would leave the draft behind as a duplicate.
        if ($draft) {
            $draft->update(array_merge($attributes, ['template_id' => (string) Arr::get($response, 'id')]));

            return $draft->fresh();
        }

        return MetaMessageTemplate::updateOrCreate(
            ['template_id' => (string) Arr::get($response, 'id')],
            $attributes
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
