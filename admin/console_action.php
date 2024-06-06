<?php

use SQLiteCloud\SQLiteCloudRowset;

include_once('auth.php');
include_once('common.php');

$data = json_decode(file_get_contents('php://input'), true);
$database = $data["database"];
$sql = $data["sql"];

$r = null;
$rc = exec_sql($database, $sql);

if ($rc === false) {
    $r = ['result' => 0, 'msg' => exec_lasterror(true)];
} elseif ($rc instanceof SQLiteCloudRowset) {
    $r = ['result' => 2, 'msg' => render_console_table($rc)];
} elseif ($rc === true) {
    $r = ['result' => 1, 'msg' => 'Query succesfully executed.'];
} elseif ($rc === null) {
    $r = ['result' => 1, 'msg' => 'NULL'];
} else {
    $r = ['result' => 1, 'msg' => $rc];
}

echo json_encode($r);
