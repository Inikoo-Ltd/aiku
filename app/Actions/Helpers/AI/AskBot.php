<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\AI\Traits\WithAIBot;
use App\Actions\OrgAction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AskBot extends OrgAction
{
    use AsController;
    use WithAIBot;

    public function handle($q)
    {

        // $embedQ = Ollama::model(config('ollama-laravel.embedding_model'))->embeddings($q);

        // if (!isset($embedQ['embedding'])) {
        //     return ['error' => 'model not found', 'status' => 422];
        // }

        // $embeddingColumn = $this->get_embedding_size(config('llmdriver.driver'));
        // $query = Chunk::query()
        // ->orderBy('sort_order')
        // ->orderBy('section_number')
        // ->nearestNeighbors(
        //     $embeddingColumn,
        //     $embedQ['embedding'],
        //     Distance::Cosine
        // )
        // ->get()
        // ->chunk(100, function ($chunk) use ($embedQ, $embeddingColumn) {
        //     return $chunk->filter(function ($item) use ($embedQ, $embeddingColumn) {
        //         try {
        //             $embedding = json_decode($item->{$embeddingColumn}, true);
        //             $score = round($this->calculateCosineSimilarity($embedding, $embedQ['embedding']), 3);
        //         } catch (\Exception $e) {
        //             dd($e->getMessage());
        //             return false;
        //         }
        //         return $score >= 0.56;
        //     });
        // });

        // $resultMerge = $query->collapse();
        // $parentRes = collect($resultMerge)
        //     ->unique('id')
        //     ->take(4);

        // $finalRes = $this->getSiblings($parentRes);

        // $context = [];

        // foreach ($finalRes as $res) {
        //     $context[] = [
        //         'content' => $res->content,
        //     ];
        // }

        // $prompt = $this->promptTemplate(json_encode($context), $q);

        // return $response;

        $q = $this->simplePrompt($q);
        if (config('askbot-laravel.ai_provider') == 'r1') {
            return $this->askDeepseek($q);
        }

        return $this->askLlama($q);
    }

    public function asController(ActionRequest $request)
    {
        $q = $request->input('q');
        return $this->handle($q);
    }

    public $commandSignature = 'ask:bot {q}';

    public function asCommand($command): void
    {

        // generate chunk vector for all product
        // $product = Product::query()->orderBy('id')->get();

        // foreach ($product as $p) {
        //     $metadata = $p->toArray();
        //     $content = Arr::only($metadata, ['code', 'status', 'slug', 'units', 'unit' ,'name', 'price', 'description']);
        //     data_set($content, 'symbol_currency', Currency::find($metadata['currency_id'])->symbol);
        //     ChunkText::make()->handle(json_encode($content), $metadata);
        // }
        // dd('done');
        // $client = DeepSeekClient::build(apiKey:env('R1_API_KEY'), baseUrl:'https://api.deepseek.com/v3', timeout:30, clientType:'guzzle');

        // $response = $client
        // ->query($command->argument('q'))
        // ->withModel(Model)
        // ->setTemperature(1.5)
        // ->run();
        // dd(AskBotResource::collection($this->handle($command->argument('q'))));

    }
}
