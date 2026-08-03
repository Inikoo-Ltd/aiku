<?php

namespace App\Actions\Web\Website\Analytics;

use App\Events\Web\WebsiteVisitorCountUpdated;
use App\Events\Web\WebsiteVisitorHit;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\Concerns\AsAction;

class TrackWebsiteVisitorActivity
{
    use AsAction;

    public function handle(Website $website, string $sessionId, array $metadata): void
    {
        $now = time();
        $expiry = 300; // 5 minutes

        $isLoggedIn = $metadata['logged_in'] ?? false;

        $keyLoggedIn = "website:{$website->id}:visitors:logged_in";
        $keyLoggedOut = "website:{$website->id}:visitors:logged_out";

        // Remove from both sets to be safe (if login status changed)
        Redis::zrem($keyLoggedIn, $sessionId);
        Redis::zrem($keyLoggedOut, $sessionId);

        // Add to the correct set
        $activeKey = $isLoggedIn ? $keyLoggedIn : $keyLoggedOut;
        Redis::zadd($activeKey, $now, $sessionId);

        // Store metadata
        $dataKey = "website:{$website->id}:visitor:{$sessionId}:data";
        Redis::hmset($dataKey, $metadata);
        Redis::expire($dataKey, $expiry);

        // Clean up old sessions
        Redis::zremrangebyscore($keyLoggedIn, 0, $now - $expiry);
        Redis::zremrangebyscore($keyLoggedOut, 0, $now - $expiry);

        // Get counts
        $loggedInCount = Redis::zcard($keyLoggedIn);
        $loggedOutCount = Redis::zcard($keyLoggedOut);

        // Broadcast counts
        WebsiteVisitorCountUpdated::dispatch($website, $loggedInCount, $loggedOutCount);

        // Broadcast hit
        WebsiteVisitorHit::dispatch($website, array_merge(['session_id' => $sessionId], $metadata));
    }

    public function getCounts(Website $website): array
    {
        $now = time();
        $expiry = 300; // 5 minutes

        $keyLoggedIn = "website:{$website->id}:visitors:logged_in";
        $keyLoggedOut = "website:{$website->id}:visitors:logged_out";

        // Clean up old sessions
        Redis::zremrangebyscore($keyLoggedIn, 0, $now - $expiry);
        Redis::zremrangebyscore($keyLoggedOut, 0, $now - $expiry);

        return [
            'logged_in'  => Redis::zcard($keyLoggedIn),
            'logged_out' => Redis::zcard($keyLoggedOut),
        ];
    }

    public function getActiveVisitors(Website $website): array
    {
        $now = time();
        $expiry = 300;

        $keyLoggedIn = "website:{$website->id}:visitors:logged_in";
        $keyLoggedOut = "website:{$website->id}:visitors:logged_out";

        Redis::zremrangebyscore($keyLoggedIn, 0, $now - $expiry);
        Redis::zremrangebyscore($keyLoggedOut, 0, $now - $expiry);

        $sessions = array_merge(
            Redis::zrange($keyLoggedIn, 0, -1),
            Redis::zrange($keyLoggedOut, 0, -1)
        );

        $visitors = [];
        foreach ($sessions as $sessionId) {
            $dataKey = "website:{$website->id}:visitor:{$sessionId}:data";
            $data = Redis::hgetall($dataKey);
            if ($data) {
                $visitors[] = array_merge(['session_id' => $sessionId], $data);
            }
        }

        return $visitors;
    }
}
