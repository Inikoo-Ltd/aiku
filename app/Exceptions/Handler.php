<?php

namespace App\Exceptions;

use App\Actions\Retina\UI\GetRetinaFirstLoadProps;
use App\Actions\UI\Grp\GetFirstLoadProps;
use App\Http\Resources\UI\LoggedUserResource;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;
use Tightenco\Ziggy\Ziggy;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }

    protected function loadErrorMiddleware($request, $callback)
    {
        $middleware = (\Route::getMiddlewareGroups()['web_errors']);

        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then($callback);
    }

    public function render($request, Throwable $e): Response|JsonResponse|\Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $response = parent::render($request, $e);


        if (!app()->environment(['local', 'testing'])
            && in_array($response->getStatusCode(), [500, 503, 404, 403, 422])
            && !(!$request->inertia() && $request->expectsJson())
        ) {
            $app = $this->getApp();


            if ($app == 'aiku-public' || $app == 'iris') {
                return $this->renderErrorForLogOutWebpages($app, $request, $e, $response);
            }


            return $this->loadErrorMiddleware($request, function ($request) use ($e, $response, $app) {
                if (Auth::check()) {
                    $route = $request->route();
                    if ($route && (str_starts_with($route->getName(), 'grp.models') || str_starts_with($route->getName(), 'retina.models'))) {
                        return back()->withErrors([
                            'error_in_models' => $response->getStatusCode().': '.$e->getMessage()
                        ]);
                    }


                    if ($e instanceof ModelNotFoundException) {
                        if (Str::startsWith($request->route()->getName(), 'grp.org')) {
                            $fallbackPlaceholder = explode('/', Request::path());
                            $fallbackPlaceholder = array_slice($fallbackPlaceholder, 2);
                            $fallbackPlaceholder = implode('/', $fallbackPlaceholder).'/404';

                            return redirect()->route(
                                'grp.org.fallback',
                                [
                                    'organisation'        => $request->route()->originalParameters()['organisation'],
                                    'fallbackPlaceholder' => $fallbackPlaceholder
                                ]
                            );
                        }


                        return redirect()->route(
                            'grp.fallback',
                            [
                                'fallbackPlaceholder' => Request::path()
                            ]
                        );
                    }

                    Inertia::setRootView('app-'.$app);


                    return Inertia::render(
                        $this->getInertiaPage($e, $app),
                        $this->getInertiaProps($request->user(), $app, $request, $response, $e)
                    )
                        ->toResponse($request)
                        ->setStatusCode($response->getStatusCode());
                }

                // User isn't logged in
                if ($app == 'grp') {
                    return redirect('login');
                } elseif ($app == 'retina') {
                    return redirect('app/login');
                } else {
                    return $this->renderErrorForLogOutWebpages($app, $request, $e, $response);
                }
            });
        } elseif ($response->getStatusCode() === 419) {
            return back()->with([
                'message' => 'The page expired, please try again.',
            ]);
        }

        return $response;
    }

    private function getApp(): string
    {
        $host = Request::getHost();

        if ($host == 'app.'.config('app.domain')) {
            $app = 'grp';
        } elseif ($host == config('app.domain')) {
            $app = 'aiku-public';
        } else {
            $path = Request::path();
            if (preg_match('/^app\//', $path)) {
                $app = 'retina';
            } else {
                $app = 'iris';
            }
        }

        return $app;
    }

    private function getInertiaProps(User|WebUser|null $user, string $app, $request, $response, $e): array
    {
        $firstLoadOnlyProps = [];


        if (!$request->inertia()) {
            $firstLoadOnlyProps = match ($app) {
                'grp' => GetFirstLoadProps::run($user),
                'retina' => GetRetinaFirstLoadProps::run($request, $user),
                default => [],
            };

            $firstLoadOnlyProps['ziggy'] = function () use ($request) {
                return array_merge((new Ziggy())->toArray(), [
                    'location' => $request->url(),
                ]);
            };
        }

        $routeName = '';
        if ($request->route()) {
            $routeName = $request->route()->getName();
        }


        return array_merge(
            $firstLoadOnlyProps,
            [
                'error'     => $this->getBaseErrorData($response, $e),
                'datetime'  => now()->tz('UTC')->toDateTimeString(),
                'routeName' => $routeName,
                'auth'      => [
                    'user' => $request->user() ? LoggedUserResource::make($request->user())->getArray() : null,
                ],
                'ziggy'     => [
                    'location' => $request->url(),
                ],

            ]
        );
    }

    private function getBaseErrorData($response, $e): array
    {
        return match ($response->getStatusCode()) {
            403 => [
                'status'      => $response->getStatusCode(),
                'title'       => __('Forbidden'),
                'description' => __('Sorry, you are forbidden from accessing this page.')
            ],
            404 => [
                'status'      => $response->getStatusCode(),
                'title'       => __('Page Not Found'),
                'description' => __('Sorry, the page you are looking for could not be found.')
            ],
            422 => [
                'status'      => $response->getStatusCode(),
                'title'       => __('Unprocessable request'),
                'description' => __('Sorry, is impossible to process this page.')
            ],
            503 => [
                'status'      => $response->getStatusCode(),
                'title'       => __('Service Unavailable'),
                'description' => __('Sorry, we are doing some maintenance. Please check back soon.')
            ],
            default => $this->getExceptionInfo($e)
        };
    }

    public function getExceptionInfo(Throwable $e): array
    {
        if (get_class($e) == 'App\Exceptions\IrisWebsiteNotFound') {
            return [
                'status'      => 404,
                'title'       => __('Domain Not Found'),
                'description' => __('This domain was not been configured yet.')
            ];
        }

        return [
            'status'      => 500,
            'title'       => __('Server Error'),
            'description' => __('Whoops, is us not you.')
        ];
    }

    public function getInertiaPage(Throwable $e, string $app): string
    {
        if (get_class($e) == 'App\Exceptions\IrisWebsiteNotFound' && $app == 'iris') {
            return 'Errors/IrisWebsiteNotFound';
        }

        return 'Errors/Error';
    }

    public function renderErrorForLogOutWebpages($app, $request, Throwable $e, $response): Response|JsonResponse|\Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        Inertia::setRootView('app-'.$app);

        $user = $request->user();
        if (!$user) {
            $user = null;
        }

        $props     = $this->getInertiaProps($user, $app, $request, $response, $e);
        $errorData = $this->getBaseErrorData($response, $e);


        return Inertia::render(
            $this->getInertiaPage($e, $app),
            array_merge($props, $errorData),
        )
            ->toResponse($request)
            ->setStatusCode($response->getStatusCode());
    }


}
