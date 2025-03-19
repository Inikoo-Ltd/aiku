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
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait WithAIBot
{
    public function driverHelper(string $driver, string $key): string
    {
        return config("llmdriver.drivers.{$driver}.{$key}");
    }

    public function get_embedding_size(string $embedding_driver): string
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

    public function promptTemplate($context, $question): string
    {
        return "
        **Role**
        You are a Chatbot operating in a Retrieval-Augmented Generation (RAG) system. Your responsibility is to generate an accurate response to the user's query based strictly on the provided context.

        **Instructions**
        - If the context does not contain enough information to answer the question, respond politely, for example: \"I'm sorry, but I don't have enough information to answer that question based on what I know.\"
        - Do not include the question or context in your response.
        - Avoid using phrases like \"Based on the provided context\" in your response.

        **CONTEXT (JSON)**
        {$context}
        
        **RESPONSE**
        Provide a direct, concise, and accurate answer using only the information in the context above.

        **QUESTION**
        {$question}

        ";
    }

    public function simplePrompt($question): string
    {
        return "
        **Role**
        You are a Chatbot Helpdesk Agent. Provide concise and accurate responses to user queries.

        **RESPONSE**
        Provide a direct, concise, and accurate answer. Avoid being verbose and get straight to the point, but ensure the response feels human.

        **QUESTION**
        {$question}
        ";
    }

    public function askDeepseek($question)
    {
        $apiKey = config('askbot-laravel.deepseek_api_key');
        $baseUrl = config('askbot-laravel.deepseek_api_url');
        $model = config('askbot-laravel.model');

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant'],
                ['role' => 'user', 'content' => $question],
            ],
            'stream' => true,
            'max_tokens' => 800
        ];

        $client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'text/event-stream',
            ],
        ]);

        $response = $client->post('', [
            'json' => $payload,
            'stream' => true,
        ]);

        return Response::stream(function () use ($response) {
            $stream = $response->getBody();

            while (!$stream->eof()) {
                $chunk = $stream->read(1024);
                echo $chunk;
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    public function askLlama($question)
    {
        $response = Ollama::model(config('ollama-laravel.model'))
        ->prompt($question)
        ->stream(true)
        ->ask();

        return Response::stream(function () use ($response) {
            Ollama::processStream($response->getBody(), function ($data) {
                echo 'data: ' . json_encode(
                    [
                        'choices' => [
                            [
                                'delta' => [
                                    'content' => $data['response']
                                ]
                            ]
                        ]
                    ]
                ) . "\n\n";
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }


}
