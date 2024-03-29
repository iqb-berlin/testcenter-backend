const dreddHooks = require('hooks');
const fs = require('fs');
const Multipart = require('multi-part');
const streamToString = require('stream-to-string');
const { Readable } = require("stream")

const skipAfterFirstFail = false; // change this to debug
let skipTheRest = false;


const changeAuthToken = (transaction, newAuthTokenData) => {
  if (typeof transaction.request.headers['AuthToken'] === "undefined") {
      return;
  }

  let authToken = '';
  let tokenType = transaction.request.headers['AuthToken'].split(':')[0];

  switch (tokenType) {

    case 'a':
      authToken = newAuthTokenData.adminToken;
      break;
    case 'p':
      authToken = newAuthTokenData.personToken;
      break;
    case 'l':
      authToken = newAuthTokenData.loginToken;
      break;
    case 'g':
      authToken = newAuthTokenData.groupMonitorToken;
      break;
  }

  transaction.request.headers['AuthToken'] = authToken;
};


const changeBody = (transaction, changeMap) => {
  if (!transaction.request.body) {
    return;
  }

  const body = JSON.parse(transaction.request.body);

  Object.keys(changeMap).forEach(key => {
    if (typeof body[key] !== "undefined") {
      body[key] = changeMap[key];
    }
  });

  transaction.request.body = JSON.stringify(body);
};

const changeUri = (transaction, changeMap) => {
  Object.keys(changeMap).forEach(key => {
    transaction.request.uri = transaction.request.uri.replace(key, changeMap[key]);
    transaction.fullPath = transaction.fullPath.replace(key, changeMap[key]);
  });
};

dreddHooks.beforeEachValidation(function(transaction) {
    // nothing do to yet
});


dreddHooks.beforeEach(function(transaction, done) {
  // skip everything after first failed test
  if (skipTheRest && skipAfterFirstFail) {
    transaction.skip = true;
    return done();
  }

  // use virtual environment
  transaction.request.headers['TestMode'] = true;

  // inject login credentials if necessary
  switch (transaction.expected.statusCode) {
    case '200':
    case '201':
    case '207':
    case '413':
      changeAuthToken(transaction, {
        adminToken: 'static:admin:super',
        loginToken: 'static:login:test',
        personToken: 'static:person:sample_group_test_xxx',
        workspaceMonitorToken: 'static:person:sample_group_test-study-monitor_',
        groupMonitorToken: 'static:person:sample_group_test-group-monitor_'
      });
      break;
    case '400':
      changeBody(transaction, {
        password: '__totally_invalid_password__',
        code: "__invalid_code__"
      });
      changeAuthToken(transaction, {
        loginToken: 'static:login:test',
        adminToken: 'static:admin:super',
      });
      break;
    case '401':
      changeAuthToken(transaction,{});
      break;
    case '403':
      changeAuthToken(transaction,{
        adminToken: '__invalid_token__',
        loginToken: '__invalid_token__',
        personToken: '__invalid_token__',
        workspaceMonitorToken: 'static:person:sample_group_test_xxx',
        groupMonitorToken: 'static:person:sample_group_test_xxx'
      });
      changeUri(transaction, {
         '/static%3Aperson%3Asample_group_test_xxx/': '/__invalid_token__/'
      });
      break;
    case '404':
      changeAuthToken(transaction, {
        adminToken: 'static:admin:super',
        loginToken: 'static:login:test',
        personToken: 'static:person:sample_group_test_xxx',
        workspaceMonitorToken: 'static:person:sample_group_test-study-monitor_',
        groupMonitorToken: 'static:person:sample_group_test-group-monitor_'
      });
      changeUri(transaction, {
        '/workspace/1': '/workspace/13',
        '/group/sample_group': '/group/invalid_group',
        '/test/1/connection-lost': '/test/13/connection-lost',
        '/SAMPLE_UNITCONTENTS.HTM': '/not-existing-unit'
      });
      break;
    case '410':
      changeAuthToken(transaction,{
        adminToken: 'static:admin:expired_user',
        loginToken: 'static:login:test-expired',
        personToken: 'static:person:expired_group_test-expired_xxx',
        workspaceMonitorToken: 'static:person:expired_group_expired-study-monitor_',
        groupMonitorToken: 'static:person:expired_group_expired-group-monitor_'
      });
      break;
    default:
      transaction.skip = true;
      return done();
  }

  // Set Accept header
  let contentType = String(transaction.expected.headers['Content-Type']);
  let contentTypeArray = contentType.split(';', 1);
  if (contentTypeArray.length > 0) {
      transaction.request.headers['Accept'] = contentTypeArray[0];
  }

  done();
});

