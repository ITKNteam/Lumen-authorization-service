<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Providers\AuthServiceProvider;

class AuthController extends Controller {
    /**
     * @var AuthServiceProvider
     */
    private $authService;

    /**
     * AuthController constructor.
     * @param AuthServiceProvider $service
     */
    function __construct(AuthServiceProvider $service) {
        $this->authService = $service;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getToken(Request $request): array {
        $this->requestHas($request, 'id');

        return $this->authService->getToken($request->get('id'))->getAnswer();
    }

    /**
     * @param Request $request
     * @return array
     */
    public function validateToken(Request $request): array {
        $this->requestHas($request, 'token');

        return $this->authService->authentication($request->get('token'))->getAnswer();
    }

    /**
     * @param Request $request
     * @return array
     */
    public function refreshToken(Request $request): array {
        $this->requestHas($request, 'token');

        return $this->authService->refreshToken($request->get('token'))->getAnswer();
    }

    /**
     * @param Request $request
     * @return array
     */
    public function logout(Request $request): array {
        $this->requestHas($request, 'token');

        return $this->authService->logout($request->get('token'))->getAnswer();
    }
}