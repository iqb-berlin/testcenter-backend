<?php
/** @noinspection PhpUnhandledExceptionInspection */

use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;


/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BookletsFolderTest extends TestCase {

    private vfsStreamDirectory $vfs;
    private BookletsFolder $bookletsFolder;

    public static function setUpBeforeClass(): void {

        require_once "unit-tests/VfsForTest.class.php";
        VfsForTest::setUpBeforeClass();
    }

    function setUp(): void {

        require_once "classes/data-collection/DataCollectionTypeSafe.class.php";
        require_once "classes/data-collection/Login.class.php";
        require_once "classes/data-collection/LoginArray.class.php";
        require_once "classes/data-collection/ValidationReportEntry.class.php";
        require_once "classes/exception/HttpError.class.php";
        require_once "classes/workspace/Workspace.class.php";
        require_once "classes/workspace/BookletsFolder.class.php";
        require_once "classes/files/File.class.php";
        require_once "classes/files/XMLFile.class.php";
        require_once "classes/files/XMLFileTesttakers.class.php";
        require_once "classes/helper/FileTime.class.php";
        require_once "classes/helper/FileName.class.php";
        require_once "classes/helper/TimeStamp.class.php";
        require_once "unit-tests/mock-classes/PasswordMock.php";

        $this->workspaceDaoMock = Mockery::mock('overload:' . WorkspaceDAO::class);
        $this->workspaceDaoMock->allows([
            'getGlobalIds' => VfsForTest::globalIds
        ]);
        $this->vfs = VfsForTest::setUp();
        $this->bookletsFolder = new BookletsFolder(1);
    }


    function test_getBookletLabel() {

        $result = $this->bookletsFolder->getBookletLabel('BOOKLET.SAMPLE-1');
        $expectation = 'Sample booklet';
        $this->assertEquals($expectation, $result);

        $this->expectException('HttpError');
        $this->bookletsFolder->getBookletLabel('inexistent.BOOKLET');
    }


    function test_getLogins() {

        $result = $this->bookletsFolder->getLogins();
        $this->assertEquals('test', $result->asArray()[0]->getName());
        $this->assertEquals('test-group-monitor', $result->asArray()[1]->getName());
        $this->assertEquals('test-review', $result->asArray()[2]->getName());
        $this->assertEquals('test-trial', $result->asArray()[3]->getName());
        $this->assertEquals('test-demo', $result->asArray()[4]->getName());
        $this->assertEquals('test-no-pw', $result->asArray()[5]->getName());
        $this->assertEquals('test-no-pw-trial', $result->asArray()[6]->getName());
        $this->assertEquals('test-expired', $result->asArray()[7]->getName());
        $this->assertEquals('expired-group-monitor', $result->asArray()[8]->getName());
        $this->assertEquals('test-future', $result->asArray()[9]->getName());
    }
}
