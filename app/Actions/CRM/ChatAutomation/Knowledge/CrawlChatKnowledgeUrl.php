<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Models\Chat\ChatKnowledgeSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsJob;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlProgress;
use Spatie\Crawler\CrawlResponse;

class CrawlChatKnowledgeUrl
{
    use AsAction;
    use AsJob;

    public function handle(ChatKnowledgeSource $source, string $url, bool $crawl, int $maxPages): void
    {
        try {
            $text = match (true) {
                $this->isVideoUrl($url) => $this->fetchVideoContent($source, $url),
                $crawl                  => $this->crawl($url, max(1, min($maxPages, 200))),
                default                 => $this->fetchSingle($url),
            };

            $text = trim($text);

            if ($text === '') {
                $source->update(['status' => ChatKnowledgeSourceStatusEnum::FAILED]);

                return;
            }

            $source->update([
                'content'      => $text,
                'content_hash' => md5($text),
                'status'       => ChatKnowledgeSourceStatusEnum::PENDING,
            ]);

            EmbedChatKnowledgeSource::run($source);
        } catch (\Throwable $e) {
            Log::warning('Chat knowledge URL fetch failed', [
                'chat_knowledge_source_id' => $source->id,
                'url'                      => $url,
                'error'                    => $e->getMessage(),
            ]);

            $source->update(['status' => ChatKnowledgeSourceStatusEnum::FAILED]);
        }
    }

    private function fetchSingle(string $url): string
    {
        $html = Http::timeout(20)->get($url)->body();

        return $this->htmlToText($html, $url);
    }

