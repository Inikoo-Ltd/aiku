<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\DataForSeo;

use App\Models\Web\SeoApiRequest;
use App\Models\Web\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every DataForSEO call goes through here, so each one is logged in `seo_api_requests` with the cost
 * DataForSEO reports, and no call is made once the month's spend reaches the budget in
 * `services.dataforseo.monthly_budget`.
 */
class DataForSeoClient
{
    public const string PROVIDER = 'dataforseo';

    private const string BASE_URL = 'https://api.dataforseo.com/v3/';

    private const int SUCCESS = 20000;

    private const int TASK_CREATED = 20100;

    private function __construct(private readonly string $login, private readonly string $password)
    {
    }

    public static function make(): ?self
    {
        $login    = config('services.dataforseo.login');
        $password = config('services.dataforseo.password');

        return $login && $password ? new self($login, $password) : null;
    }

    public static function monthSpend(): float
    {
        return (float) SeoApiRequest::query()
            ->where('provider', self::PROVIDER)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost');
    }

    public static function monthlyBudget(): float
    {
        return (float) config('services.dataforseo.monthly_budget');
    }

    /**
     * The result of a single live task.
     *
     * @return array<int, array>
     * @throws DataForSeoException
     */
    public function live(string $endpoint, array $task, ?Website $website = null): array
    {
        $tasks = $this->send('post', $endpoint, [$task], $website, true);

        return $this->taskResult($tasks[0] ?? []);
    }

    /**
     * Queues tasks and returns them, each with the id to collect its result by, in the order sent.
     *
     * @param  array<int, array>  $tasks
     * @return array<int, array>
     * @throws DataForSeoException
     */
    public function postTasks(string $endpoint, array $tasks, ?Website $website = null): array
    {
        return $this->send('post', $endpoint, $tasks, $website, true);
    }

    /**
     * Reads what is already paid for or free (task results, ready lists, location lists), so it is not
     * stopped by the monthly budget.
     *
     * @return array<int, array>
     * @throws DataForSeoException
     */
    public function get(string $endpoint, ?Website $website = null): array
    {
        $tasks = $this->send('get', $endpoint, null, $website, false);

        return $this->taskResult($tasks[0] ?? []);
    }

    /**
     * @return array<int, array>
     * @throws DataForSeoException
     */
    public function taskResult(array $task): array
    {
        $statusCode = (int) Arr::get($task, 'status_code');

        if ($statusCode !== self::SUCCESS) {
            throw new DataForSeoException((string) Arr::get($task, 'status_message', __('DataForSEO returned no result.')), $statusCode ?: null);
        }

        return Arr::get($task, 'result') ?? [];
    }

    public static function isTaskCreated(array $task): bool
    {
        return (int) Arr::get($task, 'status_code') === self::TASK_CREATED;
    }

    /**
     * @return array<int, array>
     * @throws DataForSeoException
     */
    private function send(string $method, string $endpoint, ?array $tasks, ?Website $website, bool $isBillable): array
    {
        if ($isBillable && self::monthSpend() >= self::monthlyBudget()) {
            throw new DataForSeoException(__('The DataForSEO budget for this month (:budget USD) has been reached.', ['budget' => self::monthlyBudget()]), DataForSeoException::BUDGET_REACHED);
        }

        $startedAt = hrtime(true);

        try {
            $response = retry(3, fn () => $method === 'get' ? $this->request()->get($endpoint) : $this->request()->post($endpoint, $tasks), 2000, fn (Throwable $e) => $e instanceof ConnectionException);
        } catch (Throwable $e) {
            $this->log($endpoint, $website, false, 0, null, $startedAt, $e->getMessage());

            throw new DataForSeoException($e->getMessage());
        }

        $statusCode = (int) $response->json('status_code');
        $cost       = $response->json('cost');
        $results    = collect($response->json('tasks') ?? []);
        $rows       = (int) $results->sum(fn (array $task) => collect(Arr::get($task, 'result') ?? [])->sum(fn ($result) => is_array($result) && array_key_exists('items_count', $result) ? (int) $result['items_count'] : 1));

        if ($statusCode !== self::SUCCESS) {
            $message = (string) ($response->json('status_message') ?: $response->reason());

            $this->log($endpoint, $website, false, $rows, $cost, $startedAt, "$statusCode $message");

            throw new DataForSeoException($message, $statusCode ?: null);
        }

        $taskError = $results->first(fn (array $task) => !in_array((int) Arr::get($task, 'status_code'), [self::SUCCESS, self::TASK_CREATED], true));

        $this->log($endpoint, $website, $taskError === null, $rows, $cost, $startedAt, $taskError ? Arr::get($taskError, 'status_code').' '.Arr::get($taskError, 'status_message') : null);

        return $results->all();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withBasicAuth($this->login, $this->password)
            ->acceptJson()
            ->timeout(120);
    }

    private function log(string $endpoint, ?Website $website, bool $isSuccess, int $rows, mixed $cost, int $startedAt, ?string $error = null): void
    {
        SeoApiRequest::create([
            'provider'    => self::PROVIDER,
            'endpoint'    => Str::limit(preg_replace('/\/[0-9a-f-]{36}$/', '', $endpoint), 64, ''),
            'website_id'  => $website?->id,
            'is_success'  => $isSuccess,
            'rows'        => $rows,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
            'cost'        => is_numeric($cost) ? $cost : null,
            'error'       => $error ? Str::limit($error, 2000) : null,
        ]);
    }
}
