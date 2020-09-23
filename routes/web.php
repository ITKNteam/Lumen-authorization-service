<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->group(['prefix' => ''], function () use ($router) {
    $controller = "AuthController";
    $router->get('/', "$controller@welcome");
    $router->post('/getToken', "$controller@getToken");
    $router->post('/validateToken', "$controller@validateToken");
    $router->post('/refreshToken', "$controller@refreshToken");
    $router->post('/logout', "$controller@logout");
});
