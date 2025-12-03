<?php

/*
 * author Louis Perez
 * created on 01-12-2025-16h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\WebBlockType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteWebBlockType
{
    use WithActionUpdate;
    use WithRepairWebpages;

    protected function handle(String $block_name): void
    {
        // Check if used in websites or webpages
        $exists = DB::table('websites')
                    ->where('published_layout->family->code', $block_name)
                    ->exists() ||
                DB::table('webpages')
                    ->whereJsonContains('published_layout->web_blocks', [
                        ['type' => $block_name]
                    ])
                    ->exists();
        // Delete if not used
        if (!$exists) {
            WebBlockType::where('code', $block_name)->delete();
        }

    }

    public string $commandSignature = 'delete:remove-unused-web-block-types {block_name}';

    public function asCommand(Command $command): void
    {
        if ($command->argument('block_name')) {
            $this->handle($command->argument('block_name'));
        }
    }

}
