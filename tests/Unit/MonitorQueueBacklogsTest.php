<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 16:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\DevOps\MonitorQueueBacklogs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Http::fake();
    $this->redis = Redis::connection('horizon');
    $this->redis->del('queues:phantom:delayed', 'queues:default:delayed');
});

afterEach(function () {
    $this->redis->del('queues:phantom:delayed', 'queues:default:delayed');
});

test('a queue with jobs but no supervisor is flagged', function () {
    $this->redis->zadd('queues:phantom:delayed', time(), 'job-payload');

    $issues = MonitorQueueBacklogs::run();

    expect(implode(' ', $issues))->toContain('phantom')
        ->toContain('NO Horizon supervisor');
});

test('a supervised queue under the threshold is not flagged', function () {
    $this->redis->zadd('queues:default:delayed', time(), 'job-payload');

    $issues = MonitorQueueBacklogs::run();

    expect(implode(' ', $issues))->not->toContain('queues:default:delayed');
});
