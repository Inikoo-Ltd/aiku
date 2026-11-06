<?php

/*
 * Created: Fri, 06 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\OrgAction;
use App\Actions\Tasks\SendStaffTaskBadgeUpdateToUsers;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Web\Crawl\UI\IndexSiteAuditIssuePages;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Events\BroadcastStaffTaskChanged;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlIssue;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * One staff task per website and issue type for the webmasters, with the link to the pages of
 * the latest audit that have it. Asking again posts in the open task, and the first audit that
 * no longer finds the issue closes it.
 */
class AskWebmastersToFixSiteAuditIssue extends OrgAction
{
    use WithSeoEditAuthorisation;

    public const string TASK_KIND = 'site_audit_issue';

    public function handle(Website $website, CrawlIssueTypeEnum $issueType, User $requester): StaffTask
    {
        $audit = IndexSiteAuditIssuePages::make()->latestAudit($website);
        $pages = $audit ? CrawlIssue::where('crawl_id', $audit->id)->where('type', $issueType->value)->count() : 0;

        if ($pages === 0) {
            throw ValidationException::withMessages(['issue_type' => __('The latest audit found no page with this issue.')]);
        }

        $label    = CrawlIssueTypeEnum::labels()[$issueType->value];
        $openTask = self::openTaskFor($website, $issueType);

        if ($openTask) {
            SendStaffMessage::run($openTask->conversation, $requester, [
                'body' => trans_choice(':user asked again: :count page still has this issue.|:user asked again: :count pages still have this issue.', $pages, ['user' => $requester->chatName()]),
            ]);
            BroadcastStaffTaskChanged::dispatch($openTask);
            SendStaffTaskBadgeUpdateToUsers::run($openTask->involvedUserIds());

            return $openTask;
        }

        return StoreStaffTask::run($requester, [
            'subject'     => __('Fix ":issue" on :domain', ['issue' => $label, 'domain' => $website->domain]),
            'description' => trans_choice('The site audit found :count page with ":issue". :description|The site audit found :count pages with ":issue". :description', $pages, [
                'count'       => $pages,
                'issue'       => $label,
                'description' => CrawlIssueTypeEnum::descriptions()[$issueType->value],
            ])."\n\n".__('The next audit that finds none closes this task.')."\n\n".route('grp.org.shops.show.seo.site_audit.issue', [$website->organisation->slug, $website->shop->slug, $issueType->value]),
            'data'        => ['kind' => self::TASK_KIND, 'website_id' => $website->id, 'issue_type' => $issueType->value, 'shop_id' => $website->shop_id, 'organisation_id' => $website->organisation_id],
            'department'  => 'webmaster',
        ]);
    }

    /**
     * Closes the open tasks of the website whose issue the finished audit no longer found.
     */
    public static function closeFixed(Crawl $audit): void
    {
        $foundTypes = DB::table('crawl_issues')->where('crawl_id', $audit->id)->distinct()->pluck('type')->all();

        StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->website_id', $audit->website_id)
            ->get()
            ->reject(fn (StaffTask $task) => in_array($task->data['issue_type'] ?? null, $foundTypes, true))
            ->each(function (StaffTask $task) {
                $task->update(['status' => StaffTaskStatusEnum::DONE, 'closed_at' => now()]);

                BroadcastStaffTaskChanged::dispatch($task);
                SendStaffTaskBadgeUpdateToUsers::run($task->involvedUserIds());
            });
    }

    private static function openTaskFor(Website $website, CrawlIssueTypeEnum $issueType): ?StaffTask
    {
        return StaffTask::open()
            ->where('data->kind', self::TASK_KIND)
            ->where('data->website_id', $website->id)
            ->where('data->issue_type', $issueType->value)
            ->first();
    }

    public function asController(Website $website, string $issueType, ActionRequest $request): StaffTask
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website, CrawlIssueTypeEnum::tryFrom($issueType) ?? abort(404), $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
