<?php

namespace App\Actions\HumanResources\CalendarEvent;

use App\Actions\OrgAction;
use App\Models\HumanResources\CalendarEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateCalendarEvent extends OrgAction
{
    use WithCalendarEventRules;

    /**
     * @throws \Throwable
     */
    public function handle(CalendarEvent $calendarEvent, array $modelData): CalendarEvent
    {
        return DB::transaction(function () use ($calendarEvent, $modelData) {
            $calendarEvent->update($this->eventAttributes($modelData));

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
    public function asController(CalendarEvent $calendarEvent, ActionRequest $request): CalendarEvent
    {
        $this->calendarEvent = $calendarEvent;
        $this->initialisation($calendarEvent->organisation, $request);

        return $this->handle($calendarEvent, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    /**
     * @throws \Throwable
     */
    public function action(CalendarEvent $calendarEvent, array $modelData): CalendarEvent
    {
        $this->asAction      = true;
        $this->calendarEvent = $calendarEvent;
        $this->initialisation($calendarEvent->organisation, $modelData);

        return $this->handle($calendarEvent, $this->validatedData);
    }
}
