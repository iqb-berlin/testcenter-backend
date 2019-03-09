<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

require_once('DBConnection.php');

class DBConnectionTC extends DBConnection {
    private $idletimeSession = 60 * 30;

    // for all functions here: $persontoken is the token as stored in the
    // database; the sessiontoken given to other functions and used by the 
    // client is a  combination of sessiontoken (db) + booklet-DB-id

    // =================================================================
    public function getBookletId($auth) {
        $myreturn = 0;

        if (isset($auth)) {
            if (is_string($auth)) {
                if (strlen($auth) > 0) {
                    $tokensplits = explode('##', $auth);
                    if (count($tokensplits) == 2) {
                        $persontoken = $tokensplits[0];
                        $bookletDbId = $tokensplits[1];
                        if ((strlen($persontoken) > 0) and (strlen($bookletDbId) > 0) and is_numeric($bookletDbId)) {
                            $myreturn = intval($bookletDbId);
                        }}}}}

        return $myreturn;
    }

    // =================================================================
    public function authOk($auth, $RW = false) {
        $myreturn = false;

        if (isset($auth)) {
            if (is_string($auth)) {
                if (strlen($auth) > 0) {
                    $tokensplits = explode('##', $auth);
                    if (count($tokensplits) == 2) {
                        $persontoken = $tokensplits[0];
                        $bookletDbId = $tokensplits[1];
                        if ((strlen($persontoken) > 0) and (strlen($bookletDbId) > 0) and is_numeric($bookletDbId)) {

                            // 6666666666666666666666
                            $booklet_select = $this->pdoDBhandle->prepare(
                                'SELECT booklets.locked FROM booklets
                                    INNER JOIN persons ON persons.id = booklets.person_id
                                    WHERE persons.token=:token and booklets.id=:bookletId');
                                
                            if ($booklet_select->execute(array(
                                ':token' => $persontoken,
                                ':bookletId' => $bookletDbId
                                ))) {
                
                                $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
                                if ($bookletdata !== false) {
                                    $myreturn = ($RW === false) || ($bookletdata['locked'] !== 't');
                                }
                            }
                
                        }
                    }
                }
            }
            
        }
        return $myreturn;
    }

    // =================================================================
    public function canWriteBookletData($persontoken, $bookletDbId) {
        $myreturn = false;
        $booklet_select = $this->pdoDBhandle->prepare(
            'SELECT booklets.locked FROM booklets
                INNER JOIN persons ON persons.id = booklets.person_id
                WHERE persons.token=:token and booklets.id=:bookletId');
            
        if ($booklet_select->execute(array(
            ':token' => $persontoken,
            ':bookletId' => $bookletDbId
            ))) {

            $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
            if ($bookletdata !== false) {
                $myreturn = $bookletdata['locked'] !== 't';
            }
        }
        return $myreturn;
    }

    // =================================================================
    public function addBookletReview($bookletDbId, $priority, $categories, $entry) {
        $myreturn = false;
        if ($this->pdoDBhandle != false) {
            if (is_numeric($priority)) {
                $priority = intval($priority);
                if (($priority <= 0) or ($priority > 3)) {
                    $priority = 0;
                }
            } else {
                $priority = 0;
            }

            $bookletreview_insert = $this->pdoDBhandle->prepare(
                'INSERT INTO bookletreviews (booklet_id, reviewtime, reviewer, priority, categories, entry) 
                    VALUES(:b, :t, :r, :p, :c, :e)');

            if ($bookletreview_insert->execute(array(
                ':b' => $bookletDbId,
                ':t' => date('Y-m-d H:i:s', time()),
                ':r' => '-',
                ':p' => $priority,
                ':c' => $categories,
                ':e' => $entry
                ))) {
                    $myreturn = true;
            }
        }
        return $myreturn;
    }

    // =================================================================
    public function addUnitReview($bookletDbId, $unit, $priority, $categories, $entry) {
        $myreturn = false;
        if ($this->pdoDBhandle != false) {
            try {
                $this->pdoDBhandle->beginTransaction();
                $unitDbId = $this->findOrAddUnit($bookletDbId, $unit);
                if (is_numeric($priority)) {
                    $priority = intval($priority);
                    if (($priority <= 0) or ($priority > 3)) {
                        $priority = 0;
                    }
                } else {
                    $priority = 0;
                }
                $unitreview_insert = $this->pdoDBhandle->prepare(
                    'INSERT INTO unitreviews (unit_id, reviewtime, reviewer, priority, categories, entry) 
                        VALUES(:u, :t, :r, :p, :c, :e)');

                $unitreview_insert->execute(array(
                    ':u' => $unitDbId,
                    ':t' => date('Y-m-d H:i:s', time()),
                    ':r' => '-',
                    ':p' => $priority,
                    ':c' => $categories,
                    ':e' => $entry));

                $this->pdoDBhandle->commit();
                $myreturn = true;
            } 

            catch(Exception $e){
                $this->pdoDBhandle->rollBack();
            }
        }
        return $myreturn;
    }

    // =================================================================
    public function getWorkspaceByLogintoken($logintoken) {
        $myreturn = 0;
        if ($this->pdoDBhandle != false) {
            $login_select = $this->pdoDBhandle->prepare(
                'SELECT logins.workspace_id FROM logins
                    WHERE logins.token=:token');
                
            if ($login_select->execute(array(
                ':token' => $logintoken
                ))) {

                $logindata = $login_select->fetch(PDO::FETCH_ASSOC);
                if ($logindata !== false) {
                    $myreturn = 0 + $logindata['workspace_id'];
                }
            }
        }
        return $myreturn;
    }

    // =================================================================
    public function getWorkspaceByPersonToken($persontoken) {
        $myreturn = 0;

        if (strlen($persontoken) > 0) {
               
            $person_select = $this->pdoDBhandle->prepare(
                'SELECT logins.workspace_id FROM persons
                    INNER JOIN logins ON logins.id = persons.login_id
                    WHERE persons.token=:token');
            if ($person_select->execute(array(
                ':token' => $persontoken
                ))) {

                $persondata = $person_select->fetch(PDO::FETCH_ASSOC);
                if ($persondata !== false) {
                    $myreturn = $persondata['workspace_id'];
                }
            }
        }
        return $myreturn;
    }

    // =================================================================
    public function getWorkspaceByAuth($auth) {
        $myreturn = 0;

        if (isset($auth)) {
            if (is_string($auth)) {
                if (strlen($auth) > 0) {
                    $tokensplits = explode('##', $auth);
                    if (count($tokensplits) == 2) {
                        $persontoken = $tokensplits[0];
                        // $bookletDbId = $tokensplits[1];
                        // if ((strlen($persontoken) > 0) and (strlen($bookletDbId) > 0) and is_numeric($bookletDbId)) {
                        if (strlen($persontoken) > 0) {

                            // 6666666666666666666666
                            // $person_select = $this->pdoDBhandle->prepare(
                            //     'SELECT logins.workspace_id FROM booklets
                            //         INNER JOIN persons ON persons.id = booklets.person_id
                            //         INNER JOIN logins ON logins.id = persons.login_id
                            //         WHERE persons.token=:token and booklets.id=:bookletId');
                                
                            $person_select = $this->pdoDBhandle->prepare(
                                'SELECT logins.workspace_id FROM persons
                                    INNER JOIN logins ON logins.id = persons.login_id
                                    WHERE persons.token=:token');
                            if ($person_select->execute(array(
                                ':token' => $persontoken
                                ))) {
                
                                $persondata = $person_select->fetch(PDO::FETCH_ASSOC);
                                if ($persondata !== false) {
                                    $myreturn = $persondata['workspace_id'];
                                }
                            }
                
                        }
                    }
                }
            }
            
        }
        return $myreturn;
    }
    
    // =================================================================
    public function getBookletNameByAuth($auth) {
        $myreturn = '';

        if (isset($auth)) {
            if (is_string($auth)) {
                if (strlen($auth) > 0) {
                    $tokensplits = explode('##', $auth);
                    if (count($tokensplits) == 2) {
                        $persontoken = $tokensplits[0];
                        $bookletDbId = $tokensplits[1];
                        if ((strlen($persontoken) > 0) and (strlen($bookletDbId) > 0) and is_numeric($bookletDbId)) {

                            // 6666666666666666666666
                            $booklet_select = $this->pdoDBhandle->prepare(
                                'SELECT booklets.name FROM booklets
                                    INNER JOIN persons ON persons.id = booklets.person_id
                                    WHERE persons.token=:token and booklets.id=:bookletId');
                                
                            if ($booklet_select->execute(array(
                                ':token' => $persontoken,
                                ':bookletId' => $bookletDbId
                                ))) {
                
                                $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
                                if ($bookletdata !== false) {
                                    $myreturn = $bookletdata['name'];
                                }
                            }
                
                        }
                    }
                }
            }
            
        }
        return $myreturn;
    }
    
    // =================================================================
    public function getBookletStatus($bookletDbId) {
        $myreturn = [];
        if ($this->pdoDBhandle != false) {
            $booklet_select = $this->pdoDBhandle->prepare(
                'SELECT booklets.locked, booklets.laststate FROM booklets
                    WHERE booklets.id=:bookletId');
                
            if ($booklet_select->execute(array(
                ':bookletId' => $bookletDbId
                ))) {

                $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
                if ($bookletdata !== false) {
                    $myreturn = json_decode($bookletdata['laststate'], true);
                    if ($bookletdata['locked'] === 't') {
                        $myreturn['locked'] = true;
                    }
                }
            }
        }
        return $myreturn;
    }

    // =================================================================
    public function getBookletName($bookletDbId) {
        $myreturn = '';
        if ($this->pdoDBhandle != false) {
            $booklet_select = $this->pdoDBhandle->prepare(
                'SELECT booklets.name FROM booklets
                    WHERE booklets.id=:bookletId');
                
            if ($booklet_select->execute(array(
                ':bookletId' => $bookletDbId
                ))) {

                $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
                if ($bookletdata !== false) {
                    $myreturn =  $bookletdata['name'];
                }
            }
        }
        return $myreturn;
    }
        

    // =================================================================
    // check via canWriteBookletData before calling this!
    public function setBookletStatus($bookletDbId, $stateKey, $stateValue) {
        $myreturn = false;
        if ($this->pdoDBhandle != false) {
            try {
                $this->pdoDBhandle->beginTransaction();
                $booklet_select = $this->pdoDBhandle->prepare(
                    'SELECT booklets.laststate FROM booklets
                        WHERE booklets.id=:bookletId');
                    
                $booklet_select->execute(array(':bookletId' => $bookletDbId));
                $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);

                $stateStr = $bookletdata['laststate'];
                $state = [];
                if (isset($stateStr)) {
                    if(strlen($stateStr) > 0) {
                        $state = json_decode($stateStr, true);
                    }
                }
                $state[$stateKey] = $stateValue;
                $booklet_update = $this->pdoDBhandle->prepare(
                    'UPDATE booklets SET laststate = :laststate WHERE id = :id');
                $booklet_update -> execute(array(
                    ':laststate' => json_encode($state),
                    ':id' => $bookletDbId));
                $this->pdoDBhandle->commit();
                $myreturn = true;
            } 

            catch(Exception $e){
                $this->pdoDBhandle->rollBack();
            }

        }
        return $myreturn;
    }

    // =================================================================
    public function unlockBooklet($bookletDbId) {
        $myreturn = false;
        if ($this->pdoDBhandle != false) {
            $booklet_update = $this->pdoDBhandle->prepare(
                'UPDATE booklets SET locked = "f" WHERE id = :id');
            if ($booklet_update -> execute(array(
                ':id' => $bookletDbId))) {
                $myreturn = true;
            }
        }
        return $myreturn;
    }

    // __________________________

    // =================================================================
    public function start_sessionByPersonToken($pToken, $booklet, $bookletLabel) {
        $myreturn = 0;

        $persons_select = $this->pdoDBhandle->prepare(
            'SELECT persons.id FROM persons
                WHERE persons.token=:token');
            
        if ($persons_select->execute(array(
            ':token' => $pToken
            ))) {

            $personsdata = $persons_select->fetch(PDO::FETCH_ASSOC);
            if ($personsdata !== false) {
                $personId = $personsdata['id'];

                $booklet_select = $this->pdoDBhandle->prepare(
                    'SELECT booklets.locked, booklets.id FROM booklets
                        WHERE booklets.person_id=:personId and booklets.name=:bookletname');
                    
                if ($booklet_select->execute(array(
                    ':personId' => $personId,
                    ':bookletname' => $booklet
                    ))) {
    
                    $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
                    if ($bookletdata !== false) {
                        if ($bookletdata['locked'] !== 't') {
                            // setting $bookletLabel
                            $booklet_update = $this->pdoDBhandle->prepare(
                                'UPDATE booklets SET label = :label WHERE id = :id');
                            if ($booklet_update -> execute(array(
                                ':label' => $bookletLabel,
                                ':id' => $bookletdata['id']))) {

                                $myreturn = $bookletdata['id'];
                            }
                        }
                    } else {
                        $booklet_insert = $this->pdoDBhandle->prepare(
                            'INSERT INTO booklets (person_id, name, laststate, label) 
                                VALUES(:person_id, :name, :laststate, :label)');
    
                        if ($booklet_insert->execute(array(
                            ':person_id' => $personId,
                            ':name' => $booklet,
                            ':laststate' => json_encode(['u' => 0]),
                            ':label' => $bookletLabel
                            ))) {

                            $booklet_select = $this->pdoDBhandle->prepare(
                                'SELECT booklets.id FROM booklets
                                    WHERE booklets.person_id=:personId and booklets.name=:bookletname');
                                
                            if ($booklet_select->execute(array(
                                ':personId' => $personsdata['id'],
                                ':bookletname' => $booklet
                                ))) {
                
                                $bookletdata = $booklet_select->fetch(PDO::FETCH_ASSOC);
                                if ($bookletdata !== false) {
                                    $myreturn = $bookletdata['id'];
                                }
                            }
                        }
                    }
                }
            }
        }
        return $myreturn;
    }
    
    
    /*
    // __________________________
    public function stop_session($sessiontoken, $mode) {
        // mode: (by testee) 'cancelled', 'intended', (by test-mc) 'killed'
        $myreturn = '';
        if (($this::: != false) and 
                (count($sessiontoken) > 0)) {

            $sessionquery = :::select($this:::, 'people', ['token' => $sessiontoken]);
            if (($sessionquery != false) and (count($sessionquery) > 0)) {
                // remove token
                $laststate_booklet = ['lastunit' => '', 'finished' => $mode];
                :::update($this:::, 'people', 
                        ['valid_until' => date('Y-m-d G:i:s', time()), 'token' => '', 'laststate' => json_encode($laststate_booklet)],
                        ['id' => $sessionquery[0]['id']]);
            }
        }
        return $myreturn;
    }
    */


    // . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . 
    //  . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . . .

    // =================================================================
    public function getUnitStatus($bookletDbId, $unit) {
        $myreturn = [];
        if ($this->pdoDBhandle != false) {
            $unit_select = $this->pdoDBhandle->prepare(
                'SELECT units.laststate FROM units
                    WHERE units.name = :unitname and units.booklet_id = :bookletId');
                
            if ($unit_select->execute(array(
                ':unitname' => $unit,
                ':bookletId' => $bookletDbId
                ))) {
                
                $unitdata = $unit_select->fetch(PDO::FETCH_ASSOC);
                if ($unitdata !== false) {
                    $myreturn['restorepoint'] = $unitdata['laststate'];
                }
            }
        }
        return $myreturn;
    }

    // __________________________
    // public function setUnitStatus_laststate($bookletDbId, $unit, $laststate) {
    //     $myreturn = false;
    //     if ($this->pdoDBhandle != false) {
    //         $unit_select = $this->pdoDBhandle->prepare(
    //             'SELECT units.id FROM units
    //                 WHERE units.name=:name and units.booklet_id=:bookletId');
                
    //         if ($unit_select->execute(array(
    //             ':name' => $unit,
    //             ':bookletId' => $bookletDbId
    //             ))) {

    //             $unitdata = $unit_select->fetch(PDO::FETCH_ASSOC);
    //             if ($unitdata !== false) {
    //                 $unit_update = $this->pdoDBhandle->prepare(
    //                     'UPDATE units SET laststate=:laststate WHERE id = :id');
    //                 if ($unit_update -> execute(array(
    //                     ':laststate' => json_encode($laststate),
    //                     ':id' => $unitdata['id']))) {
    //                     $myreturn = true;
    //                 }
    //             } else {
    //                 $unit_insert = $this->pdoDBhandle->prepare(
    //                     'INSERT INTO units (booklet_id, name, laststate) 
    //                         VALUES(:bookletId, :name, :laststate)');

    //                 if ($unit_insert->execute(array(
    //                     ':bookletId' => $bookletDbId,
    //                     ':name' => $unit,
    //                     ':laststate' => json_encode($laststate)
    //                     ))) {
    //                         $myreturn = true;
    //                 }
    //             }
    //         }
    //     }
    //     return $myreturn;
    // }

 

    // °\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/
    // the caller should check $this->pdoDBhandle and try/catch
    private function findOrAddUnit($bookletDbId, $unitname) {
        $standard_unit_laststate = ['restorepoint' => '', 'presentation_complete' => ''];

        $myreturn = 0;
        $unit_select = $this->pdoDBhandle->prepare(
            'SELECT units.id FROM units
                WHERE units.name = :unitname and units.booklet_id = :bookletId');
            
        if ($unit_select->execute(array(
            ':unitname' => $unitname,
            ':bookletId' => $bookletDbId
            ))) {
            
            $unitdata = $unit_select->fetch(PDO::FETCH_ASSOC);
            if ($unitdata === false) {
                $unit_insert = $this->pdoDBhandle->prepare(
                    'INSERT INTO units (booklet_id, name, laststate) 
                        VALUES(:bookletId, :name, :laststate)');
        
                if ($unit_insert->execute(array(
                    ':bookletId' => $bookletDbId,
                    ':name' => $unitname,
                    ':laststate' => json_encode($standard_unit_laststate)
                    ))) {
                        $myreturn = $this->pdoDBhandle->lastInsertId();;
                }
            } else {
                $myreturn = $unitdata['id'];
            }
        }

        return $myreturn;
    }

    // °\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/
    public function newRestorePoint($bookletDbId, $unitname, $restorepoint, $time) {
        $myreturn = false;
        if ($this->pdoDBhandle != false) {
            try {
                $this->pdoDBhandle->beginTransaction();
                $unitDbId = $this->findOrAddUnit($bookletDbId, $unitname);
                $unit_select = $this->pdoDBhandle->prepare(
                    'SELECT units.restorepoint_ts FROM units
                        WHERE units.id=:unitId');
                $unit_select->execute(array(':unitId' => $unitDbId));
                $unitdata = $unit_select->fetch(PDO::FETCH_ASSOC);
                if ($unitdata['restorepoint_ts'] < $time) {
                    $unit_update = $this->pdoDBhandle->prepare(
                        'UPDATE units SET restorepoint=:rp, restorepoint_ts=:rp_ts WHERE id = :unitId');
                    if ($unit_update -> execute(array(
                        ':rp' => $restorepoint,
                        ':rp_ts' => $time,
                        ':unitId' => $unitDbId))) {
                        $myreturn = true;
                    }
                }
                $this->pdoDBhandle->commit();
                $myreturn = true;
            } 

            catch(Exception $e){
                $this->pdoDBhandle->rollBack();
            }
        }
        return $myreturn;
    }

    // °\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/°\o/
    public function newResponses($bookletDbId, $unitname, $responses, $responseType, $time) {
        $myreturn = false;
        if ($this->pdoDBhandle != false) {
            try {
                $this->pdoDBhandle->beginTransaction();
                $unitDbId = $this->findOrAddUnit($bookletDbId, $unitname);
                $unit_select = $this->pdoDBhandle->prepare(
                    'SELECT units.responses_ts FROM units
                        WHERE units.id=:unitId');
                    
                $unit_select->execute(array(':unitId' => $unitDbId));
                $unitdata = $unit_select->fetch(PDO::FETCH_ASSOC);
                if ($unitdata['responses_ts'] < $time) {
                    $unit_update = $this->pdoDBhandle->prepare(
                        'UPDATE units SET responses=:r, responses_ts=:r_ts, responsetype=:rt WHERE id = :unitId');
                    if ($unit_update -> execute(array(
                        ':r' => $responses,
                        ':r_ts' => $time,
                        ':rt' => $responseType,
                        ':unitId' => $unitDbId))) {
                        $myreturn = true;
                    }
                }
                $this->pdoDBhandle->commit();
                $myreturn = true;
            } 

            catch(Exception $e){
                $this->pdoDBhandle->rollBack();
            }
        }

        return $myreturn;
    }

    // =================================================================
    public function addUnitLog($unitDbId, $logentry, $time) {
        $myreturn = false;
        if (($this->pdoDBhandle != false) && ($unitDbId > 0)) {
            $unitlog_insert = $this->pdoDBhandle->prepare(
                'INSERT INTO unitlogs (unit_id, logentry, timestamp) 
                    VALUES(:unitId, :logentry, :timestamp)');

            if ($unitlog_insert->execute(array(
                ':unitId' => $unitDbId,
                ':logentry' => $logentry,
                ':timestamp' => $time
                ))) {

                $myreturn = true;
            }
        }

        return $myreturn;
    }
}

?>