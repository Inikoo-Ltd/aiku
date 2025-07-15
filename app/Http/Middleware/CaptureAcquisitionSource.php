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

        // If user is already authenticated and is a customer, store immediately
        if (Auth::check() && $this->isCustomerUser($request)) {
            $customer = $this->getCustomerFromRequest($request);
            if ($customer) {
                StoreCustomerAcquisitionSource::fromRequest($customer, $request);
                $request->session()->forget('acquisition_tracking_data');
            }
        }
    }

    /**
     * Check if the authenticated user is a customer
     */
    private function isCustomerUser(Request $request): bool
    {
        $user = Auth::user();

        // This would depend on your authentication system
        // You might have different user types or ways to identify customers
        return $user && method_exists($user, 'customer') && $user->customer;
    }

    /**
     * Get customer from authenticated user
     */
    private function getCustomerFromRequest(Request $request): ?Customer
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // This would depend on your user-customer relationship
        if (method_exists($user, 'customer')) {
            return $user->customer;
        }

        return null;
    }
}
