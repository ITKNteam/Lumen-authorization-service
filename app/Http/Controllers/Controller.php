<?php

namespace App\Http\Controllers;

use http\Env\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController {
    public function requestHas(Request $request, $key) {
        if ($request->has($key)) {
            abort(400, "Missing parameter: $key");
        }
    }
}
