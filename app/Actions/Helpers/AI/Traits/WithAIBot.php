<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Helpers\AI\Traits;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Response;
use App\Models\Helpers\Chunk;
use Cloudstudio\Ollama\Facades\Ollama;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait WithAIBot
{
    public function driverHelper(string $driver, string $key): string
    {
        return config("llmdriver.drivers.$driver.$key");
    }

    public function getEmbeddingSize(string $embedding_driver): string
    {
        $embeddingModel = $this->driverHelper($embedding_driver, 'models.embedding_model');

        $size = config('llmdriver.embedding_sizes.'.$embeddingModel);

        if ($size) {
            return 'embedding_'.$size;
        }

        return 'embedding_3072';
    }

    public function getSiblings(Collection $results): Collection
    {
        $siblingsIncluded = collect();

        foreach ($results as $result) {
            $siblingsIncluded->push($result);

            $previousSibling = $this->getSiblingOrNot($result, $result->section_number - 1);
            if ($previousSibling) {
                $siblingsIncluded->push($previousSibling);
            }

            $nextSibling = $this->getSiblingOrNot($result, $result->section_number + 1);
            if ($nextSibling) {
                $siblingsIncluded->push($nextSibling);
            }
        }

        return $siblingsIncluded->unique('id');
    }

    public function getSiblingOrNot(Chunk $result, int $sectionNumber): ?Chunk
    {
        return Chunk::query()
            ->where('sort_order', $result->sort_order)
            ->where('section_number', $sectionNumber)
            ->first();
    }

    public function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            throw new InvalidArgumentException('Vectors must be of the same length.');
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += pow($vectorA[$i], 2);
            $magnitudeB += pow($vectorB[$i], 2);
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA * $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function askActionDeepseek(array $messages, $maxTokens = 800, $responseType = 'json_object', $stream = true)
    {
        $apiKey = config('askbot-laravel.deepseek_api_key');
        $url    = config('askbot-laravel.deepseek_api_url');
        $model  = config('askbot-laravel.model');

        $client = new Client();

        $payload = [
            'model'           => $model,
            'messages'        => $messages,
            'stream'          => $stream,
            'max_tokens'      => $maxTokens,
            'response_format' => [
                'type' => $responseType
            ]
        ];

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => $stream ? 'text/event-stream' : 'application/json',
        ];

        $response = $client->post($url, [
            'json'    => $payload,
            'headers' => $headers,
        ]);

        if ($stream) {
            $stream = $response->getBody();
            $result = '';
            while (!$stream->eof()) {
                $chunk = $stream->read(1024);
                $lines = explode("\n", $chunk);

                foreach ($lines as $line) {
                    if (str_starts_with($line, 'data:')) {
                        $data        = trim(substr($line, 5));
                        $decodedData = json_decode($data, true);
                        if ($decodedData && isset($decodedData['choices'][0]['delta']['content'])) {
                            // Process the decoded data as needed
                            $result .= $decodedData['choices'][0]['delta']['content'];
                        }
                    }
                }
                flush();
            }

            return $result;
        } else {
            $body = $response->getBody();
            $data = json_decode($body, true);

            return Arr::get($data, 'choices.0.message.content', '');
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function askDeepseek(array $messages): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $apiKey  = config('askbot-laravel.deepseek_api_key');
        $baseUrl = config('askbot-laravel.deepseek_api_url');
        $model   = config('askbot-laravel.model');

        $payload = [
            'model'      => $model,
            'messages'   => $messages,
            'stream'     => true,
            'max_tokens' => 800
        ];

        $client = new Client([
            'base_uri' => $baseUrl,
            'headers'  => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'text/event-stream',
            ],
        ]);

        $response = $client->post('', [
            'json'   => $payload,
            'stream' => true,
        ]);

        return Response::stream(function () use ($response) {
            $stream = $response->getBody();

            while (!$stream->eof()) {
                $chunk = $stream->read(32);
                echo $chunk;
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
        ]);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function askLlama($question): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $response = Ollama::model(config('ollama-laravel.model'))
            ->prompt($question)
            ->stream(true)
            ->ask();

        return Response::stream(function () use ($response) {
            Ollama::processStream($response->getBody(), function ($data) {
                echo 'data: '.json_encode(
                    [
                            'choices' => [
                                [
                                    'delta' => [
                                        'content' => $data['response']
                                    ]
                                ]
                            ]
                        ]
                )."\n\n";
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
        ]);
    }


}
