<?php


namespace App\Http\Middleware;

use App\Actions\CRM\CustomerAcquisitionSource\StoreCustomerAcquisitionSource;
use App\Models\CRM\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CaptureAcquisitionSource
{
    /**
     * Handle an incoming request to capture acquisition tracking data
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $allowedRoutes = [
            'retina.register',
            'retina.register_standalone',
            'retina.register_from_google',
        ];

        $routeName = $request->route() ? $request->route()->getName() : null;

        if ($routeName && in_array($routeName, $allowedRoutes)) {
            if ($response->getStatusCode() === 200 && $request->isMethod('GET')) {
                $this->captureTrackingData($request);
            }
        }

        return $response;
    }

    private function captureTrackingData(Request $request): void
    {
        // Check if there are any tracking parameters
        $trackingData = StoreCustomerAcquisitionSource::extractTrackingDataFromRequest($request);

        if (empty($trackingData)) {
            return;
        }


        // Store in session for later when customer registers/logs in
        $request->session()->put('acquisition_tracking_data', $trackingData);
    }
}
