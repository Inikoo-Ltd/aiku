<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use App\Actions\Search\WithTypesenseApi;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsCommand;

class IndexNotesInTypesense
{
    use AsCommand;
    use WithTypesenseApi;

    public string $commandSignature = 'aiku-public:index-notes';
    public string $commandDescription = 'Index the engineering notes and documentation into Typesense';

    public const string COLLECTION = 'aiku_public_notes_v2';

    public function handle(): int
    {
        $this->ensureCollection();

        $count = 0;
        foreach (['blog' => 'aiku-public.blog.show', 'docs' => 'aiku-public.docs.show'] as $section => $routeName) {
            foreach (BlogPosts::all($section) as $post) {
                $this->indexPost($post, $section, route($routeName, $post['slug']));
                $count++;
            }
        }

        return $count;
    }

    public function asCommand(Command $command): int
    {
        $count = $this->handle();
        $command->info("Indexed {$count} engineering notes into Typesense.");

        return 0;
    }

    protected function indexPost(array $post, string $section, string $url): void
    {
        $this->typesenseClient()->post(
            $this->typesenseUrl().'/collections/'.self::COLLECTION.'/documents?action=upsert',
            [
                'id'      => $section.':'.$post['slug'],
                'slug'    => $post['slug'],
                'section' => $section,
                'url'     => $url,
                'title'   => $post['title'],
                'summary' => $post['summary'],
                'body'    => $post['body'],
                'tags'    => $post['tags'],
                'date'    => $post['date']->timestamp,
            ]
        );
    }

    protected function ensureCollection(): void
    {
        $exists = $this->typesenseClient()->get($this->typesenseUrl().'/collections/'.self::COLLECTION)->successful();
        if ($exists) {
            return;
        }

        $this->typesenseClient()->post($this->typesenseUrl().'/collections', [
            'name'                  => self::COLLECTION,
            'fields'                => [
                ['name' => 'slug', 'type' => 'string'],
                ['name' => 'section', 'type' => 'string', 'facet' => true],
                ['name' => 'url', 'type' => 'string'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'summary', 'type' => 'string'],
                ['name' => 'body', 'type' => 'string'],
                ['name' => 'tags', 'type' => 'string[]', 'facet' => true],
                ['name' => 'date', 'type' => 'int64'],
            ],
            'default_sorting_field' => 'date',
        ]);
    }
}