    private function isVideoUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'youtube.com')
            || str_contains($host, 'youtu.be')
            || str_contains($host, 'vimeo.com');
    }

    /**
     * A video page has no crawlable prose, so instead of scraping HTML we pull its
     * oEmbed metadata (title + channel, no API key needed) and keep the watch link.
     * This makes the video findable by name and lets the assistant share it back.
     */
    private function fetchVideoContent(ChatKnowledgeSource $source, string $url): string
    {
        $host     = strtolower((string) parse_url($url, PHP_URL_HOST));
        $endpoint = str_contains($host, 'vimeo')
            ? 'https://vimeo.com/api/oembed.json'
            : 'https://www.youtube.com/oembed';

        $lines = [];

        try {
            $response = Http::timeout(15)->acceptJson()->get($endpoint, ['url' => $url, 'format' => 'json']);

            if ($response->successful()) {
                $data    = $response->json();
                $title   = trim((string) ($data['title'] ?? ''));
                $author  = trim((string) ($data['author_name'] ?? ''));

                if ($title !== '') {
                    $lines[] = $title;
                }
                if ($author !== '') {
                    $lines[] = 'Video by '.$author;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Chat knowledge video metadata fetch failed', [
                'chat_knowledge_source_id' => $source->id,
                'url'                      => $url,
                'error'                    => $e->getMessage(),
            ]);
        }

        if ($lines === []) {
            $title = trim((string) $source->title);
            if ($title !== '') {
                $lines[] = $title;
            }
        }

        $lines[] = 'Watch the video here: '.$url;

        return trim(implode("\n", $lines));
    }

    private function crawl(string $startUrl, int $maxPages): string
    {
        $prefix = rtrim(parse_url($startUrl, PHP_URL_PATH) ?: '/', '/');
        $pages  = [];

        Crawler::create($startUrl)
            ->internalOnly()
            ->respectRobots()
            ->shouldCrawl(function (string $url) use ($prefix): bool {
                $path = rtrim(parse_url($url, PHP_URL_PATH) ?: '/', '/');

                return str_starts_with($path, $prefix);
            })
            ->onCrawled(function (string $url, CrawlResponse $response, CrawlProgress $progress) use (&$pages, $maxPages): void {
                if (count($pages) >= $maxPages || ! $response->isSuccessful()) {
                    return;
                }

                $text = $this->htmlToText($response->body(), $url);
                if ($text !== '') {
                    $pages[$url] = $text;
                }
            })
            ->shouldStopCallback(fn (): bool => count($pages) >= $maxPages)
            ->start();

        return collect($pages)
            ->map(fn (string $text, string $url) => $url."\n".$text)
            ->implode("\n\n———\n\n");
    }

    private function htmlToText(string $html, string $baseUrl = ''): string
    {
        $embedded = $this->extractEmbeddedText($html);

        $main = $this->extractMainContent($html);
        $main = $this->inlineImages($main, $baseUrl);
        $main = $this->inlineLinks($main, $baseUrl);
        $main = $this->insertStructuralBreaks($main);

        $text = html_entity_decode(strip_tags($main), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[^\S\n]+/', ' ', $text) ?? $text;
        $text = $this->tidyCellSeparators($text);
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        return $this->mergeEmbeddedText($text, $embedded);
    }

    /**
     * strip_tags glues adjacent cells and blocks together ("Zone 4Zone 5£10.00"),
     * destroying the row/column relationships a reader needs to answer questions
     * about tabular data (shipping prices, weight brackets). Turn table cells into
     * "|"-separated columns and close block elements with newlines first.
     */
    private function insertStructuralBreaks(string $html): string
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('#</(td|th)>#i', ' | ', $html) ?? $html;
        $html = preg_replace('#</(tr|p|div|li|h[1-6]|section|article|header|footer|table|thead|tbody|caption|dt|dd)>#i', "\n", $html) ?? $html;

        return $html;
    }

    private function tidyCellSeparators(string $text): string
    {
        $text = preg_replace('/ *\| */', ' | ', $text) ?? $text;
        $text = preg_replace('/\|(?: *\|)+/', '|', $text) ?? $text;
        $text = preg_replace('/(?:^|\n) *\| */', "\n", $text) ?? $text;
        $text = preg_replace('/ *\| *(?=\n|$)/', '', $text) ?? $text;

        return $text;
    }

    /**
     * Single-page apps (Inertia, JSON-LD, framework hydration payloads) render body
     * copy only after JavaScript runs, so a plain HTTP fetch sees the container but
     * not the prose. Recover that prose from the JSON embedded in the raw HTML.
     *
     * @return array<int, string>
     */
    private function extractEmbeddedText(string $html): array
    {
        $fragments = [];

        foreach ($this->embeddedJsonBlobs($html) as $blob) {
            $decoded = json_decode($blob, true);

            if (is_array($decoded)) {
                $this->collectJsonProse($decoded, $fragments);
            }
        }

        $seen   = [];
        $unique = [];

        foreach ($fragments as $fragment) {
            $key = mb_strtolower($fragment);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key]  = true;
            $unique[]    = $fragment;
        }

        return $unique;
    }

    /**
     * @return array<int, string>
     */
    private function embeddedJsonBlobs(string $html): array
    {
        $blobs = [];

        if (preg_match_all('/data-page=("|\')(.*?)\1/is', $html, $matches)) {
            foreach ($matches[2] as $raw) {
                $blobs[] = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
            }
        }

        if (preg_match_all('/<script\b[^>]*\btype=("|\')application\/(ld\+)?json\1[^>]*>(.*?)<\/script>/is', $html, $matches)) {
            foreach ($matches[3] as $raw) {
                $blobs[] = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5);
            }
        }

        return $blobs;
    }

    /**
     * @param  mixed  $node
     * @param  array<int, string>  $fragments
     */
    private function collectJsonProse($node, array &$fragments): void
    {
        if (is_array($node)) {
            $skipKeys = [];

            if (! array_is_list($node)) {
                [$pair, $skipKeys] = $this->labeledPair($node);

                if ($pair !== '') {
                    $fragments[] = $pair;
                }
            }

            foreach ($node as $key => $value) {
                if (in_array($key, $skipKeys, true)) {
                    continue;
                }

                $this->collectJsonProse($value, $fragments);
            }

            return;
        }

        if (! is_string($node)) {
            return;
        }

        $prose = $this->jsonValueToProse($node);

        if ($prose !== '') {
            $fragments[] = $prose;
        }
    }

    /**
     * FAQ/accordion payloads store each entry as a labelled object (question in a
     * "label"/"title" key, answer in a "description"/"answer" key). Emit the two as
     * one fragment so the question stays chunked with its answer; otherwise vector
     * search matches the question but retrieves a chunk that holds no answer.
     *
     * @param  array<string, mixed>  $node
     * @return array{0: string, 1: array<int, string>}
     */
    private function labeledPair(array $node): array
    {
        $titleKeys = ['label', 'title', 'question', 'heading', 'header', 'term', 'name'];
        $bodyKeys  = ['description', 'answer', 'text', 'content', 'body', 'definition'];

        [$title, $titleKey] = $this->firstProseValue($node, $titleKeys);
        [$body, $bodyKey]   = $this->firstProseValue($node, $bodyKeys);

        if ($title !== '' && $body !== '' && $title !== $body) {
            return [$title."\n".$body, [$titleKey, $bodyKey]];
        }

        return ['', []];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, string>  $keys
     * @return array{0: string, 1: string}
     */
    private function firstProseValue(array $node, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($node[$key]) && is_string($node[$key])) {
                $prose = $this->jsonValueToProse($node[$key]);

                if ($prose !== '') {
                    return [$prose, $key];
                }
            }
        }

        return ['', ''];
    }

    private function jsonValueToProse(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '<')) {
            $value = $this->insertStructuralBreaks($value);
            $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);
            $value = preg_replace('/[^\S\n]+/', ' ', $value) ?? $value;
            $value = $this->tidyCellSeparators($value);
            $value = trim(preg_replace('/\R{2,}/', "\n", $value) ?? $value);
        } else {
            $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        }

        return $this->looksLikeProse($value) ? $value : '';
    }

    private function looksLikeProse(string $value): bool
    {
        if (mb_strlen($value) < 12) {
            return false;
        }

        if (preg_match('#^https?://#i', $value) || str_starts_with($value, 'data:') || str_starts_with($value, '/')) {
            return false;
        }

        if (preg_match('/\bfa[bsrldt]?-[a-z]/i', $value)) {
            return false;
        }

        if (preg_match('/(font-family|font-size|rgb\(|#[0-9a-f]{6}\b|[a-z-]+\s*:\s*[^;]+;)/i', $value)) {
            return false;
        }

        if (str_contains($value, ' | ')) {
            return true;
        }

        return (bool) preg_match('/\p{L}+\s+\p{L}+/u', $value);
    }

    /**
     * @param  array<int, string>  $embedded
     */
    private function mergeEmbeddedText(string $text, array $embedded): string
    {
        if ($embedded === []) {
            return $text;
        }

        $haystack = mb_strtolower($text);
        $extra    = [];

        foreach ($embedded as $fragment) {
            if (! str_contains($haystack, mb_strtolower($fragment))) {
                $extra[] = $fragment;
            }
        }

        if ($extra === []) {
            return $text;
        }

        return trim($text."\n\n".implode("\n", $extra));
    }

    /**
     * Keep only the main article body, dropping navigation, headers, footers and
     * sidebars (e.g. "Related Topics") so their links and titles never bleed into
     * the knowledge content and mislead the answer.
     */
    private function extractMainContent(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|template|svg)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        if (trim($html) === '') {
            return '';
        }

        $dom    = new \DOMDocument();
        $useErr = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($useErr);

        if (! $loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);

        foreach ($this->collectNodes($xpath, $this->noiseQuery()) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $this->pruneLinkHeavyNodes($xpath);
        $this->serializeLeafTables($xpath, $dom);

        $main = $this->pickMainNode($xpath, $dom);

        if ($main === null) {
            return $html;
        }

        $inner = '';
        foreach ($main->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }

    private function noiseQuery(): string
    {
        $tags    = ['nav', 'header', 'footer', 'aside', 'form', 'iframe'];
        $needles = ['sidebar', 'related', 'breadcrumb', 'comment', 'cookie', 'newsletter', 'social', 'share-buttons', 'site-header', 'site-footer'];

        $parts = array_map(fn (string $tag): string => '//'.$tag, $tags);

        foreach (['class', 'id'] as $attribute) {
            foreach ($needles as $needle) {
                $parts[] = '//*[contains(translate(@'.$attribute.', "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "'.$needle.'")]';
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * Drop link-list blocks (navigation menus, "Related Topics" cards, tables of
     * contents) generically: any block whose text is mostly anchor text is chrome,
     * not article prose, regardless of its class name.
     */
    private function pruneLinkHeavyNodes(\DOMXPath $xpath): void
    {
        foreach ($this->collectNodes($xpath, '//ul | //ol | //nav | //div | //section') as $node) {
            if (! $node instanceof \DOMElement || $node->parentNode === null) {
                continue;
            }

            $text   = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
            $length = mb_strlen($text);

            if ($length < 60) {
                continue;
            }

            $links = $node->getElementsByTagName('a');

            if ($links->length < 4) {
                continue;
            }

            $linkLength = 0;
            foreach ($links as $link) {
                $linkLength += mb_strlen(trim($link->textContent));
            }

            if ($linkLength >= 0.6 * $length) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    /**
     * Replace innermost data tables with a "cell | cell" per row text block so the
     * relationship between headers and values (weight brackets and shipping prices)
     * survives strip_tags. Layout tables that merely wrap other tables are left for
     * the normal block-break flow so each nested table stays its own block.
     */
    private function serializeLeafTables(\DOMXPath $xpath, \DOMDocument $dom): void
    {
        foreach ($this->collectNodes($xpath, '//table') as $table) {
            if (! $table instanceof \DOMElement || $table->parentNode === null) {
                continue;
            }

            if ($table->getElementsByTagName('table')->length > 0) {
                continue;
            }

            $text = $this->serializeTable($table);

            if ($text === '') {
                continue;
            }

            $table->parentNode->replaceChild($dom->createTextNode("\n".$text."\n"), $table);
        }
    }

    private function serializeTable(\DOMElement $table): string
    {
        $lines = [];

        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = [];

            foreach ($row->childNodes as $cell) {
                if ($cell instanceof \DOMElement && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
                }
            }

            if (implode('', $cells) !== '') {
                $lines[] = implode(' | ', $cells);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, \DOMNode>
     */
    private function collectNodes(\DOMXPath $xpath, string $query): array
    {
        $nodes = [];
        $found = $xpath->query($query);

        if ($found !== false) {
            foreach ($found as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function pickMainNode(\DOMXPath $xpath, \DOMDocument $dom): ?\DOMNode
    {
        $queries = [
            '//main',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "entry-content")]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "post-content")]',
            '//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "article-content")]',
            '//article',
            '//*[@id="content"]',
        ];

        foreach ($queries as $query) {
            $best    = null;
            $bestLen = 0;

            foreach ($this->collectNodes($xpath, $query) as $node) {
                $length = mb_strlen(trim($node->textContent));
                if ($length > $bestLen) {
                    $best    = $node;
                    $bestLen = $length;
                }
            }

            if ($best !== null && $bestLen > 200) {
                return $best;
            }
        }

        return $dom->getElementsByTagName('body')->item(0);
    }

    private function inlineImages(string $html, string $baseUrl): string
    {
        return preg_replace_callback('/<img\b[^>]*>/is', function (array $match) use ($baseUrl): string {
            $tag = $match[0];
            $src = $this->extractAttribute($tag, 'src') ?: $this->extractAttribute($tag, 'data-src');

            if ($src === '' || str_starts_with($src, 'data:')) {
                return ' ';
            }

            $src = $this->absoluteUrl($baseUrl, $src);
            $alt = trim($this->extractAttribute($tag, 'alt'));

            return $alt !== '' ? "\n[Image: {$alt} - {$src}]\n" : "\n[Image: {$src}]\n";
        }, $html) ?? $html;
    }

    private function inlineLinks(string $html, string $baseUrl): string
    {
        return preg_replace_callback('/<a\b[^>]*\bhref=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', function (array $match) use ($baseUrl): string {
            $href  = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
            $label = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5));

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                return $label;
            }

            $href = $this->absoluteUrl($baseUrl, $href);

            return $label !== '' ? "{$label} ({$href})" : $href;
        }, $html) ?? $html;
    }

    private function extractAttribute(string $tag, string $name): string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'=("|\')(.*?)\1/is', $tag, $match)) {
            return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);
        }

        return '';
    }

    private function absoluteUrl(string $base, string $link): string
    {
        $link = trim($link);

        if ($link === '' || preg_match('#^https?://#i', $link) || str_starts_with($link, 'mailto:') || str_starts_with($link, 'tel:')) {
            return $link;
        }

        $parts = parse_url($base);

        if ($base === '' || ! isset($parts['scheme'], $parts['host'])) {
            return $link;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($link, '//')) {
            return $parts['scheme'].':'.$link;
        }

        if (str_starts_with($link, '/')) {
            return $origin.$link;
        }

        $directory = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';

        return $origin.($directory ?: '/').$link;
    }

    public function asJob(ChatKnowledgeSource $source, string $url, bool $crawl, int $maxPages): void
    {
        $this->handle($source, $url, $crawl, $maxPages);
    }
}
