<?php

use PHPUnit\Framework\TestCase;


class SessionTest extends TestCase {

    static function setUpBeforeClass(): void {

        require_once "classes/data-collection/DataCollectionTypeSafe.class.php";
        require_once "classes/data-collection/AccessSet.class.php";

        parent::setUpBeforeClass(); // TODO: Change the autogenerated stub
    }

    function test_constructor() {

        $session = new AccessSet(
            "token-string",
            "display-name",
            [1, "2nd flag"],
            (object) [
                "something" => "else"
            ]
        );

        $expected = [
            "token" => "token-string",
            "displayName" => "display-name",
            "flags" => ["1", "2nd flag"],
            "customTexts" => (object) ["something" => "else"],
            "access" => (object) []
        ];

        $this->assertEquals($expected, $session->jsonSerialize());
    }


    function test_addAccessObjects() {

        $session = new AccessSet(
            "token-string",
            "display-name"
        );

        $session->addAccessObjects("test", "1", "2", "3");

        $expected = (object) ["test" => ["1", "2", "3"]];

        $this->assertEquals($expected, $session->jsonSerialize()["access"]);

        $session->addAccessObjects("workspaceAdmin", "1", "2", "3");

        $expected = (object) [
            "workspaceAdmin" => ["1", "2", "3"],
            "test" => ["1", "2", "3"]
        ];

        $this->assertEquals($expected, $session->jsonSerialize()["access"]);
    }


    function test_addAccessObjectsUnknownAccessType() {

        $session = new AccessSet(
            "token-string",
            "display-name"
        );

        $this->expectException('Exception');
        $session->addAccessObjects("something_unknown", "1", "2", "3");
    }


    function test_hasAccess() {

        $session = new AccessSet(
            "token-string",
            "display-name"
        );

        $session->addAccessObjects("test", "1", "2", "3");
        $session->addAccessObjects("superAdmin");

        $this->assertEquals(true, $session->hasAccess("test"));
        $this->assertEquals(true, $session->hasAccess("superAdmin"));
        $this->assertEquals(false, $session->hasAccess("workspaceAdmin"));
        $this->assertEquals(true, $session->hasAccess("test", 1));
        $this->assertEquals(false, $session->hasAccess("test", 5));
        $this->assertEquals(false, $session->hasAccess("superAdmin", 1));
        $this->assertEquals(false, $session->hasAccess("workspaceAdmin", 1));
    }
}
