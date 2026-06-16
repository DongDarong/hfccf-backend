<?php

use App\Http\Middleware\EnsureGuardianPortalAccess;
use App\Http\Middleware\EnsureUserHasPermission;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? null) === 'testing') {
    $testingStoragePath = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'hfccf-backend-testing'.DIRECTORY_SEPARATOR.'storage';

    $_SERVER['LARAVEL_STORAGE_PATH'] = $testingStoragePath;
    $_ENV['LARAVEL_STORAGE_PATH'] = $testingStoragePath;
    putenv('LARAVEL_STORAGE_PATH='.$testingStoragePath);

    foreach ([
        $testingStoragePath,
        $testingStoragePath.DIRECTORY_SEPARATOR.'app',
        $testingStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private',
        $testingStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
        $testingStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'assessment-prints',
        $testingStoragePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'assessment-prints'.DIRECTORY_SEPARATOR.'tmp',
        $testingStoragePath.DIRECTORY_SEPARATOR.'logs',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'sessions',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'disks',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'disks'.DIRECTORY_SEPARATOR.'local',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'disks'.DIRECTORY_SEPARATOR.'public',
        $testingStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'disks'.DIRECTORY_SEPARATOR.'r2',
    ] as $directory) {
        if (! is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(HandleCors::class);
        $middleware->append(ThrottleRequests::class.':global');

        $middleware->alias([
            'guardian.portal' => EnsureGuardianPortalAccess::class,
            'permission' => EnsureUserHasPermission::class,
            'throttle' => ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                $errors = $exception->errors();
                $firstMessage = collect($errors)->flatten()->first() ?? 'The given data was invalid.';

                return response()->json([
                    'success' => false,
                    'message' => $firstMessage,
                    'data' => [
                        'errors' => $errors,
                    ],
                ], $exception->status);
            }
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'data' => null,
                ], Response::HTTP_UNAUTHORIZED);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please wait before trying again.',
                    'data' => null,
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'API endpoint not found.',
                    'data' => null,
                ], Response::HTTP_NOT_FOUND);
            }
        });
    })->create();
