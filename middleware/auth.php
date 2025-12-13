<?php

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';

function authMiddleware() {
    if (!Auth::check()) {
        Response::error('No autorizado', 401);
    }
    return true;
}

