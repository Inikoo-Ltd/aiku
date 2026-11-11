<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * What changed in the content of a webpage between two of its snapshots: blocks added or removed,
 * and for every block the fields whose value changed, with the text compared word by word.
 */
class GetWebpageSnapshotsComparison extends OrgAction
{
    use WithWebAuthorisation;

    private const int OPTIONS_LIMIT = 100;

    private Webpage $webpage;

    /**
     * @return array{from: array|null, to: array, options: array, blocks: array}
     */
    public function handle(Webpage $webpage, Snapshot $to, ?Snapshot $from = null): array
    {
        $from ??= $this->previousSnapshot($webpage, $to);

        return [
            'from'    => $from ? $this->snapshotSummary($from) : null,
            'to'      => $this->snapshotSummary($to),
            'options' => $this->snapshotOptions($webpage),
            'blocks'  => $this->compareBlocks(
                $from ? Arr::get($from->layout, 'web_blocks', []) : [],
                Arr::get($to->layout, 'web_blocks', [])
            ),
        ];
    }

    public function previousSnapshot(Webpage $webpage, Snapshot $snapshot): ?Snapshot
    {
        if ($snapshot->state === SnapshotStateEnum::UNPUBLISHED) {
            return $webpage->liveSnapshot;
        }

        /** @var Snapshot|null $previous */
        $previous = $webpage->snapshots()
            ->where('state', '!=', SnapshotStateEnum::UNPUBLISHED->value)
            ->where('id', '!=', $snapshot->id)
            ->where('published_at', '<=', $snapshot->published_at)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        return $previous;
    }

    private function snapshotSummary(Snapshot $snapshot): array
    {
        return [
            'id'           => $snapshot->id,
            'state'        => $snapshot->state->value,
            'published_at' => $snapshot->published_at,
            'edited_at'    => $snapshot->state === SnapshotStateEnum::UNPUBLISHED ? $snapshot->updated_at : null,
            'publisher'    => $snapshot->publisher?->contact_name,
            'comment'      => $snapshot->first_commit ? __('First commit') : $snapshot->comment,
        ];
    }

    private function snapshotOptions(Webpage $webpage): array
    {
        $options = [];

        if ($webpage->is_dirty && $webpage->unpublishedSnapshot) {
            $options[] = [
                'id'           => $webpage->unpublishedSnapshot->id,
                'state'        => SnapshotStateEnum::UNPUBLISHED->value,
                'published_at' => null,
                'publisher'    => null,
            ];
        }

        $snapshots = $webpage->snapshots()
            ->select(['id', 'state', 'published_at', 'publisher_type', 'publisher_id'])
            ->with('publisher')
            ->where('state', '!=', SnapshotStateEnum::UNPUBLISHED->value)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::OPTIONS_LIMIT)
            ->get();

        foreach ($snapshots as $snapshot) {
            $options[] = [
                'id'           => $snapshot->id,
                'state'        => $snapshot->state->value,
                'published_at' => $snapshot->published_at,
                'publisher'    => $snapshot->publisher?->contact_name,
            ];
        }

