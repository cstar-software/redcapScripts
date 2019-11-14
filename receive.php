<?php

// https://github.com/ABCD-STUDY/nih-ipad-app-end-point

require("import.php");
 
/*
  curl -F "action=store" -F "upload[]=@2019-08-30 11.30.11 Assessment Scores.csv" http://localhost/~ryanjoseph/redcap/receive.php
*/

/*
curl -F "action=store" -F "upload=@2019-08-19 10.42.39 Assessment Data.csv" http://localhost/~ryanjoseph/redcap/receive.php
curl -F "action=store" -F "upload[]=@2019-08-19 10.42.39 Assessment Scores.csv" http://localhost/~ryanjoseph/redcap/receive.php
curl -F "action=store" -F "upload=@2019-08-19 10.42.39 Assessment Data.csv" http://batcaveusc.com/nih/receive.php
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

function get_upload_value(string $key) {
  if (is_array($_FILES['upload'][$key])) {
    return $_FILES['upload'][$key][0];
  } else {
    return $_FILES['upload'][$key];
  }
}

function backup_file($source, $dest) {
  $dir = "backup";
  if (!file_exists($dir)) mkdir($dir);
  $dest = "$dir/$dest";
  move_uploaded_file($source, $dest);
}

if ($_POST['action'] == "store") {

  // there was an error with the upload protocol
  if (get_upload_value('error') != UPLOAD_ERR_OK) {
    fatal_error(2, "upload error");
  }

  // check if the uploaded file name matches any of the requested files
  if (preg_match("/(\w+ \w+)\.csv$/", get_upload_value('name'), $matches)) {
    if ($matches[1] == "Assessment Scores") {
      if (parse_assessment_scores(get_upload_value('tmp_name'), true)) {
        post_message(0, "uploaded successfully!");
        backup_file(get_upload_value('tmp_name'), get_upload_value('name'));
        exit;
      } else {
        fatal_error(4, "parse_assessment_scores failed");
      }
    } else if ($matches[1] == "Assessment Data") {
      if (parse_assessment_data(get_upload_value('tmp_name'), true)) {
        post_message(0, "uploaded successfully!");
        backup_file(get_upload_value('tmp_name'), get_upload_value('name'));
        exit;
      } else {
        fatal_error(5, "parse_assessment_data failed");
      }
    } else if ($matches[1] == "Registration Data") {
      // nothing to do but we don't want to send an error code
      post_message(0, "ok");
      backup_file(get_upload_value('tmp_name'), get_upload_value('name'));
      exit;
    }
  } else if (get_upload_value('name') == "TestConnection.csv") {
    post_message(0, "ok");
    exit;
  }
} else {
  fatal_error(3, "only 'store' action is allowed");
}

// if get here something went wrong
fatal_error(100, "upload error");

?>