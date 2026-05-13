<?php

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
