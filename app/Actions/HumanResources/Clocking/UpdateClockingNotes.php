<?php

namespace App\Actions\HumanResources\Clocking;

use App\Models\HumanResources\Clocking;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateClockingNotes
{
    use AsAction;

    public function handle(Clocking $clocking, ?string $notes): Clocking
    {
        $clocking->update([
            'notes' => $notes,
        ]);

        return $clocking;
    }

    public function asController(Clocking $clocking, ActionRequest $request)
    {
        $this->handle($clocking, $request->validated('notes'));

        return response()->json([
            'success' => true,
            'message' => __('Notes updated successfully.'),
            'clocking' => $clocking
        ]);
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
