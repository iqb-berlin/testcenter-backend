<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnusedParameterInspection */
declare(strict_types=1);
// TODO unit tests !


use Slim\Exception\HttpBadRequestException;
use Slim\Http\Response;
use Slim\Http\Request;
use Slim\Route;

class SystemController extends Controller {

    public static function get(Request $request, Response $response): Response {

        return $response->withJson(['version' => Version::get()]);
    }


    public static function getWorkspaces(Request $request, Response $response): Response {

        $workspaces = self::superAdminDAO()->getWorkspaces();
        return $response->withJson($workspaces);
    }


    public static function deleteWorkspaces(Request $request, Response $response): Response {

        $bodyData = JSON::decode($request->getBody()->getContents());
        $workspaceList = $bodyData->ws ?? [];

        if (!is_array($workspaceList)) {
            throw new HttpBadRequestException($request);
        }

        self::superAdminDAO()->deleteWorkspaces($workspaceList);

        foreach ($workspaceList as $workspaceId) {

            $workspace = new Workspace((int) $workspaceId);
            $workspace->delete();
        }

        BroadcastService::send('system/clean');

        return $response;
    }


    public static function getUsers(Request $request, Response $response): Response {

        return $response->withJson(self::superAdminDAO()->getUsers());
    }


    public static function deleteUsers(Request $request, Response $response): Response {

        $bodyData = JSON::decode($request->getBody()->getContents());
        $userList = isset($bodyData->u) ? $bodyData->u : [];

        self::superAdminDAO()->deleteUsers($userList);

        return $response;
    }


    public static function getListRoutes(Request $request, Response $response): Response {

        global $app;
        $routes = array_reduce(
            $app->getContainer()->get('router')->getRoutes(),
            function($target, Route $route) {
                foreach ($route->getMethods() as $method) {
                    $target[] = "[$method] " . $route->getPattern();
                }
                return $target;
            },
            []
        );

        return $response->withJson($routes);
    }


    public static function getVersion(Request $request, Response $response): Response {

        return $response->withJson(['version' => Version::get()]);
    }


    public static function getSystemConfig(Request $request, Response $response): Response {

        $meta = self::adminDAO()->getMeta(['customTexts', 'appConfig']);

        return $response->withJson(
            [
                'version' => Version::get(),
                'customTexts' => (object) $meta['customTexts'],
                'appConfig' => (object) $meta['appConfig'],
                'broadcastingService' => BroadcastService::getStatus(),
                'baseUrl' => Server::getUrl()
            ]
        );
    }


    public static function patchAppConfig(Request $request, Response $response): Response {

        return SystemController::updateMeta('appConfig', $request, $response);
    }


    public static function patchCustomTexts(Request $request, Response $response): Response {

        return SystemController::updateMeta('customTexts', $request, $response);
    }


    private static function updateMeta(string $category, Request $request, Response $response): Response {

        $requestBody = JSON::decode($request->getBody()->getContents());

        if (!is_object($requestBody)) {

            return $response;
        }

        foreach ($requestBody as $key => $value) {

            $valueAsString = (is_string($value) or is_null($value)) ? $value : json_encode($value);
            self::adminDAO()->setMeta($category, $key, $valueAsString);
        }

        return $response;
    }


    public static function getSystemTime(Request $request, Response $response): Response {

        return $response->withJson(
            [
                'timezone' => date_default_timezone_get(),
                'timestamp' => microtime(true) * 1000
            ]
        );
    }


    public static function getSysChecks(Request $request, Response $response): Response {

        $availableSysChecks = [];

        foreach (SysChecksFolder::getAll() as $sysChecksFolder) { /* @var SysChecksFolder $sysChecksFolder */

            $availableSysChecks = array_merge(
                $availableSysChecks,
                array_map(
                    function(XMLFileSysCheck $file) use ($sysChecksFolder) { return [
                        'workspaceId' => $sysChecksFolder->getId(),
                        'name' => $file->getId(),
                        'label' => $file->getLabel(),
                        'description' => $file->getDescription()
                    ]; },
                    $sysChecksFolder->findAvailableSysChecks()
                )
            );
        }

        if (!count($availableSysChecks)) {

            return $response->withStatus(204, "No SysChecks found.");
        }

        return $response->withJson($availableSysChecks);
    }


    public static function getFlushBroadcastingService(Request $request, Response $response): Response {

        BroadcastService::send('system/clean');
        return $response->withStatus(200);
    }
}
