<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 15:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Tasks;

use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class StoreStaffTasksFromList
{
    use AsAction;

    public const int MAX_TASKS = 100;

    /**
     * @return array<int, array{line: int, subject: string, assignee_id: int|null, department: string|null, who: string, due_at: string|null, error: string|null}>
     */
    public function parse(User $requester, string $list): array
    {
        $departments = collect(StaffTask::departments($requester->group_id));
        $rows        = [];

        foreach (preg_split('/\R/', $list) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells   = array_map('trim', str_contains($line, "\t") ? explode("\t", $line) : explode('|', $line));
            $subject = trim(preg_replace('/^\s*(?:[-*•]|\d+[.)])\s+/u', '', $cells[0]));
            $who     = $cells[1] ?? '';
            $due     = $cells[2] ?? '';

            if ($rows === [] && in_array(Str::lower($subject), ['subject', 'task', 'tasks', 'what'])) {
                continue;
            }

            $row = ['line' => $index + 1, 'subject' => $subject, 'assignee_id' => null, 'department' => null, 'who' => $requester->chatName(), 'due_at' => null, 'error' => null];

            if ($subject === '') {
                $row['error'] = __('The task is empty');
            } elseif (mb_strlen($subject) > 255) {
                $row['error'] = __('The task is longer than 255 characters');
            }

            if ($who === '') {
                $row['assignee_id'] = $requester->id;
            } elseif ($department = $departments->first(fn (array $department) => $this->matches($who, $department['value']) || $this->matches($who, $department['label']))) {
                $row['department'] = $department['value'];
                $row['who']        = $department['label'];
            } else {
                $users = User::query()
                    ->where('group_id', $requester->group_id)
                    ->where('status', true)
                    ->where(fn ($query) => $query
                        ->whereRaw('lower(username) = ?', [Str::lower($who)])
                        ->orWhereRaw('lower(nickname) = ?', [Str::lower($who)])
                        ->orWhereRaw('lower(contact_name) = ?', [Str::lower($who)]))
                    ->get();

                if ($users->count() === 1 && StaffTask::canBeAssigned($users->first())) {
                    $row['assignee_id'] = $users->first()->id;
                    $row['who']         = $users->first()->chatName();
                } else {
                    $row['who']   = $who;
                    $row['error'] ??= match (true) {
                        $users->isEmpty()   => __('No colleague or department called :who', ['who' => $who]),
                        $users->count() > 1 => __('More than one colleague called :who, use their username', ['who' => $who]),
                        default             => __('Engineers and QA get tickets, not tasks'),
                    };
                }
            }

            if ($due !== '') {
                $dueAt = $this->parseDate($due);
                if ($dueAt) {
                    $row['due_at'] = $dueAt->toDateString();
                } else {
                    $row['error'] ??= __('Cannot read the date :date', ['date' => $due]);
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function matches(string $typed, string $name): bool
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($typed))) === preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));
    }

    private function parseDate(string $date): ?Carbon
    {
        try {
            if (preg_match('/^\d{1,2}[\/.]\d{1,2}[\/.]\d{2,4}$/', $date)) {
                return Carbon::createFromFormat(strlen(preg_split('/[\/.]/', $date)[2]) === 2 ? 'd/m/y' : 'd/m/Y', str_replace('.', '/', $date))->startOfDay();
            }

            return Carbon::parse($date)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return StaffTask[]
     */
    public function handle(User $requester, string $list): array
    {
        $rows = $this->parse($requester, $list);

        $errors = collect($rows)->filter(fn (array $row) => $row['error'])->map(fn (array $row) => __('Line :line: :error', ['line' => $row['line'], 'error' => $row['error']]));
        if ($rows === []) {
            $errors->push(__('The list is empty'));
        }
        if (count($rows) > self::MAX_TASKS) {
            $errors->push(__('At most :max tasks at a time', ['max' => self::MAX_TASKS]));
        }
        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages(['list' => $errors->values()->all()]);
        }

        return DB::transaction(fn () => array_map(fn (array $row) => StoreStaffTask::make()->action($requester, array_filter([
            'subject'     => $row['subject'],
            'assignee_id' => $row['assignee_id'],
            'department'  => $row['department'],
            'due_at'      => $row['due_at'],
        ])), $rows));
    }

    public function rules(): array
    {
        return [
            'list'    => ['required', 'string', 'max:50000'],
            'preview' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        if ($request->boolean('preview')) {
            return ['data' => $this->parse($request->user(), $request->validated('list'))];
        }

        $tasks = $this->handle($request->user(), $request->validated('list'));

        return ['data' => array_map(fn (StaffTask $task) => ['id' => $task->id, 'reference' => $task->reference], $tasks)];
    }
}
