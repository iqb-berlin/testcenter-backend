#!/bin/bash

source integration/test-init/functions/functions.sh

echo_h1 "Original patch 12.0.0 vom Testcenter 12.0.0 might break, revised Patch 12.0.0 from 12.0.2 should fix it";

# so already installed patches can be re-installed

echo_h2 "Install Version 11";
fake_version 11.0.0
php scripts/initialize.php \
--user_name "" \
--workspace "workspace" \
--host=$MYSQL_HOST \
--port=$MYSQL_PORT \
--dbname=$MYSQL_DATABASE \
--user=$MYSQL_USER \
--password=$MYSQL_PASSWORD \
--skip_read_workspace_files=true \
--skip_db_integrity_check=true
expect_init_script_ok


echo_h2 "add some data";
echo "INSERT INTO login_sessions (name, mode, workspace_id, token, group_name) VALUES ('l', 'run-hot-return', 1, 't', 'sample_group');" | run sql
echo "INSERT INTO person_sessions (login_id, code, token) VALUES (1, 'd', 't');" | run sql
echo "INSERT INTO tests (id, name, person_id) VALUES (1, 'sample test', 1);" | run sql
echo "INSERT INTO units (name, booklet_id, laststate, responses, responsetype, responses_ts, restorepoint, restorepoint_ts) VALUES ('UNIT_1', 1, 'state', 'responses', '', 1597903000, '\"restore point\"', 1597903000);" | run sql


echo_h2 "add the problematic entry (content is NULL!)";
echo "INSERT INTO units (name, booklet_id, laststate, responses, responsetype, responses_ts, restorepoint, restorepoint_ts) VALUES ('UNIT_2', 1, 'state', null, '', 1597903000, '\"restore point\"', 1597903000);" | run sql


echo_h2 "do the bogus update";
fake_version 12.0.0
cp integration/test-init/data/broken-12.0.0-patch.sql scripts/sql-schema/mysql.patches.d/12.0.0.sql
php scripts/initialize.php \
--user_name "" \
--workspace "" \
--skip_read_workspace_files=true \
--skip_db_integrity_check=true # to maintain test's compatibility with future versions
expect_init_script_failed
expect_table_to_have_rows unit_data 0 # second part of the patch failed
expect_table_to_have_rows units 2
rm scripts/sql-schema/mysql.patches.d/12.0.0.sql


echo_h2 "In the mean time the testcenter could be used!"
echo "INSERT INTO units (name, booklet_id, laststate) VALUES ('UNIT_NEW', 1, 'state');" | run sql
# a new unit
echo "INSERT INTO unit_data (unit_id, part_id, content, ts, response_type) VALUES (3, 'partA', 'content', 123456789, 'text');" | run sql
echo "INSERT INTO unit_data (unit_id, part_id, content, ts, response_type) VALUES (3, 'partB', 'content', 123456789, 'text');" | run sql
# an update to the old one
echo "INSERT INTO unit_data (unit_id, part_id, content, ts, response_type) VALUES (1, 'partB', 'new content', 123456789, 'text');" | run sql


echo_h2 "Run the update which should fix everything";
fake_version 12.0.2
php scripts/initialize.php \
--user_name "" \
--workspace "" \
--skip_read_workspace_files=true \
--skip_db_integrity_check=true # to maintain test's compatibility with future versions
expect_init_script_ok
expect_table_to_have_rows unit_data 4
expect_table_to_have_rows units 3
expect_sql_to_return "select content from unit_data where unit_id=1" '[["new content"]]'