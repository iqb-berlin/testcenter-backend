<?php


use Slim\Http\Request;

abstract class Controller {

    // TODO refactor DAO to be static, than this would not be needed

    private static $_adminDAO;
    private static $_sessionDAO;

    protected static function sessionDAO(): SessionDAO {

        if (!self::$_sessionDAO) {
            self::$_sessionDAO = new SessionDAO();
        }

        return self::$_sessionDAO;
    }


    protected static function adminDAO(): AdminDAO {

        if (!self::$_adminDAO) {
            self::$_adminDAO = new AdminDAO();
        }

        return self::$_adminDAO;
    }


    protected static function authToken(Request $request): AuthToken {

        return $request->getAttribute('AuthToken');
    }
}
