# Changelog & Upgrade Information

## 12.3.3
### Bugfixes
* (#239, #238) Fix file reading issues in initialization

## 12.3.0
### Bugfixes
* (#366) Fix: In live-mode the group-monitor didn't update when Testtakers.XMLs get updated or deleted.

### Result-Data / Group-Monitor 
* (#231) Logins of the same name (created with `hot-run-restart`-mode) get now a number into there display-name to be 
 distinguishable. In result/log-data export, this number is stored in the field `code`. 

## 12.2.3
### Bugfixes
* Fix critical bug in communication between broadcasting-service and backend

## 12.2.1
Set a maximum for filenames in workspace of 120 characters.

## 12.0.2
Fixes data-migration from versions before 12.0.0. With the update to version 12.0.0 the way, response-data is stored
changed. Data from existing units should be migrated, but that might fail in some installations. With this patch
12.0.2 this state will be repaired and the remaining data will get migrated.


## 12.0.1
* Timeout for admin sessions was extended to 10h (from 30min)

### Bugfixes:
* Wrong numbers in Results overview
* Handle bogus Player-Metadata
 

## 12.0.0
This update makes the Teststudio Verona3- and 4 compatible.

### Endpoints
* the responses-output from `/workspace/{ws_id}/responses` and `/workspace/{ws_id}/report/response` 
  now contains the chunk names. eg: `{"all":"{\"key\": \"value\"}"` instead of `{\"key\": \"value\"}`
* new Endpoint `/{auth_token}/resource/{resource_name}` is an alternative way for fetching resources. It can be used as
  `directDownloadUrl`-parameter (see [here](https://verona-interfaces.github.io/player/#operation-publish-vopStartCommand))
  in Verona4-players. 
* Those deprecated endpoints are removed
  * `[GET] /workspace/{ws_id}/logs`
  * `[GET] /workspace/{ws_id}/reviews`
  * `[GET] /workspace/{ws_id}/responses`
  * `[GET] /workspace/{ws_id}/sys-check/reports`

### XSD
* in the `Booklet.xml`-format a new restriction is allowed: `<DenyNavigationOnIncomplete>`. It forbids the leaving of  
  units of a testlet under certain circumstances: if the unit was not presented oder responded completely. The attributes 
  `presentation` and `response` may have the values `OFF`, `ON` and `ALWAYS`. Always tells the testcenter, to check
  the completeness and response-progress everytime the unit shall be left regardless of teh direction. `ON` only checks
  if the testee want to proceed forwards.
* The `Booklet.xsd` now validates correctly that `<unit>`-id must only be unique if no alias is set and otherwise the
  alias must be unique.  

### Database
* The unit-data now gets stored in an additional table `test_data`, not in `tests` anymore to allow chunkwise updates. 
  There will be a data-migration, but depending on the specific format of the player it can be possible, that 
  previously edited units will not be restored correctly. 
* See `scripts/sql-schema/patches.mysql.d/12.0.0`.

## 11.6.0
This update refactors the CSV-output for various data: logs, reviews, test-results and sys-check-reports. 
The CSVs can now all be generated in the backend and retrieved via analogous endpoints. The data is also available 
as JSON. All CSVs contain BOMs now. 

### Endpoints
* The four new endpoints for retrieving reports: 
  * `[GET] /workspace/{ws_id}/report/log`
  * `[GET] /workspace/{ws_id}/report/review`
  * `[GET] /workspace/{ws_id}/report/response`
  * `[GET] /workspace/{ws_id}/report/sys-check`
* The old ones are now deprecated and will be removed soon:
  * `[GET] /workspace/{ws_id}/logs`
  * `[GET] /workspace/{ws_id}/reviews`
  * `[GET] /workspace/{ws_id}/responses`
  * `[GET] /workspace/{ws_id}/sys-check/reports`


## 11.5.0
Fixes some issues in the file-management.

## 11.2.0
Adds the missing second endpoint for the customization-module.
### Endpoints
* contains the new endpoint `[PATCH] /system/config/custom-texts`, which updates the key-value-store for the frontend analogous to customTexts.


## 11.1.0
This update provides the API for the customziation-module.   

### Endpoints
* contains the new endpoint `[PATCH] /system/config/app`, which updates the key-value-store for the frontend analogous to customTexts.
* `[GET] /system/config` provides the key-value store 'app-config' as well.
### Database
* See `scripts/sql-schema/patches.mysql.d/11.1.0`

## 11.0.0
This update contains various changes around the improved Group-Monitor.
### Endpoints
* A new endpoint `[GET] /system/time` was added to retrieve the server's time and time zone.
* A new endpoint where added: `/monitor/group/{group_name}/tests/unlock`
* A new endpoint was added: `[POST] /test/{test_id}/connection-lost`. It can be triggered by a closing browser as well
  as from the broadcasting-service to notify a lost connection to the testController. Note: This endpoint does not
  need any credentials.
### Database
* See `scripts/sql-schema/patches.mysql.d/11.0.0`

## 10.0.0
This update does not contain new functionality. It's about the init/install script, which can do database-migration from
older to newer versions by itself now. The version 10 indicates the beginning of an era with versioned database-schemas.
There is no manual patching necessary anymore after an update. So changes in the DB does not force a new major-version
anymore.

## 9.2.0
### XSD
* Additional elements and attributes needed by teststudio-lite where added. They have no affect for the testcenter at
the moment.

## 9.1.0
### Endpoints
* You can now insert an optional parameter `/alias/{alias}` in the end to obtain data if unit is defined with
an alias in the booklet. This is an HotFix for https://github.com/iqb-berlin/testcenter-frontend/issues/261.

## 9.0.0
The main content of this update is a complete refactoring of the (XML-)File-classes,
Workspace validation and XML-File-Handling. The main goal was to keep validity and
consistency of the workspaces. The refactoring shall also allow more and deeper validation
checks, update scripts and more in the future. The whole part of the software is now backed
with unit-tests galore.
### Requirements
* **PHP 7.4 is now required**
### Endpoints
* The `[GET] /workspace/{id}/validation` endpoint **was removed completely**.
  Validation takes now place on file upload and on `[GET] /workspace/{id}/files`.
* Return-Values and Status-Codes of `[POST] /workspace/{id}/file`
  and `[GET] /workspace/{id}/files` where changed **significantly** to contain the
  file's validation information as well as some metadata to display in the frontend.
### XML
* XML-files without a reference to a XSD-Schema generate a warning now. Currently, 
  the reference can only be done with the `noNamespaceSchemaLocation`-tag! 
* Player-Metadata as defined in [verona2](https://github.com/verona-interfaces/player/blob/master/api/playermetadata.md)
  is supported now.
### Config
* `config/system.json` contains a new (optional) value: `allowExternalXMLSchema` 
  (boolean, defaults to true) . It defines wether the program is allowed to fetch
  XSD schemas from external URLs.

## 8.0.0
The role `monitor-study` / `workspaceMonitor` was removed completely and all functions and endpoints depending on it.
### XML
* Mode `monitor-study` was removed from the `mode`-attribute
### Endpoints
* The following endpoints where removed
* `[PATCH] /{ws_id}/tests/unlock`
* `[PATCH] /{ws_id}/tests/lock`
* `[GET] /{ws_id}/status`
* `[GET] /{ws_id}/booklets/started`

## 7.4.0
### XML
* A new mode for logins is allowed now: `run-demo`

## 7.0.0
### Endpoints
* Log- and State-Endpoints
  * `[patch] \test\{test_id}\state`
  * `[put] \test\{test_id}\log`
  * `[patch] \test\{test_id}\unit\{unit_name}\state`
  * `[put] \test\{test_id}\unit\{unit_name}\log`  
    were changed:
  * They all take items in the form
  ```
  [
    {
       "key": __my_key__,
       "content": __my_content__,
       "timeStamp": 1234567891
    }
  ]
  ```
  * A state change automatically whites a log now.
* `Timestamp` parameter in various endpoints is now `timeStamp` to resemble the Verona 2 Standard

## 6.1.0
### Database
* You have to apply database structure changes,
  see `scripts/sql-schema/patches.mysql.sql`

## 6.0.0
* Hint: Sample Data/Player is still not supporting Verona 2.0 Interface,
  although compatible frontend version expect them!

## 5.0.3
### Config
* You have to manipulate the contents of `config/system.json`: You need now two parameters
  `broadcastServiceUriPush` and `broadcastServiceUriSubscribe` instead of just `broadcastServiceUri`.

## 4.0.0
Introduced the group-monitor for the frist time.
### XML
#### Testtakers
- `name`-attribute of `<group>`-element is now called `<id>`
- introduced optional attribute `label` for `<group>`-element
- in `<Metadata>`-element, only the optional `<Description>` field remains
#### Booklet
- changed defintion of `<Testlet>`-element to get rid of a warning,
  that `<Unit>` was not allowed in some legal constellations
- `id`-attribute is now mandatory for testlets
- `<Units>`-element can not contain `id` or `label` (since it won't be
  visible anywhere anyway), and first `<Restrictions>` can not contain
  `<CodeToEnter>`, which would not make any sense
- Made `<Restriction>` more readable: generic `parameter`-paremater is
  now renamed to `minutes` in context of `<TimeMax>` and to `code` for
  `<CodeToEnter>`-element.
- in `<Metadata>`-element, the elements `<ID>` and `<Label>` are mandatory,
  and `<Description>` is optional, the rest does not exist anymore.
#### SysCheck
- in `<Metadata>`-element, the elements `<ID>` and `<Label>` are mandatory,
  and `<Description>` is optional, the rest does not exist anymore.
#### Unit
- in `<Metadata>`-element, the elements `<ID>` and `<Label>` are mandatory,
  and `<Description>` is optional, the rest does not exist anymore.



