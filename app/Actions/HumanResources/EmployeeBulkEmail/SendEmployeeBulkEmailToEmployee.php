<?php

namespace App\Actions\HumanResources\EmployeeBulkEmail;

use App\Models\HumanResources\EmployeeBulkEmail;
use App\Notifications\EmployeeBulkEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class SendEmployeeBulkEmailToEmployee
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function handle(EmployeeBulkEmail $employeeBulkEmail, string $email, string $name): void
    {
        Notification::route('mail', [$email => $name])->notifyNow(new EmployeeBulkEmailNotification(
            $employeeBulkEmail->subject,
            $employeeBulkEmail->body,
            $name,
            $employeeBulkEmail->organisation->name,
            $employeeBulkEmail->attachments ?? [],
            $employeeBulkEmail->sender?->email
        ));

        self::markRecipientDone($employeeBulkEmail);
    }

    public function jobFailed(Throwable $exception, EmployeeBulkEmail $employeeBulkEmail): void
    {
        self::markRecipientDone($employeeBulkEmail);
    }

    public static function markRecipientDone(EmployeeBulkEmail $employeeBulkEmail): void
    {
        $pending = DB::selectOne(
            'UPDATE employee_bulk_emails SET number_pending = GREATEST(number_pending - 1, 0) WHERE id = ? RETURNING number_pending',
            [$employeeBulkEmail->id]
        )?->number_pending;

        if ($pending === 0) {
            self::deleteAttachments($employeeBulkEmail);
        }
    }

    public static function deleteAttachments(EmployeeBulkEmail $employeeBulkEmail): void
    {
        foreach (collect($employeeBulkEmail->attachments ?? [])->map(fn (array $attachment): string => dirname($attachment['path']))->unique() as $directory) {
            Storage::disk('local')->deleteDirectory($directory);
        }
    }
}