        return $options;
    }

    private function compareBlocks(array $fromBlocks, array $toBlocks): array
    {
        $fromByKey = $this->blocksByKey($fromBlocks);
        $toByKey   = $this->blocksByKey($toBlocks);

        $changes = [];

        foreach ($toByKey as $key => $toBlock) {
            $fromBlock = $fromByKey[$key] ?? null;
            $fields    = $this->compareFields(
                $fromBlock ? $this->blockFields($fromBlock) : [],
                $this->blockFields($toBlock)
            );

            if ($fromBlock && !$fields) {
                continue;
            }

            $changes[] = [
                'key'    => $key,
                'name'   => $this->blockName($toBlock),
                'status' => $fromBlock ? 'changed' : 'added',
                'fields' => $fields,
            ];
        }

        foreach ($fromByKey as $key => $fromBlock) {
            if (isset($toByKey[$key])) {
                continue;
            }

            $changes[] = [
                'key'    => $key,
                'name'   => $this->blockName($fromBlock),
                'status' => 'removed',
                'fields' => $this->compareFields($this->blockFields($fromBlock), []),
            ];
        }

        return $changes;
    }

    private function blocksByKey(array $blocks): array
    {
        $byKey = [];
        foreach (array_values($blocks) as $index => $block) {
            $key         = (string) (Arr::get($block, 'id') ?? Arr::get($block, 'web_block.id') ?? 'position-'.$index);
            $byKey[$key] = $block;
        }

        return $byKey;
    }

    private function blockName(array $block): string
    {
        return (string) (Arr::get($block, 'name') ?: Arr::get($block, 'type') ?: __('Block'));
    }

    /**
     * @return array<string, array{kind: string, value: string}>
     */
    private function blockFields(array $block): array
    {
        $fields = [
            'visible' => ['kind' => 'value', 'value' => Arr::get($block, 'show', true) ? __('Shown') : __('Hidden')],
        ];

        $fieldValue = Arr::get($block, 'web_block.layout.data.fieldValue');
        if (!is_array($fieldValue)) {
            return $fields;
        }

        unset($fieldValue['latest_blogs']);

        if (isset($fieldValue['beefree'])) {
            $fields['content'] = ['kind' => 'html', 'value' => implode("\n", $this->beefreeLines($fieldValue['beefree']))];
            unset($fieldValue['beefree']);
        }

        $this->flattenFields($fieldValue, '', $fields);

        return $fields;
    }

    /**
     * A blog converted from a mailshot keeps the email builder design, mostly styling. Only its
     * text and images are compared, in the order they appear.
     *
     * @return array<int, string>
     */
    private function beefreeLines(mixed $value, ?string $key = null): array
    {
        if (is_array($value)) {
            $lines = [];
            foreach ($value as $childKey => $child) {
                $lines = array_merge($lines, $this->beefreeLines($child, (string) $childKey));
            }

            return $lines;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        if (in_array($key, ['html', 'text'], true)) {
            $text = $this->htmlToText($value);

            return $text === '' ? [] : [$text];
        }

        if ($key === 'src' && preg_match('/\.(png|jpe?g|gif|webp|avif|svg)(\?|$)/i', $value)) {
            return ['['.__('Image').': '.basename(parse_url($value, PHP_URL_PATH) ?: $value).']'];
        }

        return [];
    }

    private function flattenFields(mixed $value, string $path, array &$fields): void
    {
        if (is_array($value) && $this->isImageSource($value)) {
            $fields[$path] = ['kind' => 'image', 'value' => (string) $value['original']];

            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $this->flattenFields($child, $path === '' ? (string) $key : $path.'.'.$key, $fields);
            }

            return;
        }

        if ($value === null || $value === '' || $path === '' || $path === 'id' || str_ends_with($path, '.id')) {
            return;
        }

        if (is_bool($value)) {
            $fields[$path] = ['kind' => 'value', 'value' => $value ? __('Yes') : __('No')];

            return;
        }

        $text = (string) $value;
        if ($text !== strip_tags($text)) {
            $fields[$path] = ['kind' => 'html', 'value' => $this->htmlToText($text)];

            return;
        }

        $fields[$path] = ['kind' => 'text', 'value' => $text];
    }

    private function isImageSource(array $value): bool
    {
        return isset($value['original']) && is_string($value['original'])
            && array_diff(array_keys($value), ['original', 'png', 'avif', 'webp', 'jpg', 'jpeg', 'gif', 'svg']) === [];
    }

    /**
     * Readable text of a rich text field, one paragraph per line. Images and link targets are kept
     * as text so a swapped image or a changed link shows up in the comparison.
     */
    public function htmlToText(string $html): string
    {
        $html = preg_replace('/<(style|script|head)\b[^>]*>.*?<\/\1>/isu', '', $html);

        $html = preg_replace_callback(
            '/<img\b[^>]*>/iu',
            function (array $match) {
                $alt = preg_match('/\balt\s*=\s*"([^"]*)"/iu', $match[0], $altMatch) ? $altMatch[1] : '';
                $src = preg_match('/\bsrc\s*=\s*"([^"]*)"/iu', $match[0], $srcMatch) ? $srcMatch[1] : '';

                return "\n[".__('Image').': '.($alt !== '' ? $alt : basename(parse_url($src, PHP_URL_PATH) ?: $src))."]\n";
            },
            $html
        );

        $html = preg_replace_callback(
            '/<a\b[^>]*\bhref\s*=\s*"([^"]*)"[^>]*>(.*?)<\/a>/isu',
            fn (array $match) => $match[2].' ⟨'.$match[1].'⟩',
            $html
        );

        $html = preg_replace('/<br\s*\/?>|<\/(p|div|h[1-6]|li|blockquote|tr|ul|ol|table)>/iu', "\n", $html);
        $html = preg_replace('/<li\b[^>]*>/iu', '• ', $html);

        $text  = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(
            fn (string $line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line)),
            preg_split('/\R/u', $text)
        );

        return implode("\n", array_filter($lines, fn (string $line) => $line !== ''));
    }

    private function compareFields(array $fromFields, array $toFields): array
    {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($fromFields), array_keys($toFields))) as $path) {
            $before = $fromFields[$path]['value'] ?? '';
            $after  = $toFields[$path]['value'] ?? '';

            if ($before === $after) {
                continue;
            }

            $kind = $toFields[$path]['kind'] ?? $fromFields[$path]['kind'];

            $changes[] = [
                'field'  => (string) $path,
                'kind'   => $kind,
                'before' => $before,
                'after'  => $after,
                'rows'   => $kind === 'image' ? [] : DiffWebpageSnapshotText::run($before, $after),
            ];
        }

        return $changes;
    }

    public function rules(): array
    {
        $snapshotRule = Rule::exists('snapshots', 'id')
            ->where('parent_type', 'Webpage')
            ->where('parent_id', $this->webpage->id);

        return [
            'to'   => ['required', 'integer', $snapshotRule],
            'from' => ['sometimes', 'nullable', 'integer', $snapshotRule],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($webpage->shop, $request);

        /** @var Snapshot $to */
        $to   = Snapshot::find($this->validatedData['to']);
        $from = Arr::get($this->validatedData, 'from') ? Snapshot::find($this->validatedData['from']) : null;

        return $this->handle($webpage, $to, $from);
    }
}
