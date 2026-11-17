<?php

namespace App\Actions\Helpers\TicketProject;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketProjectHealthEnum;
use App\Models\Helpers\TicketProjectUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketProjectUpdate extends OrgAction
{
    /**
     * @param array{body: string, health?: string|null} $modelData
     */
    public function handle(TicketProjectUpdate $update, array $modelData): TicketProjectUpdate
    {
        $update->update([
            'body'   => trim($modelData['body']),
            'health' => array_key_exists('health', $modelData) ? $modelData['health'] : $update->health,
        ]);

        return $update;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('ticketProjectUpdate')->canBeEditedBy($request->user());
    }

    public function rules(): array
    {
        return [
            'body'   => ['required', 'string', 'max:20000'],
            'health' => ['sometimes', 'nullable', Rule::enum(TicketProjectHealthEnum::class)],
        ];
    }

    public function asController(TicketProjectUpdate $ticketProjectUpdate, ActionRequest $request): TicketProjectUpdate
    {
        $this->initialisationFromGroup($ticketProjectUpdate->project->group, $request);

        return $this->handle($ticketProjectUpdate, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
