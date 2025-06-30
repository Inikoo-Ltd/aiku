<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 01-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

use function Amp\async;
use function Amp\Future\await;

class GetWebsiteCloudflareAnalytics extends OrgAction
{
    use AsAction;
    use WithNoStrictRules;
    use WithCloudflareQueryGraphql;

    private Website $website;
    /**
     * @throws \Throwable
     */
    public function handle(Website $website, array $modelData): array
    {
        /** @var User $user */
        $user = auth()->user();


        $groupSettings = $website->group->settings;
        $dataWebsite = $website->data;

        $apiToken = Arr::get($groupSettings, 'cloudflare.apiToken');
        $zoneTag = Arr::get($dataWebsite, "cloudflare.zoneTag");
        $accountTag = Arr::get($dataWebsite, "cloudflare.accountTag");
        $siteTag = Arr::get($dataWebsite, "cloudflare.siteTag");

        if (!$apiToken || !$zoneTag || !$accountTag || !$siteTag) {
            return [];
        }

        $cacheKey = "ui_state-user:$user->id;website:$website->id;filter-analytics:" . md5(json_encode($modelData));

        $cachedData = cache()->get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }


        data_set($modelData, "zoneTag", $zoneTag);
        data_set($modelData, "accountTag", $accountTag);
        data_set($modelData, "siteTag", $siteTag);
        data_set($modelData, "apiToken", $apiToken);

        $partialShowTopNs = Arr::get($modelData, 'partialShowTopNs');
        if ($partialShowTopNs) {
            return $this->partialHandle($partialShowTopNs, $modelData);
        }


        $showTopNs = Arr::get($modelData, 'showTopNs') ?? 'visits';
        $rumAnalyticsTopNsPromise = async(fn () => []);
        $rumAnalyticsTimeSeriesPromise = async(fn () => []);
        $rumWebVitalsPromise = async(fn () => []);
        if ($showTopNs) {
            switch ($showTopNs) {
                case 'performance':
                    $rumAnalyticsTopNsPromise = async(fn () => $this->getRumPerfAnalyticsTopNs($modelData));
                    break;
                case 'webVitals':
                    $rumAnalyticsTopNsPromise = async(fn () => $this->getRumWebVitalsTop($modelData));
                    $section = ['lcp', 'fid', 'inp' ,'cls'];
                    $rumWebVitalsPromise = [];
                    foreach ($section as $key) {
                        data_set($modelData, 'section', $key);
                        $rumWebVitalsPromise[$key] = async(fn () => $this->getRumWebVitals($modelData));
                    }
                    break;
                case 'pageViews':
                case 'visits':
                    $rumAnalyticsTopNsPromise      = async(fn () => $this->getRumAnalyticsTopNs($modelData));
                    $rumAnalyticsTimeSeriesPromise = async(fn () => $this->getRumAnalyticsTimeseries($modelData));
                    break;
                default:
                    //
            }
        }

        $rumSparklinePromise = async(fn () => $this->getRumSparkline($modelData));
        $zonePromise = async(fn () => $this->getZone($modelData));

        [$rumAnalyticsTopNs, $rumSparkline, $rumAnalyticsTimeSeries, $zone] = await([
            $rumAnalyticsTopNsPromise,
            $rumSparklinePromise,
            $rumAnalyticsTimeSeriesPromise,
            $zonePromise
        ]);
        $rumWebVitals = [];
        foreach ($rumWebVitalsPromise as $key => $promise) {
            [$webVital] = await([$promise]);
            $rumWebVitals[$key] = $webVital;
        }

        $data = array_filter([
            'rumAnalyticsTopNs' => $rumAnalyticsTopNs,
            'rumSparkline' => $rumSparkline,
            'rumAnalyticsTimeSeries' => $rumAnalyticsTimeSeries,
            'rumWebVitals' => $rumWebVitals,
            'zone' => $zone,
        ]);

        cache()->put($cacheKey, $data, now()->addMinutes(5));

