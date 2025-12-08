<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

return [
    'driver' => env('LLM_DRIVER', 'ollama'),

    'chunking' => [
        'default_size' => env('CHUNK_SIZE', 600),
    ],
    'embedding_sizes' => [
        'mock' => 3072,
        'text-embedding-3-large' => 3072,
        'text-embedding-3-medium' => 768,
        'text-embedding-3-small' => 384,
        'ollama' => 4096,
        'llama2' => 4096,
        'llama3' => 4096,
        'mistral' => 4096,
        'mxbai-embed-large' => 1024,
        'nomic-embed-text' => 768,
    ],

    'drivers' => [
        'mock' => [
            'models' => [
                'completion_model' => 'mock',
                'embedding_model' => 'mock',
            ],
        ],
        'ollama' => [
            'feature_flags' => [
                'functions' => env('OLLAMA_FUNCTIONS', false),
            ],
            'api_key' => 'ollama',
            'api_url' => env('OLLAMA_API_URL', 'http://10.0.0.100:11435/api/'),
            'models' => [
                // @see https://github.com/ollama/ollama/blob/main/docs/openai.md
                'completion_model' => env('OLLAMA_COMPLETION_MODEL', 'llama3.2:3b'),
                'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
                'chat_output_model' => env('OLLAMA_COMPLETION_MODEL', 'llama3.2:3b'), // this is good to use other systems for better repsonses to people in chat
            ],
        ],
    ],
];
