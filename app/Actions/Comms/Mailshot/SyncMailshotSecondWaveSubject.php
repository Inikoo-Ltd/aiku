<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 10:50:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Models\Comms\Mailshot;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncMailshotSecondWaveSubject
{
    use AsAction;

    public const string SUFFIX = ' (2nd)';

    public function handle(Mailshot $parentMailshot): void
    {
        $secondWave = $parentMailshot->secondWave;

        if (!$secondWave || $this->isSubjectEditedByUser($secondWave)) {
            return;
        }

        $subject = self::subjectFor($parentMailshot);

        if ($secondWave->subject === $subject) {
            return;
        }

        $secondWave->update(['subject' => $subject]);
        $secondWave->email?->update(['subject' => $subject]);
    }

    public static function subjectFor(Mailshot $parentMailshot): string
    {
        return Str::limit($parentMailshot->subject, 255 - strlen(self::SUFFIX), '').self::SUFFIX;
    }

    public function isSubjectEditedByUser(Mailshot $secondWave): bool
    {
        return (bool) Arr::get($secondWave->data, 'subject_edited_by_user', false);
    }

    /**
     * A wave that is not hand-edited takes its subject from the parent, so the parent's subject is what
     * must not be a placeholder; a hand-edited wave is judged on its own subject.
     */
    public function assertSendable(Mailshot $secondWave): void
    {
        $secondWave->assertSubjectIsNotDefault();

        if (!$this->isSubjectEditedByUser($secondWave)) {
            $secondWave->parentMailshot?->assertSubjectIsNotDefault();
        }
    }
}
