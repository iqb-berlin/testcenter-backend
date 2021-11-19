<?php

declare(strict_types=1);


class Report {

    private const BOM = "\xEF\xBB\xBF";         // UTF-8 BOM for MS Excel
    private const DELIMITER = ';';              // standard delimiter for MS Excel
    private const ENCLOSURE = '"';
    private const LINE_ENDING = "\n";
    private const CSV_CELL_FORMAT = self::ENCLOSURE . "%s" . self::ENCLOSURE;

    private int $workspaceId;
    private array $dataIds;
    private string $type;
    private string $format;
    private AdminDAO $adminDAO;
    private SysChecksFolder $sysChecksFolder;

    private string $csvReportData;
    private array $reportData;


    /**
     * Report constructor.
     * @param int $workspaceId The workspace identifier
     * @param array $dataIds The identifiers of report data depending on specific workspace
     * @param ReportType $reportType The report type
     * @param ReportFormat $reportFormat The report format
     */
    function __construct(int $workspaceId, array $dataIds, ReportType $reportType, ReportFormat $reportFormat) {

        $this->workspaceId = $workspaceId;
        $this->dataIds = $dataIds;
        $this->type = $reportType->getValue();
        $this->format = $reportFormat->getValue();
    }

    /**
     * @return int
     */
    public function getWorkspaceId(): int {

        return $this->workspaceId;
    }

    /**
     * @return array
     */
    public function getDataIds(): array {

        return $this->dataIds;
    }

    /**
     * @return string
     */
    public function getType(): string {

        return $this->type;
    }

    /**
     * @return string
     */
    public function getFormat(): string {

        return $this->format;
    }

    /**
     * @param AdminDAO $adminDAO
     */
    public function setAdminDAOInstance(AdminDAO $adminDAO): void {

        if (!isset($this->adminDAO)) {
            $this->adminDAO = $adminDAO;
        }
    }

    /**
     * @param SysChecksFolder $sysChecksFolder
     */
    public function setSysChecksFolderInstance(SysChecksFolder $sysChecksFolder): void {

        if (!isset($this->sysChecksFolder)) {
            $this->sysChecksFolder = $sysChecksFolder;
        }
    }

    /**
     * @return string Raw CSV report data
     */
    public function getCsvReportData(): string {

        return $this->csvReportData;
    }

    /**
     * @return array An array of report data
     */
    public function getReportData(): array {

        return $this->reportData;
    }

    /**
     * @return bool True on report generation success, otherwise false
     */
    public function generate(): bool {

        switch ($this->type) {

            case ReportType::LOG:

                $logs = $this->adminDAO->getLogReportData($this->workspaceId, $this->dataIds);

                if (empty($logs)) {
                    return false;

                } else {
                    $this->reportData = $logs;

                    if ($this->format == ReportFormat::CSV) {
                        $this->csvReportData = $this->generateLogsCSVReport($logs);
                    }
                }

                break;

            case ReportType::RESPONSE:

                $responses = $this->adminDAO->getResponseReportData($this->workspaceId, $this->dataIds);

                if (empty($responses)) {
                    return false;

                } else {
                    $this->reportData = $responses;

                    if ($this->format == ReportFormat::CSV) {
                        $this->csvReportData = $this->generateResponsesCSVReport($responses);
                    }
                }

                break;

            case ReportType::REVIEW:

                $reviewData = $this->adminDAO->getReviewReportData($this->workspaceId, $this->dataIds);
                $reviewData = $this->transformReviewData($reviewData);

                if (empty($reviewData)) {
                    return false;

                } else {
                    $this->reportData = $reviewData;

                    if ($this->format == ReportFormat::CSV) {
                        $this->csvReportData = $this->generateReviewsCSVReport($reviewData);
                    }
                }

                break;

            case ReportType::SYSTEM_CHECK:

                $systemChecks = $this->sysChecksFolder->collectSysCheckReports($this->dataIds);

                if (empty($systemChecks)) {
                    return false;

                } else {
                    $this->reportData = array_map(
                        function(SysCheckReportFile $report) {

                            return $report->get();
                        },
                        $systemChecks
                    );

                    if ($this->format == ReportFormat::CSV) {
                        $flatReports = array_map(
                            function(SysCheckReportFile $report) {

                                return $report->getFlat();
                            },
                            $systemChecks
                        );
                        $this->csvReportData = self::BOM .
                            CSV::build(
                                $flatReports,
                                [],
                                self::DELIMITER,
                                self::ENCLOSURE,
                                self::LINE_ENDING
                            );
                    }
                }

                break;

            default:

                return false;   // @codeCoverageIgnore

        }

        return true;
    }

