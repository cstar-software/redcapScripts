<?php

// https://github.com/ABCD-STUDY/nih-ipad-app-end-point

require("import.php");
 
/*
curl -F "action=test" http://localhost/~ryanjoseph/redcap/receive.php
curl -F "action=test" -F "upload=@test.csv" http://localhost/~ryanjoseph/redcap/receive.php
curl -F "action=test" -F "upload[]=@test.csv" -F "upload[]=@test2.csv" http://localhost/~ryanjoseph/redcap/receive.php
curl -F "action=store" -F "upload=@test.csv" http://localhost/~ryanjoseph/redcap/receive.php

curl -F "action=test" -F "upload=@test.csv" http://thealchemistguild.com/nih/receive.php
curl -F "action=store" -F "upload=@test.csv" http://thealchemistguild.com/nih/receive.php
curl --user "test" -F "action=test" http://thealchemistguild.com/nih/receive.php

*/

function post_message(int $error_code, string $message): void {
  $args = array(
    'error' => $error_code,
    'message' => $message,
  );
  $json = json_encode($args, JSON_PRETTY_PRINT);
  print($json."\n");
}

function fatal_error(int $code, string $message): void {
  post_message($code, $message);
  die;
}

function upload_temp_files(): array {
  // TODO: we need to process $_FILES['upload']['error'] and check for errors
  // UPLOAD_ERR_OK is what we need to look for
  if (is_array($_FILES['upload']['tmp_name'])) {
    return $_FILES['upload']['tmp_name'];
  } else {
    return array($_FILES['upload']['tmp_name']);
  }
}

if (isset($_POST['action'])) {
  $action = $_POST['action'];
  if ($action != 'test' && $action != 'store') {
    fatal_error(-1, "invalid action '$action'.");
  }
} else {
  fatal_error(-1, "no action specified.");
}

$count = 0;
$files = upload_temp_files();
// print_r($files);
foreach ($files as $file) {
  if (file_exists($file)) {
    if ($action == 'store') {
      if (parse_and_upload($file, true)) $count += 1;
    } else {
      $count += 1;
    }
  }
}

if ($count == 0) {
  post_message(1, 'no files attached to upload');
} else {
  // TODO: this isn't the format the NIH toolbox expects
  // I rememeber seeing there was json response
  // which contained the number of files uploaded but I don't
  // remember where now
  if ($action == 'store') {
    if ($count > 1) {
      post_message(0, "Info: $count files stored");
    } else {
      post_message(0, 'Info: file stored');
    }
  } else {
    post_message(0, 'ok');
  }
}

?>