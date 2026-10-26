<?php

namespace App\Actions\HumanResources\CalendarEvent;

use App\Actions\OrgAction;
use App\Models\HumanResources\CalendarEvent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreCalendarEvent extends OrgAction
{
    use WithCalendarEventRules;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, array $modelData, ?User $createdBy = null): CalendarEvent
    {
        return DB::transaction(function () use ($organisation, $modelData, $createdBy) {
            /** @var CalendarEvent $calendarEvent */
            $calendarEvent = $organisation->calendarEvents()->create(array_merge(
                $this->eventAttributes($modelData),
                [
                    'group_id'           => $organisation->group_id,
                    'created_by_user_id' => $createdBy?->id,
                ]
            ));

            $calendarEvent->participants()->sync($modelData['participants'] ?? []);

            return $calendarEvent->refresh();
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canEditCalendar($request->user(), $this->organisation);
    }

    public function rules(): array
    {
        return $this->calendarEventRules();
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): CalendarEvent
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, array $modelData, ?User $createdBy = null): CalendarEvent
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData, $createdBy);
    }
}
