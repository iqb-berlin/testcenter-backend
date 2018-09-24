<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	exit();
} else {
	require_once('../vo_code/DBConnectionTC.php');

	// *****************************************************************

	// Booklet-Struktur: name, codes[], 
	$myreturn = '';

	$myerrorcode = 503;

	$myDBConnection = new DBConnectionTC();
	if (!$myDBConnection->isError()) {
		$myerrorcode = 401;

		$data = json_decode(file_get_contents('php://input'), true);
		$myLoginToken = $data["lt"];
		$myCode = $data["c"];
		$myBookletFilename = $data["b"];

		if (isset($myLoginToken) and isset($myBookletFilename)) {
			$wsId = $myDBConnection->getWorkspaceByLogintoken($myLoginToken);
			if ($wsId > 0) {
				$myBookletFilename = '../vo_data/ws_' . $wsId . '/Booklet' . '/' . $myBookletFilename;
				if (file_exists($myBookletFilename)) {

					require_once('../vo_code/XMLFile.php'); // // // // ========================
					$xFile = new XMLFile($myBookletFilename);
					if ($xFile->isValid()) {
						$myerrorcode = 0;
						$bKey = $xFile->getId();
						$bLabel = $xFile->getLabel();
						$myreturn = $myDBConnection->start_session($myLoginToken, $myCode, $bKey, $bLabel);
					}
				}
			}
		}				
	}    
	unset($myDBConnection);

	if ($myerrorcode > 0) {
		http_response_code($myerrorcode);
	} else {
		echo(json_encode($myreturn));
	}
}
?>