<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AICreditException extends Exception
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if (!$request->inertia() && $request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['message' => $this->getMessage()]);
    }
}
