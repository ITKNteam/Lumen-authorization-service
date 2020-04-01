<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Providers\AuthServiceProvider;

class AuthController extends Controller {


    private $authService;

    function __construct(AuthServiceProvider $service) {
        $this->authService = $service;
    }

    /**
     * Retrieve the user for the given ID.
     *
     * @param Request $request
     * @return Response
     */
    public function getUser(Request $request) {
        return 'dadas';
    }

    /**
     * @param Request $request
     * @return string
     */
    public function getToken(Request $request): string {
        if (!$request->has('id')) {
            return 'Missing parameter id';
        }

        return json_encode($this->authService->getToken($request->get('id')));
    }

    /**
     * @param Request $request
     * @return false|string
     */
    public function validateToken(Request $request) {
        if (!$request->has('token')) {
            return 'Missing parameter id';
        }

        return json_encode($this->authService->authentication($request->get('token')));
    }

    /**
     * @param Request $request
     * @return false|string
     */
    public function refreshToken(Request $request) {
        if (!$request->has('token')) {
            return 'Missing parameter token';
        }

        return json_encode($this->authService->refreshToken($request->get('token')));
    }

    /**
     * @param Request $request
     * @return false|string
     */
    public function logout(Request $request) {
        if (!$request->has('token')) {
            return 'Missing parameter token';
        }

        return json_encode($this->authService->logout($request->get('token')));
    }
}