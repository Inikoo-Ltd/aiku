<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Enums\CRM\Livechat\ChatKnowledgeSourceStatusEnum;
use App\Models\CRM\Livechat\ChatKnowledgeSource;
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

        return $this->htmlToText($html);
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

                $text = $this->htmlToText($response->body());
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

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|template)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    public function asJob(ChatKnowledgeSource $source, string $url, bool $crawl, int $maxPages): void
    {
        $this->handle($source, $url, $crawl, $maxPages);
    }
}
