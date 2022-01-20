<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);


class SessionDAO extends DAO {


    // TODO unit-test
    public function getToken(string $tokenString, array $requiredTypes): AuthToken {

        $tokenInfo = $this->_(
            'select
                    *
                from (
                    select
                        admin_sessions.token,
                        users.id,
                        \'admin\' as "type",
                        -1 as "workspaceId",
                        case when (users.is_superadmin) then \'super-admin\' else \'admin\' end as "mode",
                        valid_until as "validTo",
                        \'[admins]\' as "group"
                    from admin_sessions
                        inner join users on (users.id = admin_sessions.user_id)
                    union
                    select
                        token,
                        login_sessions.id as "id",
                        \'login\' as "type",
                        logins.workspace_id as "workspaceId",
                        logins.mode,
                        logins.valid_to as "validTo",
                        logins.group_name as "group"
                    FROM login_sessions
                        inner join logins on (logins.name = login_sessions.name)
                    union
                    select
                        person_sessions.token,
                        person_sessions.id as "id",
                        \'person\' as "type",
                        logins.workspace_id as "workspaceId",
                        logins.mode,
                        person_sessions.valid_until as "validTo",
                        logins.group_name as "group"
                    from person_sessions
                        inner join login_sessions on (person_sessions.login_sessions_id = login_sessions.id)
                        inner join logins on (logins.name = login_sessions.name)
                ) as allTokenTables
            where 
                token = :token',
            [':token' => $tokenString]
        );

        if ($tokenInfo == null) {

            throw new HttpError("Invalid token: `$tokenString`", 403);
        }

        if (!in_array($tokenInfo["type"], $requiredTypes)) {

            throw new HttpError("Token `$tokenString` of "
                . "type `{$tokenInfo["type"]}` has wrong type - `"
                . implode("` or `", $requiredTypes) . "` required.", 403);
        }

        TimeStamp::checkExpiration(0, TimeStamp::fromSQLFormat($tokenInfo['validTo']));

        return new AuthToken(
            $tokenInfo['token'],
            (int) $tokenInfo['id'],
            $tokenInfo['type'],
            (int) $tokenInfo['workspaceId'],
            $tokenInfo['mode'],
            $tokenInfo['group']
        );
    }


    public function updateLoginSource(int $workspaceId, string $source, LoginArray $logins): void {

        $this->deleteLoginSource($workspaceId, $source);
        $this->addLoginSource($workspaceId, $source, $logins);
    }


    public function addLoginSource(int $workspaceId, string $source, LoginArray $logins): int {

        foreach ($logins as $login) {

            $this->createLogin($login, $workspaceId, $source);
        }
        return count($logins->asArray());
    }


