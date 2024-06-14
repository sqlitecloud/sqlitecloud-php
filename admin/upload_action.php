<?php

include_once('auth.php');
include_once('common.php');

function do_upload_loop()
{
    $start = $_POST["start"];
    $len = $_POST["len"];
    $end = $_POST["end"];
    $chunk = $_POST["chunk"];
    $encoding = $_POST["encoding"];

    if ($encoding == 1) {
        // https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsDataURL
        $head = "data:application/octet-stream;base64,";
        $data = substr($chunk, strlen($head));
        $chunk = base64_decode($data);
    } elseif ($encoding == 2) {
        // https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsArrayBuffer
    }

    $rc = exec_uploadblob($chunk);
    if ($rc == false) {
        echo exec_lasterror();
    } else {
        echo 0;
    }
}

function do_upload_start()
{
    $name = $_POST["name"];
    $key = isset($_POST['key']) ? $_POST["key"] : null;
    $rc = exec_uploaddatabase($name, $key);
    if ($rc == false) {
        echo exec_lasterror();
    } else {
        echo 0;
    }
}

function do_upload_end()
{
    $rc = exec_uploadblob(null);
    if ($rc == false) {
        echo exec_lasterror();
    } else {
        echo 0;
    }
}

function do_upload_abort()
{
    $rc = exec_uploadabort();
    if ($rc == false) {
        echo exec_lasterror();
    } else {
        echo 0;
    }
}

$action = $_POST["action"];
if ($action == 0) {
    return do_upload_start();
} elseif ($action == 1) {
    return do_upload_loop();
} elseif ($action == 2) {
    return do_upload_end();
} elseif ($action == 666) {
    return do_upload_abort();
}

echo "Unknown action type.";
