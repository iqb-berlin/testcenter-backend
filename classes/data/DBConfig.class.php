<?php
/** @noinspection PhpUnhandledExceptionInspection */


class DBConfig extends AbstractDataCollection {

    public $host = "localhost";
    public $port = "3306";
    public $dbname = "";
    public $user = "";
    public $password = "";
    public $salt = "t"; // for passwords
    public $type = null; // mysql,
    public $staticTokens = false; // relevant for unit- and e2e-tests
}