<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteNotFoundPath;

use App\Actions\Web\WebsiteVisitor\IsBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteNotFoundHit
{
    use AsAction;

    private const int MAX_PATH_LENGTH = 2048;

    public string $jobQueue = 'analytics';

    public int $jobTries = 1;

    public function handle(int $websiteId, string $path, ?string $referrer, string $userAgent): void
    {
        if ($userAgent === '' || IsBot::run($userAgent)) {
            return;
        }

        $path = '/'.trim(Str::limit($path, self::MAX_PATH_LENGTH, ''), '/');
        $now  = now();

        DB::statement(
            'INSERT INTO website_not_found_paths (website_id, path, path_hash, last_segment, hits, last_referrer, is_ignored, first_seen_at, last_seen_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, false, ?, ?, ?, ?)
            ON CONFLICT (website_id, path_hash) DO UPDATE SET
                hits = website_not_found_paths.hits + 1,
                last_referrer = COALESCE(EXCLUDED.last_referrer, website_not_found_paths.last_referrer),
                last_seen_at = EXCLUDED.last_seen_at,
                updated_at = EXCLUDED.updated_at',
            [
                $websiteId,
                $path,
                md5($path),
                Str::limit(Str::afterLast($path, '/'), 255, ''),
                $referrer ? Str::limit($referrer, self::MAX_PATH_LENGTH, '') : null,
                $now,
                $now,
                $now,
                $now,
            ]
        );
    }
}
