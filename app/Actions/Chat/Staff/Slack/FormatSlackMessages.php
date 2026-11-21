<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Turns stored Slack messages into a tree of display nodes (paragraph, list, quote, pre, header, fields, context,
 * buttons, divider, attachment; text, br, bold, italic, strike, code, link, user, channel, group, broadcast, emoji).
 * Every string in it is plain text for the screen to print escaped; a link is made only for http, https and mailto,
 * and a colour only from a hex value, so nothing anyone writes in Slack reaches the page as markup.
 */
class FormatSlackMessages
{
    use AsObject;

    private const int MAX_DEPTH = 8;

    private const array LAYOUT_BLOCKS = ['section', 'header', 'divider', 'context', 'actions', 'image'];

    private const string ATOM = '/`([^`\n]+)`|<([^<>\n]*)>|:([a-z0-9_+\'-]+):(?::(skin-tone-[2-6]):)?/u';

    private const array MARKERS = ['*' => 'bold', '_' => 'italic', '~' => 'strike'];

    /** @var array<string, true> */
    private array $userIds = [];

    /** @var array<string, true> */
    private array $channelIds = [];

    /** @var array<string, string> */
    private array $emojiInMessage = [];

    /** @var list<array<string, mixed>> */
    private array $atoms = [];

    /**
     * Every emoji name of the last page handled, resolved once for the whole page (reactions included), keyed by
     * "name" or "name::skin-tone-N".
     *
     * @var array<string, array{unicode: string}|array{url: string|null, name: string}|null>
     */
    public array $resolvedEmoji = [];

