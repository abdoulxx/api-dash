<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', \App\Http\Middleware\CompressResponse::class);
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle 404 errors for API routes with uniform response format
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 404,
                    'message' => $e->getMessage(),
                    'data' => null,
                ], 404);
            }
        });

        // Handle 404 errors for API routes
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                // Try to extract model name from route if it's a route model binding issue
                $route = $request->route();
                $message = 'Ressource introuvable';
                
                if ($route) {
                    $parameters = $route->parameters();
                    // Check if any parameter looks like a ULID
                    foreach ($parameters as $key => $value) {
                        if (is_string($value) && \Illuminate\Support\Str::isUlid($value)) {
                            $message = sprintf(
                                'Ressource introuvable : aucun enregistrement trouvé avec l\'identifiant "%s" pour le paramètre "%s"',
                                $value,
                                $key
                            );
                            break;
                        }
                    }
                }
                
                return response()->json([
                    'status' => 404,
                    'message' => $message,
                    'data' => null,
                ], 404);
            }
        });
    })->create();