dreddHooks.before('specs > /workspace/{ws_id}/file > upload file > 201 > application/json', async (transaction, done) => {
  const form = new Multipart();
  form.append('fileforvo', fs.createReadStream('../sampledata/Unit.xml', 'utf-8'), {filename: 'SAMPLE_UNIT.XML'});
  transaction.request.body = await streamToString(form.stream());
  transaction.request.headers['Content-Type'] = form.getHeaders()['content-type'];
  done();
});


dreddHooks.before('specs > /workspace/{ws_id}/file > upload file > 207 > application/json', async (transaction, done) => {
  const form = new Multipart();
  form.append('fileforvo', fs.createReadStream('../sampledata/Unit.xml', 'utf-8'), {filename: 'SAMPLE_UNIT.XML'});
  transaction.request.body = (await streamToString(form.stream()))
    .replace('<Unit', '<Invalid')
    .replace('</Unit', '</Invalid');
  transaction.request.headers['Content-Type'] = form.getHeaders()['content-type'];
  done();
});

dreddHooks.before('specs > /workspace/{ws_id}/file > upload file > 413', async (transaction, done) => {
  const form = new Multipart();
  const tooBigContent = Readable.from(['x'.repeat(1024)]);
  form.append('MAX_FILE_SIZE', '512');
  form.append('fileforvo', tooBigContent, {filename: 'HUGE_FILE.XML'});
  transaction.request.body = await streamToString(form.stream());
  transaction.request.headers['Content-Type'] = form.getHeaders()['content-type'];
  done();
});

dreddHooks.beforeValidation('specs > /test/{test_id}/resource/{resource_name} > get resource by name > 200 > application/octet-stream', function(transaction, done) {
    transaction.expected.body = fs.readFileSync('../vendor/iqb-berlin/verona-player-simple/verona-player-simple-4.0.0.html').toString();
    done();
});

dreddHooks.beforeValidation('specs > /booklet/{booklet_name} > get a booklet > 200 > application/xml', (transaction, done) => {
  transaction.real.body = '';
  transaction.expected.body = '';
  done();
});

dreddHooks.beforeValidation('specs > /workspace/{ws_id}/report/log > get report of logs > 200 > text/csv;charset=UTF-8', function (transaction, done) {
    transaction.expected.body = "\uFEFF" + transaction.expected.body;
    done();
});

dreddHooks.beforeValidation('specs > /workspace/{ws_id}/report/response > get report of item responses > 200 > text/csv;charset=UTF-8', function (transaction, done) {
    transaction.expected.body = "\uFEFF" + transaction.expected.body;
    done();
});

dreddHooks.beforeValidation('specs > /workspace/{ws_id}/report/review > get report of item reviews > 200 > text/csv;charset=UTF-8', function (transaction, done) {
    transaction.expected.body = "\uFEFF" + transaction.expected.body;
    done();
});

dreddHooks.beforeValidation('specs > /workspace/{ws_id}/report/sys-check > get report of system checks > 200 > text/csv;charset=UTF-8', function (transaction, done) {
    transaction.expected.body = "\uFEFF" + transaction.expected.body;
    done();
});

dreddHooks.afterEach(function(transaction, done) {
    // die after first failure
    if (transaction.results.valid === false) {
        skipTheRest = true;
    }
    done();
});