    /**
     * @param  iterable<SlackMessage>  $messages
     * @param  list<string>  $alsoResolveEmoji  names to resolve in the same lookup, such as the page's reactions
     * @return array<int, list<array<string, mixed>>>
     */
    public function handle(iterable $messages, array $alsoResolveEmoji = []): array
    {
        $names = array_fill_keys($alsoResolveEmoji, true);
        foreach ($messages as $message) {
            $names += $this->emojiNamesIn($message->text, $message->data ?? []);
        }
        $this->resolvedEmoji = $names === [] ? [] : ResolveSlackEmoji::make()->many(array_keys($names));

        $formatted = [];
        foreach ($messages as $message) {
            $formatted[$message->id] = $this->message($message->text, $message->data ?? []);
        }

        $userNames = $this->userIds === [] ? [] : SlackUser::whereIn('slack_id', array_keys($this->userIds))
            ->get(['slack_id', 'display_name', 'real_name', 'name'])
            ->mapWithKeys(fn (SlackUser $slackUser) => [$slackUser->slack_id => $slackUser->display_name ?: $slackUser->real_name ?: $slackUser->name])
            ->all();
        $channelNames = $this->channelIds === [] ? [] : SlackChannel::kept()->whereIn('slack_id', array_keys($this->channelIds))
            ->pluck('name', 'slack_id')
            ->all();

        $this->fillNames($formatted, $userNames, $channelNames);

        return $formatted;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function plainText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node['type'] ?? null) {
                'text', 'code', 'link' => $node['text'],
                'pre'          => $node['text']."\n",
                'br'           => "\n",
                'user'         => '@'.($node['name'] ?? __('unknown')),
                'channel'      => '#'.($node['name'] ?? __('unknown')),
                'group'        => $node['label'] ?? '@'.__('group'),
                'broadcast'    => '@'.$node['range'],
                'emoji'        => $node['unicode'] ?? ':'.$node['name'].':',
                'list'         => implode('', array_map(fn (array $item) => '• '.self::plainText($item)."\n", $node['items'])),
                'fields'       => implode('', array_map(fn (array $item) => self::plainText($item), $node['items'])),
                'buttons'      => '['.implode('] [', $node['labels']).']'."\n",
                'divider'      => "---\n",
                'attachment'   => implode("\n", array_filter([$node['author'], $node['title']])).($node['author'] || $node['title'] ? "\n" : '').self::plainText($node['blocks']).($node['footer'] ? self::plainText($node['footer'])."\n" : ''),
                'paragraph', 'header', 'context' => self::plainText($node['children'])."\n",
                'quote'                          => self::plainText($node['children']),
                default               => self::plainText($node['children'] ?? []),
            };
        }

        return $text;
    }

    /**
     * The seam for custom emoji (INI-425): a name, with its skin tone, becomes an emoji node, or null to keep the
     * text as typed. Without a lookup, only the Unicode Slack itself gave for the emoji in this message is known.
     *
     * @return array<string, mixed>|null
     */
    public function emoji(string $name, ?string $skinTone, ?string $unicodeHex = null): ?array
    {
        $key = $name.($skinTone ? '::'.$skinTone : '');
        $unicodeHex ??= $this->emojiInMessage[$key] ?? null;
        $unicode = $unicodeHex ? $this->unicodeFromHex($unicodeHex) : null;
        if ($unicode !== null) {
            return ['type' => 'emoji', 'name' => $name, 'skin_tone' => $skinTone, 'unicode' => $unicode];
        }

        $resolved = $this->resolvedEmoji[$key] ?? null;
        if (isset($resolved['unicode'])) {
            return ['type' => 'emoji', 'name' => $name, 'skin_tone' => $skinTone, 'unicode' => $resolved['unicode']];
        }

        return isset($resolved['url']) ? ['type' => 'emoji', 'name' => $name, 'skin_tone' => $skinTone, 'unicode' => null, 'url' => $resolved['url']] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, true>
     */
    private function emojiNamesIn(?string $text, array $data): array
    {
        $names = [];
        $add   = function (string $source) use (&$names) {
            preg_match_all('/:([a-z0-9_+\'-]+):(?::(skin-tone-[2-6]):)?/u', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $names[$match[1].(($match[2] ?? '') !== '' ? '::'.$match[2] : '')] = true;
            }
        };
        $walk = function (mixed $value) use (&$walk, &$names, $add) {
            if (is_string($value)) {
                $add($value);

                return;
            }
            if (!is_array($value)) {
                return;
            }
            if (($value['type'] ?? null) === 'emoji' && is_string($value['name'] ?? null) && !is_string($value['unicode'] ?? null)) {
                $names[$value['name'].(isset($value['skin_tone']) ? '::skin-tone-'.(int) $value['skin_tone'] : '')] = true;
            }
            foreach ($value as $child) {
                $walk($child);
            }
        };
        $add((string) $text);
        $walk($data);

        return $names;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function message(?string $text, array $data): array
    {
        $blocks       = $this->listOf($data['blocks'] ?? null);
        $this->emojiInMessage = $this->emojiUnicodeIn($blocks);

        $body = [];
        if ($this->hasOnlyKnownLayoutBlocks($blocks)) {
            $body = $this->blocks($blocks, 0);
        }
        if ($body === []) {
            $body = $this->mrkdwn((string) $text, 0);
        }
        if ($body === []) {
            $body = $this->blocks($blocks, 0);
        }

        foreach ($this->listOf($data['attachments'] ?? null) as $attachment) {
            if (is_array($attachment) && $node = $this->attachment($attachment, 0)) {
                $body[] = $node;
            }
        }

        return $body;
    }

    /**
     * @param  list<mixed>  $blocks
     */
    private function hasOnlyKnownLayoutBlocks(array $blocks): bool
    {
        $types = array_map(fn ($block) => is_array($block) ? ($block['type'] ?? null) : null, $blocks);

        return array_diff($types, self::LAYOUT_BLOCKS) !== $types
            && array_diff($types, [...self::LAYOUT_BLOCKS, 'rich_text']) === [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mrkdwn(string $text, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $nodes = [];
        $parts = preg_split('/```(.*?)```/s', str_replace("\0", '', $text), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $nodes[] = ['type' => 'pre', 'text' => $this->literal(trim($part, "\n"))];
            } else {
                array_push($nodes, ...$this->lines(explode("\n", $part), $depth));
            }
        }

        return $nodes;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array<string, mixed>>
     */
    private function lines(array $lines, int $depth): array
    {
        $nodes     = [];
        $paragraph = [];
        $count     = count($lines);
        $flush     = function () use (&$paragraph, &$nodes, $depth) {
            while ($paragraph !== [] && trim(end($paragraph)) === '') {
                array_pop($paragraph);
            }
            while ($paragraph !== [] && trim($paragraph[0]) === '') {
                array_shift($paragraph);
            }
            if ($paragraph !== []) {
                $nodes[] = ['type' => 'paragraph', 'children' => $this->inline(implode("\n", $paragraph), $depth)];
            }
            $paragraph = [];
        };

        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];

            if (str_starts_with($line, '&gt;&gt;&gt;')) {
                $flush();
                $rest    = array_slice($lines, $index + 1);
                $first   = ltrim(substr($line, 12));
                $nodes[] = $this->quote($first === '' ? $rest : [$first, ...$rest], $depth);
                break;
            }

            if (str_starts_with($line, '&gt;')) {
                $flush();
                $quoted = [];
                while ($index < $count && str_starts_with($lines[$index], '&gt;')) {
                    $quoted[] = preg_replace('/^&gt; ?/', '', $lines[$index]);
                    $index++;
                }
                $index--;
                $nodes[] = $this->quote($quoted, $depth);
                continue;
            }

            if (preg_match('/^\s*(?:[•◦▪]|(\d+)\.) /u', $line, $start)) {
                $flush();
                $ordered = isset($start[1]) && $start[1] !== '';
                $pattern = $ordered ? '/^\s*\d+\. (.*)$/u' : '/^\s*[•◦▪] (.*)$/u';
                $items   = [];
                while ($index < $count && preg_match($pattern, $lines[$index], $item)) {
                    $items[] = $this->inline($item[1], $depth);
                    $index++;
                }
                $index--;
                $nodes[] = ['type' => 'list', 'ordered' => $ordered, 'start' => $ordered ? (int) $start[1] : 1, 'items' => $items];
                continue;
            }

            $paragraph[] = $line;
        }
        $flush();

        return $nodes;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>
     */
    private function quote(array $lines, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['type' => 'quote', 'children' => [['type' => 'paragraph', 'children' => $this->textWithBreaks($this->literal(implode("\n", $lines)))]]];
        }

        return ['type' => 'quote', 'children' => $this->lines($lines, $depth + 1)];
    }

    /**
     * Code, Slack's <…> sequences and emoji are taken out first, so nothing inside them is read as formatting, then
     * *bold*, _italic_ and ~strike~ open only after a non-word character and close only before one, as in Slack,
     * so snake_case and 2*3*4 stay as written.
     *
     * @return list<array<string, mixed>>
     */
    private function inline(string $text, int $depth): array
    {
        $this->atoms = [];
        $withAtoms   = preg_replace_callback(self::ATOM, function (array $match) {
            $atom = match (true) {
                ($match[1] ?? '') !== '' => ['type' => 'code', 'text' => $this->literal($match[1])],
                ($match[3] ?? '') !== '' => $this->emoji($match[3], ($match[4] ?? '') ?: null),
                default                  => $this->angle($match[2] ?? ''),
            };
            if ($atom === null) {
                return $match[0];
            }
            $this->atoms[] = $atom;

            return "\0".(count($this->atoms) - 1)."\0";
        }, $text) ?? '';

        return $this->marked($withAtoms, $depth);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marked(string $text, int $depth): array
    {
        $nodes  = [];
        $offset = 0;
        $next   = [];
        while ($depth <= self::MAX_DEPTH) {
            $earliest = null;
            foreach (self::MARKERS as $marker => $type) {
                if (!array_key_exists($marker, $next) || ($next[$marker] !== null && $next[$marker][0][1] < $offset)) {
                    $quoted        = preg_quote($marker, '/');
                    $next[$marker] = preg_match("/(?<![\\w$quoted])$quoted([^\\s$quoted](?:[^$quoted\\n]*[^\\s$quoted])?)$quoted(?![\\w$quoted])/u", $text, $match, PREG_OFFSET_CAPTURE, $offset) ? $match : null;
                }
                if ($next[$marker] !== null && ($earliest === null || $next[$marker][0][1] < $earliest[0][0][1])) {
                    $earliest = [$next[$marker], $type];
                }
            }
            if ($earliest === null) {
                break;
            }

            [$match, $type] = $earliest;
            array_push($nodes, ...$this->leaves(substr($text, $offset, $match[0][1] - $offset)));
            $nodes[] = ['type' => $type, 'children' => $this->marked($match[1][0], $depth + 1)];
            $offset  = $match[0][1] + strlen($match[0][0]);
        }

        return [...$nodes, ...$this->leaves(substr($text, $offset))];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leaves(string $text): array
    {
        $nodes = [];
        foreach (preg_split('/\0(\d+)\0/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $piece) {
            if ($index % 2 === 1) {
                $nodes[] = $this->atoms[(int) $piece];
            } else {
                array_push($nodes, ...$this->textWithBreaks($this->literal($piece)));
            }
        }

        return $nodes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function textWithBreaks(string $text): array
    {
        $nodes = [];
        foreach (explode("\n", $text) as $index => $line) {
            if ($index > 0) {
                $nodes[] = ['type' => 'br'];
            }
            if ($line !== '') {
                $nodes[] = ['type' => 'text', 'text' => $line];
            }
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function angle(string $inside): ?array
    {
        [$target, $label] = array_pad(explode('|', $inside, 2), 2, null);
        $label = $label === null || $label === '' ? null : $this->literal($label);

        if (preg_match('/^@([UW][A-Z0-9]+)$/', $target, $match)) {
            return $this->userNode($match[1]);
        }
        if (preg_match('/^#(C[A-Z0-9]+)$/', $target, $match)) {
            return $this->channelNode($match[1], $label);
        }
        if (preg_match('/^!subteam\^([A-Z0-9]+)$/', $target, $match)) {
            return ['type' => 'group', 'id' => $match[1], 'label' => $label];
        }
        if (in_array($target, ['!here', '!channel', '!everyone'], true)) {
            return ['type' => 'broadcast', 'range' => substr($target, 1)];
        }
        if (str_starts_with($target, '!')) {
            return $label === null ? null : ['type' => 'text', 'text' => $label];
        }

        return $this->link($this->literal($target), $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function link(string $url, ?string $label): array
    {
        if (!$this->isSafeUrl($url)) {
            return ['type' => 'text', 'text' => $label ?? $url];
        }

        return ['type' => 'link', 'url' => $url, 'text' => $label ?? preg_replace('/^mailto:/i', '', $url)];
    }

    public static function isSafeUrl(?string $url): bool
    {
        return is_string($url)
            && preg_match('/^(https?:\/\/|mailto:)/i', $url) === 1
            && preg_match('/[\x00-\x20\x7f"<>\\\\`]/', $url) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function userNode(string $id): array
    {
        $this->userIds[$id] = true;

        return ['type' => 'user', 'id' => $id, 'name' => null];
    }

    /**
     * @return array<string, mixed>
     */
    private function channelNode(string $id, ?string $label): array
    {
        $this->channelIds[$id] = true;

        return ['type' => 'channel', 'id' => $id, 'name' => $label];
    }

    private function literal(string $mrkdwn): string
    {
        $plain = preg_replace_callback('/<([^<>\n]*)>/', function (array $match) {
            [$target, $label] = array_pad(explode('|', $match[1], 2), 2, null);

            return $label ?? $target;
        }, $mrkdwn) ?? $mrkdwn;

        return strtr($plain, ['&amp;' => '&', '&lt;' => '<', '&gt;' => '>']);
    }

    /**
     * @param  list<mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    private function blocks(array $blocks, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return [];
        }

        $nodes = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            switch ($block['type'] ?? null) {
                case 'rich_text':
                    array_push($nodes, ...$this->richText($this->listOf($block['elements'] ?? null), $depth));
                    break;
                case 'header':
                    $nodes[] = ['type' => 'header', 'children' => $this->textWithBreaks($this->plainOf($block['text'] ?? null))];
                    break;
                case 'section':
                    array_push($nodes, ...$this->textObject($block['text'] ?? null, $depth));
                    $fields = array_values(array_filter(array_map(fn ($field) => $this->textObject($field, $depth), $this->listOf($block['fields'] ?? null))));
                    if ($fields !== []) {
                        $nodes[] = ['type' => 'fields', 'items' => $fields];
                    }
                    if (($block['accessory']['type'] ?? null) === 'button') {
                        $nodes[] = ['type' => 'buttons', 'labels' => [$this->plainOf($block['accessory']['text'] ?? null)]];
                    }
                    break;
                case 'context':
                    $children = [];
                    foreach ($this->listOf($block['elements'] ?? null) as $element) {
                        if (is_array($element) && in_array($element['type'] ?? null, ['mrkdwn', 'plain_text'], true)) {
                            if ($children !== []) {
                                $children[] = ['type' => 'text', 'text' => '  '];
                            }
                            array_push($children, ...$this->flatInline($this->textObject($element, $depth)));
                        }
                    }
                    if ($children !== []) {
                        $nodes[] = ['type' => 'context', 'children' => $children];
                    }
                    break;
                case 'divider':
                    $nodes[] = ['type' => 'divider'];
                    break;
                case 'actions':
                    $labels = [];
                    foreach ($this->listOf($block['elements'] ?? null) as $element) {
                        if (is_array($element) && ($element['type'] ?? null) === 'button') {
                            $labels[] = $this->plainOf($element['text'] ?? null);
                        }
                    }
                    if ($labels !== []) {
                        $nodes[] = ['type' => 'buttons', 'labels' => $labels];
                    }
                    break;
                case 'image':
                    $caption = $this->plainOf($block['title'] ?? null) ?: (string) ($block['alt_text'] ?? '');
                    if ($this->isSafeUrl($block['image_url'] ?? null)) {
                        $nodes[] = ['type' => 'paragraph', 'children' => [$this->link($block['image_url'], $caption ?: null)]];
                    }
                    break;
            }
        }

        return $nodes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function textObject(mixed $object, int $depth): array
    {
        if (!is_array($object) || !is_string($object['text'] ?? null) || $object['text'] === '') {
            return [];
        }

        if (($object['type'] ?? null) === 'mrkdwn') {
            return $this->mrkdwn($object['text'], $depth + 1);
        }

        return [['type' => 'paragraph', 'children' => $this->textWithBreaks($object['text'])]];
    }

    private function plainOf(mixed $object): string
    {
        return is_array($object) && is_string($object['text'] ?? null) ? $object['text'] : '';
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function flatInline(array $blocks): array
    {
        $inline = [];
        foreach ($blocks as $index => $block) {
            if ($index > 0) {
                $inline[] = ['type' => 'br'];
            }
            array_push($inline, ...(($block['type'] ?? null) === 'paragraph' ? $block['children'] : [['type' => 'text', 'text' => rtrim(self::plainText([$block]))]]));
        }

        return $inline;
    }

    /**
     * @param  list<mixed>  $elements
     * @return list<array<string, mixed>>
     */
    private function richText(array $elements, int $depth): array
    {
        $nodes = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $children = $this->listOf($element['elements'] ?? null);
            switch ($element['type'] ?? null) {
                case 'rich_text_section':
                    $nodes[] = ['type' => 'paragraph', 'children' => $this->richInline($children)];
                    break;
                case 'rich_text_list':
                    $ordered = ($element['style'] ?? null) === 'ordered';
                    $nodes[] = [
                        'type'    => 'list',
                        'ordered' => $ordered,
                        'start'   => $ordered ? (int) ($element['offset'] ?? 0) + 1 : 1,
                        'items'   => array_map(fn ($item) => $this->richInline($this->listOf(is_array($item) ? ($item['elements'] ?? null) : null)), $children),
                    ];
                    break;
                case 'rich_text_quote':
                    $nodes[] = ['type' => 'quote', 'children' => [['type' => 'paragraph', 'children' => $this->richInline($children)]]];
                    break;
                case 'rich_text_preformatted':
                    $nodes[] = ['type' => 'pre', 'text' => self::plainText($this->richInline($children))];
                    break;
            }
        }

        return $nodes;
    }

    /**
     * @param  list<mixed>  $elements
     * @return list<array<string, mixed>>
     */
    private function richInline(array $elements): array
    {
        $nodes = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }
            $node = match ($element['type'] ?? null) {
                'text'      => $this->textWithBreaks((string) ($element['text'] ?? '')),
                'link'      => [$this->link((string) ($element['url'] ?? ''), ($element['text'] ?? '') ?: null)],
                'user'      => is_string($element['user_id'] ?? null) ? [$this->userNode($element['user_id'])] : [],
                'channel'   => is_string($element['channel_id'] ?? null) ? [$this->channelNode($element['channel_id'], null)] : [],
                'usergroup' => [['type' => 'group', 'id' => (string) ($element['usergroup_id'] ?? ''), 'label' => null]],
                'broadcast' => in_array($element['range'] ?? null, ['here', 'channel', 'everyone'], true) ? [['type' => 'broadcast', 'range' => $element['range']]] : [],
                'emoji'     => [$this->emoji(
                    (string) ($element['name'] ?? ''),
                    isset($element['skin_tone']) ? 'skin-tone-'.(int) $element['skin_tone'] : null,
                    is_string($element['unicode'] ?? null) ? $element['unicode'] : null
                ) ?? ['type' => 'text', 'text' => ':'.($element['name'] ?? '').':']],
                'date'      => [['type' => 'text', 'text' => (string) ($element['fallback'] ?? '')]],
                'color'     => [['type' => 'text', 'text' => (string) ($element['value'] ?? '')]],
                default     => is_string($element['text'] ?? null) ? [['type' => 'text', 'text' => $element['text']]] : [],
            };
            $style = is_array($element['style'] ?? null) ? $element['style'] : [];
            if (($style['code'] ?? false) && $node !== []) {
                $node = [['type' => 'code', 'text' => self::plainText($node)]];
            }
            foreach (['strike', 'italic', 'bold'] as $mark) {
                if (($style[$mark] ?? false) && $node !== []) {
                    $node = [['type' => $mark, 'children' => $node]];
                }
            }
            array_push($nodes, ...$node);
        }

        return $nodes;
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @return array<string, mixed>|null
     */
    private function attachment(array $attachment, int $depth): ?array
    {
        $blocks = $this->mrkdwn((string) ($attachment['pretext'] ?? ''), $depth + 1);

        $nested = $this->listOf($attachment['blocks'] ?? null);
        if ($nested !== []) {
            array_push($blocks, ...$this->blocks($nested, $depth + 1));
        } else {
            array_push($blocks, ...$this->mrkdwn((string) ($attachment['text'] ?? ''), $depth + 1));
        }
        if (!isset($attachment['text']) && $nested === []) {
            foreach ($this->listOf($attachment['message_blocks'] ?? null) as $shared) {
                array_push($blocks, ...$this->blocks($this->listOf(is_array($shared) ? ($shared['message']['blocks'] ?? null) : null), $depth + 1));
            }
        }

        $fields = [];
        foreach ($this->listOf($attachment['fields'] ?? null) as $field) {
            if (is_array($field)) {
                $fields[] = [
                    ['type' => 'paragraph', 'children' => [['type' => 'bold', 'children' => $this->textWithBreaks((string) ($field['title'] ?? ''))]]],
                    ...$this->mrkdwn((string) ($field['value'] ?? ''), $depth + 1),
                ];
            }
        }
        if ($fields !== []) {
            $blocks[] = ['type' => 'fields', 'items' => $fields];
        }

        $buttons = array_values(array_filter(array_map(fn ($action) => is_array($action) ? (string) ($action['text'] ?? '') : '', $this->listOf($attachment['actions'] ?? null))));
        if ($buttons !== []) {
            $blocks[] = ['type' => 'buttons', 'labels' => $buttons];
        }

        if ($this->isSafeUrl($attachment['image_url'] ?? null)) {
            $blocks[] = ['type' => 'paragraph', 'children' => [$this->link($attachment['image_url'], null)]];
        }

        $title  = is_string($attachment['title'] ?? null) && $attachment['title'] !== '' ? $this->literal($attachment['title']) : null;
        $author = is_string($attachment['author_name'] ?? null) && $attachment['author_name'] !== '' ? $attachment['author_name'] : null;
        $footer = is_string($attachment['footer'] ?? null) && $attachment['footer'] !== '' ? $this->inline($attachment['footer'], $depth + 1) : null;

        if ($blocks === [] && $title === null && is_string($attachment['fallback'] ?? null) && $attachment['fallback'] !== '') {
            $blocks[] = ['type' => 'paragraph', 'children' => $this->textWithBreaks($this->literal($attachment['fallback']))];
        }

        if ($blocks === [] && $title === null && $author === null) {
            return null;
        }

        $color = is_string($attachment['color'] ?? null) && preg_match('/^#?([0-9a-fA-F]{6})$/', $attachment['color'], $hex) ? '#'.$hex[1] : null;

        return [
            'type'      => 'attachment',
            'color'     => $color,
            'author'    => $author,
            'title'     => $title,
            'title_url' => $this->isSafeUrl($attachment['title_link'] ?? null) ? $attachment['title_link'] : null,
            'blocks'    => $blocks,
            'footer'    => $footer,
        ];
    }

    /**
     * @param  list<mixed>  $blocks
     * @return array<string, string>
     */
    private function emojiUnicodeIn(array $blocks): array
    {
        $found = [];
        $walk = function (mixed $value) use (&$walk, &$found) {
            if (!is_array($value)) {
                return;
            }
            if (($value['type'] ?? null) === 'emoji' && is_string($value['name'] ?? null) && is_string($value['unicode'] ?? null)) {
                $found[$value['name'].(isset($value['skin_tone']) ? '::skin-tone-'.(int) $value['skin_tone'] : '')] = $value['unicode'];
            }
            foreach ($value as $child) {
                $walk($child);
            }
        };
        $walk($blocks);

        return $found;
    }

    private function unicodeFromHex(string $hex): ?string
    {
        if (!preg_match('/^[0-9a-f]{1,6}(-[0-9a-f]{1,6})*$/i', $hex)) {
            return null;
        }

        $characters = array_map(fn (string $code) => mb_chr((int) hexdec($code), 'UTF-8'), explode('-', $hex));

        return in_array(false, $characters, true) ? null : implode('', $characters);
    }

    /**
     * @return list<mixed>
     */
    private function listOf(mixed $value): array
    {
        return is_array($value) && array_is_list($value) ? $value : [];
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  array<string, string|null>  $userNames
     * @param  array<string, string|null>  $channelNames
     */
    private function fillNames(array &$nodes, array $userNames, array $channelNames): void
    {
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }
            if (($node['type'] ?? null) === 'user' && is_string($node['id'] ?? null)) {
                $node['name'] = $userNames[$node['id']] ?? null;
            } elseif (($node['type'] ?? null) === 'channel' && is_string($node['id'] ?? null)) {
                $node['name'] = $channelNames[$node['id']] ?? null;
            }
            $this->fillNames($node, $userNames, $channelNames);
        }
    }
}
