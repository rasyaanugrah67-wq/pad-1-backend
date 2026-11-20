<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Pt04FeatureTest extends TestCase
{
    public function test_pt04_read_endpoints_require_sanctum_token(): void
    {
        $this->getJson('/api/dokumentasi')->assertUnauthorized();
        $this->getJson('/api/histori')->assertUnauthorized();
    }

    public function test_pt04_routes_have_expected_role_boundaries(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(
            fn ($route) => implode('|', $route->methods()) . ' ' . $route->uri()
        );

        $readMiddleware = $routes['GET|HEAD api/dokumentasi']->middleware();
        $manageMiddleware = $routes['POST api/dokumentasi']->middleware();
        $historyReadMiddleware = $routes['GET|HEAD api/histori']->middleware();
        $historyManageMiddleware = $routes['PUT api/histori/{id_periode}']->middleware();

        $this->assertContains('role:Admin,Panitia,Warga', $readMiddleware);
        $this->assertContains('role:Admin,Panitia', $manageMiddleware);
        $this->assertContains('role:Admin,Panitia,Warga', $historyReadMiddleware);
        $this->assertContains('role:Admin,Panitia', $historyManageMiddleware);
    }
}