    public function createLogin(Login $login, int $workspaceId, string $source): void {

        $this->_('insert into logins 
                 (
                     name,
                     mode,
                     workspace_id,
                     codes_to_booklets,
                     group_name,
                     group_label,
                     custom_texts,
                     password,
                     source,
                     valid_from,
                     valid_to,
                     valid_for
                 ) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $login->getName(),
                $login->getMode(),
                $workspaceId,
                json_encode($login->getBooklets()),
                $login->getGroupName(),
                $login->getGroupLabel(),
                json_encode($login->getCustomTexts()),
                $login->getPassword(),
                $source,
                TimeStamp::toSQLFormat($login->getValidFrom()),
                TimeStamp::toSQLFormat($login->getValidTo()),
                $login->getValidForMinutes()
            ]
        );
    }


    public function deleteLoginSource(int $workspaceId, string $source): int {

        $this->_(
            'delete from logins where source = :source and workspace_id = :ws_id',
            [
                ':source' => $source,
                ':ws_id' => $workspaceId
            ]
        );
        return $this->lastAffectedRows;
    }


    public function getLoginsByGroup(string $groupName, int $workspaceId): array {

        $logins = [];

        $result = $this->_(
            'SELECT 
                    logins.name,
                    logins.mode,
                    logins.group_name,
                    logins.group_label,
                    logins.codes_to_booklets,
                    logins.custom_texts,
                    logins.password,
                    logins.valid_for,
                    logins.valid_to,
                    logins.valid_from,
                    logins.workspace_id,
                    login_sessions.id, 
                    login_sessions.token
                FROM 
                    logins
                    left join login_sessions on (logins.name = login_sessions.name)
                WHERE 
                    logins.group_name = :group_name and logins.workspace_id = :workspace_id',
            [
                ':group_name' => $groupName,
                ':workspace_id' => $workspaceId
            ],
            true
        );

        foreach ($result as $row) {
            $logins[] =
                new LoginSession(
                    (int) $row["id"],
                    $row["token"],
                    new Login(
                        $row['name'],
                        '',
                        $row['mode'],
                        $row['group_name'],
                        $row['group_label'],
                        JSON::decode($row['codes_to_booklets'], true),
                        (int) $row['workspace_id'],
                        TimeStamp::fromSQLFormat($row['valid_to']),
                        TimeStamp::fromSQLFormat($row['valid_from']),
                        (int) $row['valid_for'],
                        JSON::decode($row['custom_texts'])
                    )
                );
        }

        return $logins;
    }


    public function getOrCreateLoginSession(string $name, string $password): ?LoginSession {

        $loginSession = $this->getLoginSession($name, $password);

        if (!$loginSession){

            return null;
        }

        if (!$loginSession->getToken() or Mode::hasCapability($loginSession->getLogin()->getMode(), 'alwaysNewSession')) {

            $loginSession = $this->createLoginSession($loginSession->getLogin());
        }

        return $loginSession;
    }


    public function createLoginSession(Login $login, bool $allowExpired = false): LoginSession {

        if (!$allowExpired) {
            TimeStamp::checkExpiration($login->getValidFrom(), $login->getValidTo());
        }

        $loginToken = $this->randomToken('login', $login->getName());

        $this->_(
            'INSERT INTO login_sessions (token, name, workspace_id, group_name)
                VALUES(:token, :name, :ws, :group_name)',
            [
                ':token' => $loginToken,
                ':name' => $login->getName(),
                ':ws' => $login->getWorkspaceId(),
                ':group_name' => $login->getGroupName()
            ]
        );

        return new LoginSession(
            (int) $this->pdoDBhandle->lastInsertId(),
            $loginToken,
            $login
        );
    }


    // TODO unit-test
    public function getLoginSession($name, $password): ?LoginSession {

        $loginSession = $this->_(
            'SELECT 
                    login_sessions.id,
                    login_sessions.token,             
                    logins.name,
                    logins.mode,
                    logins.group_name,
                    logins.group_label,
                    logins.codes_to_booklets,
                    logins.workspace_id,
                    logins.valid_to,
                    logins.valid_from,
                    logins.valid_for,
                    logins.custom_texts,
                    logins.password
                FROM 
                    logins
                    left join login_sessions on (logins.name = login_sessions.name)
                WHERE 
                    logins.name = :name',
            [
                ':name' => $name
            ]
        );

        // we always check one password to not leak the existence of username to time-attacks
        if (!$loginSession) {
            $loginSession = ['password' => 'dummy'];
        }

        if (!Password::verify($password, $loginSession['password'], 't')) {
            return null;
        }

        TimeStamp::checkExpiration(
            TimeStamp::fromSQLFormat($loginSession['valid_from']),
            TimeStamp::fromSQLFormat($loginSession['valid_to'])
        );

        return new LoginSession(
            (int) $loginSession['id'],
            $loginSession['token'],
            new Login(
                $loginSession['name'],
                '',
                $loginSession['mode'],
                $loginSession['group_name'],
                $loginSession['group_label'],
                JSON::decode($loginSession['codes_to_booklets'], true),
                (int) $loginSession['workspace_id'],
                TimeStamp::fromSQLFormat($loginSession['valid_to']),
                TimeStamp::fromSQLFormat($loginSession['valid_from']),
                (int) $loginSession['valid_for'],
                JSON::decode($loginSession['custom_texts'])
            )
        );
    }


    public function getLoginSessionByToken(string $loginToken): LoginSession {

        $loginSession = $this->_(
            'SELECT 
                    login_sessions.id, 
                    logins.name,
                    login_sessions.token,
                    logins.mode,
                    logins.group_name,
                    logins.group_label,
                    logins.codes_to_booklets,
                    login_sessions.workspace_id,
                    logins.custom_texts,
                    logins.password,
                    logins.valid_for,
                    logins.valid_to,
                    logins.valid_from
                FROM 
                    logins
                    left join login_sessions on (logins.name = login_sessions.name)
                WHERE 
                    login_sessions.token=:token',
            [':token' => $loginToken]
        );

        if ($loginSession == null){
            throw new HttpError("LoginToken invalid: `$loginToken`", 403);
        }

        TimeStamp::checkExpiration(
            TimeStamp::fromSQLFormat($loginSession['valid_from']),
            TimeStamp::fromSQLFormat($loginSession['valid_to'])
        );

        return new LoginSession(
            (int) $loginSession["id"],
            $loginSession["token"],
            new Login(
                $loginSession['name'],
                $loginSession['password'], // TODO keep this here?
                $loginSession['mode'],
                $loginSession['group_name'],
                $loginSession['group_label'],
                JSON::decode($loginSession['codes_to_booklets'], true),
                (int) $loginSession['workspace_id'],
                TimeStamp::fromSQLFormat($loginSession['valid_to']),
                TimeStamp::fromSQLFormat($loginSession['valid_from']),
                (int) $loginSession['valid_for'],
                JSON::decode($loginSession['custom_texts'])
            )
        );
    }


    public function getOrCreatePersonSession(LoginSession $loginSession, string $code, bool $renewToken = true): PersonSession {
        $person = $this->_(
            'SELECT 
                    person_sessions.id,
                    person_sessions.token,
                    person_sessions.code,
                    person_sessions.valid_until,
                    logins.group_name
                FROM logins
                    left join login_sessions on (logins.name = login_sessions.name)
                    left join person_sessions on (person_sessions.login_sessions_id = login_sessions.id)
                WHERE
                      person_sessions.login_sessions_id=:id
                  and person_sessions.code=:code',
            [
                ':id' => $loginSession->getId(),
                ':code' => $code
            ]
        );

        if ($person === null) {

            return $this->createPersonSession($loginSession, $code);
        }

        $personToken = $person['token'];
        if ($renewToken) {
            $tokenName = "{$loginSession->getLogin()->getGroupName()}_{$loginSession->getLogin()->getName()}_$code";
            $personToken = $this->renewPersonToken((int) $person['id'], $tokenName);
        }

        return new PersonSession(
            $loginSession,
            new Person(
                (int) $person['id'],
                $personToken,
                $person['code'],
                TimeStamp::fromSQLFormat($person['valid_until'])
            )
        );
    }


    public function createPersonSession(LoginSession $loginSession, string $code, bool $allowExpired = false): PersonSession {

        $login = $loginSession->getLogin();

        if (count($login->getBooklets()) and !array_key_exists($code, $login->getBooklets())) {
            throw new HttpError("`$code` is no valid code for `{$login->getName()}`", 400);
        }

        if (!$allowExpired) {
            TimeStamp::checkExpiration($login->getValidFrom(), $login->getValidTo());
        }

        $newPersonToken = $this->randomToken('person', "{$login->getGroupName()}_{$login->getName()}_$code");
        $validUntil = TimeStamp::expirationFromNow($login->getValidTo(), $login->getValidForMinutes());

        $this->_(
            'INSERT INTO person_sessions (token, code, login_sessions_id, valid_until)
            VALUES(:token, :code, :login_id, :valid_until)',
            [
                ':token' => $newPersonToken,
                ':code' => $code,
                ':login_id' => $loginSession->getId(),
                ':valid_until' => TimeStamp::toSQLFormat($validUntil)
            ]
        );

        return new PersonSession(
            $loginSession,
            new Person(
                (int) $this->pdoDBhandle->lastInsertId(),
                $newPersonToken,
                $code,
                $validUntil
            )
        );
    }


    public function getPersonSessionFromToken(string $personToken): PersonSession {

        $personSession = $this->_(
            'SELECT 
                login_sessions.id,
                logins.codes_to_booklets,
                login_sessions.workspace_id,
                logins.mode,
                logins.password,
                logins.group_name,
                logins.group_label,
                login_sessions.token,
                login_sessions.name,
                logins.custom_texts,
                logins.valid_to,
                logins.valid_from,
                logins.valid_for,
                person_sessions.id as "person_id",
                person_sessions.code,
                person_sessions.valid_until
            FROM person_sessions
                INNER JOIN login_sessions ON login_sessions.id = person_sessions.login_sessions_id
                INNER JOIN logins ON logins.name = login_sessions.name
            WHERE person_sessions.token = :token',
            [':token' => $personToken]
        );

        if ($personSession === null) {
            throw new HttpError("PersonToken invalid: `$personToken`", 403);
        }

        TimeStamp::checkExpiration(0, Timestamp::fromSQLFormat($personSession['valid_until']));
        TimeStamp::checkExpiration(
            TimeStamp::fromSQLFormat($personSession['valid_from']),
            TimeStamp::fromSQLFormat($personSession['valid_to'])
        );

        return new PersonSession(
            new LoginSession(
                (int) $personSession['id'],
                $personSession['token'],
                new Login(
                    $personSession['name'],
                    $personSession['password'],
                    $personSession['mode'],
                    $personSession['group_name'],
                    $personSession['group_label'],
                    JSON::decode($personSession['codes_to_booklets'], true),
                    (int) $personSession['workspace_id'],
                    Timestamp::fromSQLFormat($personSession['valid_to']),
                    Timestamp::fromSQLFormat($personSession['valid_from']),
                    Timestamp::fromSQLFormat($personSession['valid_for']),
                    JSON::decode($personSession['custom_texts'])
                )
            ),
            new Person(
                (int) $personSession['person_id'],
                $personToken,
                $personSession['code'] ?? '',
                TimeStamp::fromSQLFormat($personSession['valid_until'])
            )
        );
    }


    // TODO add unit-test
    public function getTestStatus(string $personToken, string $bookletName): array {

        $person = $this->getPersonSessionFromToken($personToken);

        $test = $this->_(
            'SELECT tests.laststate, tests.locked, tests.label, tests.id, tests.running FROM tests
            WHERE tests.person_id = :personid and tests.name = :bookletname',
            [
                ':personid' => $person->getPerson()->getId(),
                ':bookletname' => $bookletName
            ]
        );

        if ($test !== null) {

            return [
                'running' => (bool) $test['running'],
                'locked' => (bool) $test['locked'],
                'label' => $test['label']
            ];

        } else {

            return [
                'running' => false,
                'locked' => false,
                'label' => ""
            ];
        }
    }


    // TODO add unit-test
    public function personHasBooklet(string $personToken, string $bookletName): bool {

        $bookletDef = $this->_('
            select
                logins.codes_to_booklets,
                login_sessions.id,
                person_sessions.code
            from logins
                left join login_sessions on logins.name = login_sessions.name
                left join person_sessions on login_sessions.id = person_sessions.login_sessions_id
            where
                person_sessions.token = :token',
            [
                ':token' => $personToken
            ]
        );

        $code = $bookletDef['code'];
        $codes2booklets = JSON::decode($bookletDef['codes_to_booklets'], true);

        return $codes2booklets and isset($codes2booklets[$code]) and in_array($bookletName, $codes2booklets[$code]);
    }


    // TODO unit test
    public function ownsTest(string $personToken, string $testId): bool {

        $test = $this->_(
            'SELECT tests.locked FROM tests
                INNER JOIN person_sessions ON person_sessions.id = tests.person_id
                WHERE person_sessions.token=:token and tests.id=:testId',
            [
                ':token' => $personToken,
                ':testId' => $testId
            ]
        );

        // TODO check for mode?!

        return !!$test;
    }


    private function renewPersonToken(int $id, string $name): string {

        $newToken = $this->randomToken('person', $name);
        $this->_(
            "UPDATE person_sessions SET token = :token WHERE id = :id",
            [
                ':token' => $newToken,
                ':id'=> $id
            ]
        );

        return $newToken;
    }
}
