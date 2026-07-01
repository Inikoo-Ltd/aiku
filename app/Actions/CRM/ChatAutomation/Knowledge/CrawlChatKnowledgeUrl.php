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
            $text = $crawl
                ? $this->crawl($url, max(1, min($maxPages, 200)))
                : $this->fetchSingle($url);

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
        $html = $this->extractMainContent($html);
        $html = $this->inlineImages($html, $baseUrl);
        $html = $this->inlineLinks($html, $baseUrl);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[^\S\n]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
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
