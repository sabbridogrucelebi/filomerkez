<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\CheckLicense;
use App\Http\Middleware\CheckModule;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\ForceJsonResponseCharset;



return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission'  => CheckPermission::class,
            'super_admin' => SuperAdminMiddleware::class,
            'license'     => CheckLicense::class,
            'module'      => CheckModule::class,
        ]);

        $middleware->appendToGroup('web', CheckLicense::class);
        $middleware->appendToGroup('api', CheckLicense::class);
        $middleware->appendToGroup('api', ForceJsonResponseCharset::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\AddPermissionsUpdatedHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['bot_token']);
    })->create();
