<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\AI\Traits\WithAIBot;
use App\Actions\OrgAction;
use App\Models\Helpers\Chunk;
use Ollama;

class ChunkText extends OrgAction
{
    use WithAIBot;

    public function handle(string $text, array $metadata = [])
    {
        $page_number = 1;
        $chunked_chunks = TextChunker::make()->handle($text);
        foreach ($chunked_chunks as $chunkSection => $chunkContent) {

            try {
                $guid = md5($chunkContent);
                $chunk = Chunk::updateOrCreate(
                    [
                        'guid' => $guid,
                    ],
                    [
                        'section_number' => $chunkSection,
                        'content' => $chunkContent,
                        'sort_order' => $page_number,
                        'metadata' => $metadata,
                    ]
                );

                $embedding_column = $this->getEmbeddingSize(config('llmdriver.driver'));

                $chunk->update([
                    $embedding_column => Ollama::model(config('ollama-laravel.embedding_model'))->embeddings($chunkContent)['embedding'],
                ]);

            } catch (\Exception $e) {
                dd($e->getMessage());
            }

        }
    }
}