        return $data;
    }

    private function partialHandle($partialShowTopNs, $modelData): array
    {
        $partialFilterTimeSeries = Arr::get($modelData, 'partialFilterTimeSeries');
        $partialFilterPerfAnalytics = Arr::get($modelData, 'partialFilterPerfAnalytics');
        $partialTimeSeriesData = Arr::get($modelData, 'partialTimeSeriesData');
        data_set($modelData, "showTopNs", $partialShowTopNs);
        data_forget($modelData, 'partialShowTopNs');
        switch ($partialShowTopNs) {
            case 'performance':
                data_set($modelData, 'filter', $partialFilterPerfAnalytics);
                return $this->getRumPerfAnalyticsTopNs($modelData);
                // case 'webVitals':
                //     $rumAnalyticsTopNsPromise = async(fn () => $this->getRumWebVitalsTop($modelData));
                //     break;
            case 'pageViews':
            case 'visits':
                $rumAnalyticsTopNsPromise = async(fn () => $this->getRumAnalyticsTopNs($modelData));
                data_set($modelData, 'filterData', $partialTimeSeriesData);
                data_set($modelData, 'filter', $partialFilterTimeSeries);
                $rumAnalyticsTimeSeriesPromise = async(fn () => $this->getRumAnalyticsTimeseries($modelData));
                [$rumAnalyticsTopNs, $rumAnalyticsTimeSeries] = await([$rumAnalyticsTopNsPromise, $rumAnalyticsTimeSeriesPromise]);
                return [
                    'rumAnalyticsTopNs' => $rumAnalyticsTopNs,
                    'rumAnalyticsTimeSeries' => $rumAnalyticsTimeSeries,
                ];
            default:
                return [];
        }
    }

    private function isIso8601($dateString): bool
    {
        try {
            $date = Carbon::parse($dateString);
            return $date->toIso8601String() === $dateString;
        } catch (Exception) {
            return false;
        }
    }

    private function isDate($dateString): bool
    {
        try {
            $date = Carbon::parse($dateString);
            return $date->toDateString() === $dateString;
        } catch (Exception) {
            return false;
        }
    }

    public function rules(): array
    {
        return [
            'since' => ['sometimes'],
            'until' => ['sometimes'],
            'showTopNs' => ['sometimes', 'string', 'in:visits,pageViews,performance,webVitals'],
            'partialShowTopNs' => ['sometimes', 'string', 'in:visits,pageViews,performance,webVitals'],
            'partialFilterTimeSeries' => [
                'sometimes',
                'string',
                'required_with:partialShowTopNs',
                'in:all,referer,host,country,path,browser,os,deviceType'
            ],
            'partialTimeSeriesData' => [
                'sometimes',
                'string',
                'required_with:partialFilterTimeSeries'
            ],
            'partialFilterPerfAnalytics' => [
                'sometimes',
                'string',
                'required_with:partialShowTopNs',
                'in:p50,p75,p90,p99,avg'
            ],
            'partialWebVitals' => [
                'sometimes',
                'string',
                'required_with:partialShowTopNs',
                'in:lcp,inp,fid,cls'
            ],
            'partialWebVitalsData' => [
                'sometimes',
                'string',
                'in:url,browser,os,country,element'
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $since = $this->since;
            $until = $this->until;
            if (isset($since) && !$this->isIso8601($since) && !$this->isDate($since)) {
                $validator->errors()->add('since', 'The since field must be a valid ISO 8601 or YYYY-MM-DD date.');
            }

            if (isset($until) && !$this->isIso8601($until) && !$this->isDate($until)) {
                $validator->errors()->add('until', 'The until field must be a valid ISO 8601 or YYYY-MM-DD date.');
            }
        });
    }

    /**
     * @throws \Throwable
     */
    public function action(Website $website, array $modelData, bool $strict = true): array
    {

        $this->strict   = $strict;
        $this->asAction = true;
        $this->website  = $website;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($website, $validatedData);
    }

}
