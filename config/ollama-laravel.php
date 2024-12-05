<?php

// Config for Cloudstudio/Ollama

return [
    'model' => env('OLLAMA_MODEL', 'llama3.2:1b'),
    'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
    'url' => env('OLLAMA_URL', 'http://10.0.0.100:11435'),
    'default_prompt' => env('OLLAMA_DEFAULT_PROMPT', 'Hello, how can I assist you today?'),
    'connection' => [
        'timeout' => env('OLLAMA_CONNECTION_TIMEOUT', 300),
    ],
];
