<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);
// TODO unit test

class WorkspaceInitializer {


    const sampleDataPaths = [
      "sampledata/Booklet.xml" => "Booklet/SAMPLE_BOOKLET.XML",
      "sampledata/Booklet2.xml" => "Booklet/SAMPLE_BOOKLET2.XML",
      "sampledata/Booklet3.xml" => "Booklet/SAMPLE_BOOKLET3.XML",
      "sampledata/Testtakers.xml" => "Testtakers/SAMPLE_TESTTAKERS.XML",
      "sampledata/SysCheck.xml" => "SysCheck/SAMPLE_SYSCHECK.XML",
      "sampledata/Unit.xml" => "Unit/SAMPLE_UNIT.XML",
      "vendor/iqb-berlin/verona-player-simple/sample-data/introduction-unit.htm" => "Resource/SAMPLE_UNITCONTENTS.HTM",
      "sampledata/Unit2.xml" => "Unit/SAMPLE_UNIT2.XML",
      "vendor/iqb-berlin/verona-player-simple/verona-player-simple-4.0.0.html" => "Resource/verona-player-simple-4.0.0.html",
      "sampledata/SysCheck-Report.json" => "SysCheck/reports/SAMPLE_SYSCHECK-REPORT.JSON",
      "sampledata/sample_resource_package.itcr.zip" => "Resource/sample_resource_package.itcr.zip"
    ];


    private function importSampleFile(int $workspaceId, string $source, string $target) {

        $importFileName = ROOT_DIR . '/' . $source;

        if (!file_exists($importFileName)) {
            throw new Exception("File not found: `$importFileName`");
        }

        $dir = pathinfo($target, PATHINFO_DIRNAME);
        $fileName = basename($target);
        $fileName = Folder::createPath(DATA_DIR . "/ws_$workspaceId/$dir") . $fileName;

        if (!@copy($importFileName, $fileName)) {
            throw new Exception("Could not write file: $fileName");
        }
    }


    public function importSampleData(int $workspaceId): void {

        foreach ($this::sampleDataPaths as $source => $target) {

            if (!file_exists(ROOT_DIR . '/' . $source)) {
                throw new Exception("File not found: `$source`");
            }
        }

        foreach ($this::sampleDataPaths as $source => $target) {

            $this->importSampleFile($workspaceId, $source, $target);
        }
    }


    public function cleanWorkspace(int $workspaceId): void {

        Folder::deleteContentsRecursive(DATA_DIR . "/ws_$workspaceId/");
        rmdir(DATA_DIR . "/ws_$workspaceId/");
    }
}
