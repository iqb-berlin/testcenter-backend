<?php

/** @noinspection PhpUnhandledExceptionInspection */

use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Http\Request;
use Slim\Http\Response;

class IsSuperAdmin {

    function __invoke(Request $request, Response $response, $next) {

        /* @var $authToken AdminAuthToken */
        $authToken = $request->getAttribute('AuthToken');

        if (!$authToken) {
            throw new HttpInternalServerErrorException($request, 'Validated AuthToken not found.');
        }

        if ($authToken::type != 'admin') {
            throw new HttpInternalServerErrorException($request, "AuthToken of wrong type: {$authToken::type}");
        }

        if (!$authToken->isSuperAdmin()) {
            throw new HttpForbiddenException($request, "Only SuperAdmins can do that");
        }

        $next($request, $response);
    }
}