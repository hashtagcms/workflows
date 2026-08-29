<?php

use Illuminate\Support\Facades\Route;
use HashtagCms\Workflows\Http\Controllers\Admin\WorkflowBuilderController;
use HashtagCms\Workflows\Http\Controllers\Admin\WorkflowDirectiveController;
use HashtagCms\Workflows\Http\Controllers\Admin\WorkflowLogController;
use HashtagCms\Workflows\Http\Controllers\Admin\PlaygroundController;

/*
|--------------------------------------------------------------------------
| HashtagCMS Workflows — Admin (web) routes
|--------------------------------------------------------------------------
| Self-contained, explicit admin routes for this package. This is the pattern
| every HashtagCMS package should follow (see also hashtagcms-extended): the
| package owns its own routing and references its own controllers directly.
|
| The controllers extend core's admin CRUD base, whose action methods have
| plain signatures (index, create, edit($id), store(Request), destroy($id),
| publish($id,$status), search, show($id)). Because of that, Laravel binds the
| {id}/{param} URL segments and injects Request natively — no dynamic
| reflection dispatcher is needed.
|
| URLs mirror the admin UI's convention: {prefix}/{group}/{action}/{id}
|   e.g. admin/workflows/builder            -> WorkflowBuilderController@index
|        admin/workflows/builder/edit/5     -> WorkflowBuilderController@edit(5)
|        admin/workflows/builder/store (POST)-> WorkflowBuilderController@store
|        admin/workflows/logs/show/9        -> WorkflowLogController@show(9)
*/

$adminBasePath = trim(config('hashtagcmsadmin.cmsInfo.base_path', 'admin'), '/');
$routePrefix   = trim(config('hashtagcms-workflows.route_prefix') ?: ($adminBasePath . '/workflows'), '/');
$middleware    = config('hashtagcms-workflows.middleware', ['web', 'auth:sanctum', 'cmsModuleInfo', 'cmsInterceptor']);

// Interactive Workflow Manager compiled assets (Vue 3 / webpack bundle). Served
// publicly (no admin middleware) so the browser can load the JS/CSS on the admin
// page; the closure whitelists filenames and streams them from the package.
Route::get($routePrefix . '/builder/asset/{file}', function (string $file) {
    $allowed = [
        'workflow-builder.js' => 'application/javascript; charset=utf-8',
        'workflow-builder.css' => 'text/css; charset=utf-8',
    ];
    abort_unless(isset($allowed[$file]), 404);

    $path = dirname(__DIR__) . '/resources/dist/' . $file;
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => $allowed[$file]]);
})->where('file', '[A-Za-z0-9._-]+')->name('hashtagcms.workflows.builder.asset');

Route::prefix($routePrefix)
    ->middleware($middleware)
    ->name('hashtagcms.workflows.')
    ->group(function () use ($routePrefix) {

        // Landing → Workflow Manager
        Route::get('home', fn () => redirect($routePrefix . '/builder'))->name('home');

        // Playground — a read-only demo screen to run seeded workflows
        Route::get('playground', [PlaygroundController::class, 'index'])->name('playground');

        // Workflow Manager (Vue-based visual builder — full CRUD)
        Route::controller(WorkflowBuilderController::class)
            ->prefix('builder')
            ->name('builder.')
            ->group(function () {
                Route::match(['get', 'post'], '/', 'index')->name('index');
                Route::match(['get', 'post'], 'search', 'search')->name('search');
                Route::match(['get', 'post'], 'create', 'create')->name('create');
                Route::match(['get', 'post'], 'edit/{id?}/{param1?}', 'edit')->name('edit');
                Route::post('store', 'store')->name('store');
                Route::post('preview', 'preview')->name('preview');
                Route::match(['get', 'post'], 'publish/{id?}/{status?}', 'publish')->name('publish');
                Route::match(['get', 'post', 'delete'], 'destroy/{id}', 'destroy')->name('destroy');
            });

        // Workflow Directives (capability manifest CRUD)
        Route::controller(WorkflowDirectiveController::class)
            ->prefix('directives')
            ->name('directives.')
            ->group(function () {
                Route::match(['get', 'post'], '/', 'index')->name('index');
                Route::match(['get', 'post'], 'search', 'search')->name('search');
                Route::match(['get', 'post'], 'create', 'create')->name('create');
                Route::match(['get', 'post'], 'edit/{id?}/{param1?}', 'edit')->name('edit');
                Route::post('store', 'store')->name('store');
                Route::match(['get', 'post'], 'publish/{id?}/{status?}', 'publish')->name('publish');
                Route::match(['get', 'post', 'delete'], 'destroy/{id}', 'destroy')->name('destroy');
            });

        // Workflow Logs (read + delete)
        Route::controller(WorkflowLogController::class)
            ->prefix('logs')
            ->name('logs.')
            ->group(function () {
                Route::match(['get', 'post'], '/', 'index')->name('index');
                Route::match(['get', 'post'], 'search', 'search')->name('search');
                Route::match(['get', 'post'], 'show/{id}', 'show')->name('show');
                Route::match(['get', 'post', 'delete'], 'destroy/{id}', 'destroy')->name('destroy');
            });
    });
