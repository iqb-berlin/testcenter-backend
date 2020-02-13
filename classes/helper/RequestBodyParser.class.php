<?php
/** @noinspection PhpUnhandledExceptionInspection */

use Slim\Exception\HttpBadRequestException;
use Slim\Http\Request;

class RequestBodyParser {

    static function getRequiredElement(Request $request, string $elementName) {

        $requestBody = JSON::decode($request->getBody());

        if (!isset($requestBody->$elementName)) {
            throw new HttpBadRequestException($request, "Required body-parameter is missing: `$elementName`");
        }

        return $requestBody->$elementName;
    }

    static function getElementWithDefault(Request $request, string $elementName, $default) {

        $requestBody = JSON::decode($request->getBody());

        return isset($requestBody->$elementName) ? $requestBody->$elementName : $default;
    }
}
