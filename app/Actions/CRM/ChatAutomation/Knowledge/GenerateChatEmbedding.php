<?php

namespace App\Actions\CRM\ChatAutomation\Knowledge;

use App\Actions\Helpers\AI\Traits\WithAIBot;
use Cloudstudio\Ollama\Facades\Ollama;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateChatEmbedding
{
    use AsAction;
    use WithAIBot;

    public const OPENAI_MODEL = 'text-embedding-3-small';
    public const OPENAI_DIMENSIONS = 1536;

    /**
     * @return array{column: string, vector: array<int, float>}
     */
    public function handle(string $text): array
    {
        $apiKey = $this->openAiApiKey();

        if ($apiKey) {
            return $this->openAiEmbedding($apiKey, $text);
        }

        return $this->ollamaEmbedding($text);
    }

    /**
     * @return array{column: string, vector: array<int, float>}
     */
    private function openAiEmbedding(string $apiKey, string $text): array
    {
        $vector = Http::withToken($apiKey)
            ->connectTimeout(10)
            ->timeout(30)
            ->post('https://api.openai.com/v1/embeddings', [
                'model'      => self::OPENAI_MODEL,
                'input'      => $text,
                'dimensions' => self::OPENAI_DIMENSIONS,
            ])
            ->throw()
            ->json('data.0.embedding');

        return [
            'column' => 'embedding_'.self::OPENAI_DIMENSIONS,
            'vector' => $vector,
        ];
    }

    /**
     * @return array{column: string, vector: array<int, float>}
     */
    private function ollamaEmbedding(string $text): array
    {
        return [
            'column' => $this->getEmbeddingSize(config('llmdriver.driver')),
            'vector' => Ollama::model(config('ollama-laravel.embedding_model'))->embeddings($text)['embedding'],
        ];
    }

    private function openAiApiKey(): ?string
    {
        $driver = config('auto-translations.default_driver', 'chatgpt5');

        return config("auto-translations.drivers.{$driver}.api_key")
            ?? config('askbot-laravel.openai_api_key')
            ?? config('services.openai.api_key');
    }
}
