<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Models\Web\StaticWebsite;
use Dom\HTMLDocument;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Edits made by quoting the HTML they change, like a find and replace: a quote that is not there, or
 * is there more than once without asking for every one, refuses the whole change instead of guessing.
 */
trait WithStaticWebsiteEditing
{
    public const array EDIT_ACTIONS = ['replace', 'insert_before', 'insert_after', 'remove'];

    /**
     * @param list<array{action: string, find: string, html?: string|null, all?: bool}> $edits
     *
     * @return array{0: string, 1: list<array{action: string, before: string, after: string, occurrences: int}>}
     */
    protected function applyEdits(StaticWebsite $staticWebsite, string $html, array $edits, string $where): array
    {
        $diffs = [];

        foreach (array_values($edits) as $index => $edit) {
            $number = $index + 1;
            $action = $edit['action'] ?? '';
            $find   = (string) ($edit['find'] ?? '');
            $new    = (string) ($edit['html'] ?? '');
            $all    = (bool) ($edit['all'] ?? false);

            if (!in_array($action, self::EDIT_ACTIONS, true)) {
                $this->refuse("Edit $number on $where: action must be one of ".implode(', ', self::EDIT_ACTIONS).'.');
            }
            if ($find === '') {
                $this->refuse("Edit $number on $where: find is empty. Quote the exact HTML to change, as info-websites-tool shows it.");
            }
            if ($action !== 'remove') {
                $this->checkNewHtml($staticWebsite, $new, "Edit $number on $where");
            }

            $occurrences = substr_count($html, $find);
            if ($occurrences === 0) {
                $this->refuse("Edit $number on $where: the quoted HTML is not there. Look it up again with info-websites-tool find and quote it exactly.");
            }
            if ($occurrences > 1 && !$all) {
                $this->refuse("Edit $number on $where: the quoted HTML is there $occurrences times. Quote more of it so it is unique, or pass all=true to change every one.");
            }

            $replacement = match ($action) {
                'replace'       => $new,
                'insert_before' => $new.$find,
                'insert_after'  => $find.$new,
                'remove'        => '',
            };

            $html = $all ? str_replace($find, $replacement, $html) : substr_replace($html, $replacement, strpos($html, $find), strlen($find));

            $diffs[] = [
                'action'      => $action,
                'before'      => $this->readable($find),
                'after'       => $action === 'remove' ? '' : $this->readable($replacement),
                'occurrences' => $all ? $occurrences : 1,
            ];
        }

        return [$html, $diffs];
    }

    /**
     * New HTML may not run scripts, load from the old host, or point at a frozen file that is not there.
     */
    protected function checkNewHtml(StaticWebsite $staticWebsite, string $html, string $where): void
    {
        if (preg_match('/<script\b/i', $html)) {
            $this->refuse("$where: scripts cannot be added to an info website.");
        }
        if (preg_match('/\son[a-z]+\s*=/i', $html) || preg_match('/javascript\s*:/i', $html)) {
            $this->refuse("$where: inline event handlers and javascript: links cannot be added to an info website.");
        }
        if (preg_match_all('/<iframe\b[^>]*>/i', $html, $frames)) {
            foreach ($frames[0] as $frame) {
                if (!preg_match('#src="https://(www\.youtube\.com|www\.youtube-nocookie\.com|player\.vimeo\.com)/#i', $frame)) {
                    $this->refuse("$where: only YouTube and Vimeo videos can be embedded.");
                }
            }
        }
        foreach (VerifyStaticWebsite::FORBIDDEN_HOSTS as $host) {
            if (stripos($html, $host) !== false) {
                $this->refuse("$where: nothing may point at $host any more; the website no longer depends on it.");
            }
        }
        preg_match_all('#'.preg_quote(StaticWebsite::ASSET_URL_PREFIX, '#').'([A-Za-z0-9._-]+)#', $html, $assets);
        foreach (array_unique($assets[1]) as $asset) {
            if (!StaticWebsite::assetDisk()->exists($staticWebsite->assetStoragePath($asset))) {
                $this->refuse("$where: ".StaticWebsite::ASSET_URL_PREFIX."$asset is not a file of this website.");
            }
        }
    }

    /**
     * Keeps the head in step with the page: its title, description and canonical address, also in
     * the tags search engines and link previews read.
     */
    protected function withHeadMeta(string $html, ?string $title, ?string $description, ?string $canonicalUrl): string
    {
        if ($title !== null) {
            $html = preg_replace('#<title>.*?</title>#is', '<title>'.e($title).'</title>', $html, 1) ?? $html;
        }

        $values = array_filter([
            'og:title'            => $title,
            'twitter:title'       => $title,
            'name'                => $title,
            'description'         => $description,
            'og:description'      => $description,
            'twitter:description' => $description,
            'og:url'              => $canonicalUrl,
            'url'                 => $canonicalUrl,
        ], fn ($value) => $value !== null);

        $html = preg_replace_callback('/<meta\b[^>]*>/i', function (array $match) use ($values) {
            if (!preg_match('/\b(?:property|name|itemprop)="([^"]+)"/i', $match[0], $key) || !array_key_exists($key[1], $values)) {
                return $match[0];
            }

            return preg_replace('/\scontent="[^"]*"/i', ' content="'.e($values[$key[1]]).'"', $match[0], 1) ?? $match[0];
        }, $html) ?? $html;

        if ($canonicalUrl !== null) {
            $html = preg_replace('/(<link\b[^>]*rel="canonical"[^>]*href=")[^"]*(")/i', '${1}'.e($canonicalUrl).'${2}', $html, 1) ?? $html;
        }

        return $html;
    }

    /**
     * The visible text of a page or part, in order, so the assistant can see what is where without
     * reading all of its HTML.
     *
     * @return list<array{tag: string, text?: string, href?: string, src?: string}>
     */
    protected function outline(string $html, int $limit = 200): array
    {
        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
        $items    = [];

        foreach ($document->querySelectorAll('h1, h2, h3, h4, h5, p, li, a, button, img') as $element) {
            $tag = strtolower($element->localName);
            if (in_array($tag, ['a', 'button'], true) && $element->parentElement?->closest('p, li, h1, h2, h3, h4, h5')) {
                continue;
            }

            $item = ['tag' => $tag];
            if ($tag === 'img') {
                $item['text'] = (string) $element->getAttribute('alt');
                $item['src']  = (string) $element->getAttribute('src');
            } else {
                $text = trim(preg_replace('/\s+/u', ' ', (string) $element->textContent));
                if ($text === '') {
                    continue;
                }
                $item['text'] = Str::limit($text, 200);
                if ($tag === 'a') {
                    $item['href'] = (string) $element->getAttribute('href');
                }
            }

            if (end($items) == $item) {
                continue;
            }
            $items[] = $item;
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return list<array{offset: int, html: string}>
     */
    protected function occurrences(string $html, string $find, int $context = 300, int $limit = 10): array
    {
        $found  = [];
        $offset = 0;
        while (count($found) < $limit && $find !== '' && ($at = strpos($html, $find, $offset)) !== false) {
            $start   = max(0, $at - $context);
            $found[] = ['offset' => $at, 'html' => substr($html, $start, $at - $start + strlen($find) + $context)];
            $offset  = $at + strlen($find);
        }

        return $found;
    }

    protected function readable(string $html): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));

        return Str::limit($text !== '' ? $text : trim(preg_replace('/\s+/u', ' ', $html)), 300);
    }

    protected function refuse(string $message): never
    {
        throw ValidationException::withMessages(['change' => $message]);
    }
}