    /**
     * @param array $logData An array of Log data
     * @return string A raw csv report of Logs
     */
    private function generateLogsCSVReport(array $logData): string {

        $csv[] = implode(self::DELIMITER, CSV::collectColumnNamesFromHeterogeneousObjects($logData)); // TODO: Adjust column headers?

        foreach ($logData as $log) {
            $csv[] = implode(
                self::DELIMITER,
                [
                    sprintf(self::CSV_CELL_FORMAT, $log['groupname']),
                    sprintf(self::CSV_CELL_FORMAT, $log['loginname']),
                    sprintf(self::CSV_CELL_FORMAT, $log['code']),
                    sprintf(self::CSV_CELL_FORMAT, $log['bookletname']),
                    sprintf(self::CSV_CELL_FORMAT, $log['unitname']),
                    sprintf(self::CSV_CELL_FORMAT, $log['timestamp']),
                    preg_replace("/\\\\\"/", '""', $log['logentry'])   // TODO: adjust replacement & use cell enclosure ?
                ]
            );
        }

        $csv = implode(self::LINE_ENDING, $csv);

        return self::BOM . $csv;
    }

    /**
     * @param array $responseData
     * @return string A raw csv report of responses
     */
    private function generateResponsesCSVReport(array $responseData): string {

        $csv[] = implode(self::DELIMITER, CSV::collectColumnNamesFromHeterogeneousObjects($responseData)); // TODO: Adjust column headers?

        foreach ($responseData as $resp) {
            $csv[] = implode(
                self::DELIMITER,
                [
                    sprintf(self::CSV_CELL_FORMAT, $resp['groupname']),
                    sprintf(self::CSV_CELL_FORMAT, $resp['loginname']),
                    sprintf(self::CSV_CELL_FORMAT, $resp['code']),
                    sprintf(self::CSV_CELL_FORMAT, $resp['bookletname']),
                    sprintf(self::CSV_CELL_FORMAT, $resp['unitname']),
                    preg_replace("/\\\\\"/", '""', $resp['responses']),     // TODO: adjust replacement & use cell enclosure ?
                    preg_replace("/\\\\\"/", '""', $resp['restorePoint']),  // TODO: adjust replacement & use cell enclosure ?
                    empty($resp['responseType'])
                        ? ""                                                            // TODO: Don't allow empty cell values ?
                        : sprintf(self::CSV_CELL_FORMAT, $resp['responseType']),
                    $resp['response-ts'],                                              // TODO: use cell enclosure ?
                    $resp['restorePoint-ts'],                                          // TODO: use cell enclosure ?
                    empty($resp['laststate'])
                        ? ""
                        : sprintf(self::CSV_CELL_FORMAT, $resp['laststate'])    // TODO: adjust cell format ?
                ]
            );

        }

        $csv = implode(self::LINE_ENDING, $csv);

        return self::BOM . $csv;
    }

    /**
     * @param array $reviewData An array of Review data
     * @return array An array transformed Review data
     */
    private function transformReviewData(array $reviewData): array {

        $transformedReviewData = [];
        $categoryKeys = $this->extractCategoryKeys($reviewData);

        foreach ($reviewData as $review) {
            $offset = array_search('categories', array_keys($review));
            $transformedReviewData[] =
                array_slice($review, 0, $offset) +
                $this->fillCategories($categoryKeys, explode(" ", $review['categories'])) +
                array_slice($review, $offset + 1, null);
        }

        return $transformedReviewData;
    }


    /**
     * @param array $reviewData An array of Review data
     * @return array A map of category keys
     */
    private function extractCategoryKeys(array $reviewData): array {

        $categoryMap = [];

        foreach ($reviewData as $reviewEntry) {

            if (!empty($reviewEntry['categories'])) {
                $categories = explode(" ", $reviewEntry['categories']);

                foreach ($categories as $category) {

                    if (0 === count(array_keys($categoryMap, $category))) {
                        $categoryMap[] = $category;
                    }
                }
            }
        }
        asort($categoryMap);

        return $categoryMap;
    }


    /**
     * @param array $categoryKeys An array of category keys
     * @param array $categoryValues An array of category values
     * @return array An associated array of category keys and transformed category values
     */
    private function fillCategories(array $categoryKeys, array $categoryValues): array {

        $categories = [];

        foreach ($categoryKeys as $categoryKey) {
            $isMatch = false;

            foreach ($categoryValues as $categoryValue) {
                if ($categoryKey === $categoryValue) {
                    $isMatch = true;
                    break;
                }
            }
            $categories["category: " . $categoryKey] = $isMatch ? 'X' : null;
        }

        return $categories;
    }


    /**
     * @param array $reviewData An array of Review data
     * @return string A raw csv report of reviews
     */
    private function generateReviewsCSVReport(array $reviewData): string {

        $csv[] = implode(self::DELIMITER, CSV::collectColumnNamesFromHeterogeneousObjects($reviewData));   // TODO: Adjust column headers?

        foreach ($reviewData as $review) {
            $csv[] = implode(
                self::DELIMITER,
                array_map(
                    function($reviewProperty) {

                        return isset($reviewProperty) ? sprintf(self::CSV_CELL_FORMAT, $reviewProperty) : $reviewProperty;
                    },
                    $review
                )
            );
        }

        $csv = implode(self::LINE_ENDING, $csv);

        return self::BOM . $csv;
    }

}