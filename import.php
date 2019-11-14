<?php

// https://www.youtube.com/watch?v=i2Pl90MUBkE&frags=pl%2Cwn
require("RestCallRequest.php");

// NIH instruments to REDCap variable mappings
require('mappings.php');

// Assessment Data instruments to calculate mean reaction times
$patient_reaction_times = array(
  'NIH Toolbox Flanker Inhibitory Control and Attention Test Age 12+ v2.1' => 'flanker_mean_rt',
  'NIH Toolbox Dimensional Change Card Sort Test Age 12+ v2.1' => 'dccs_mean_rt',
);

function read_csv(string $path): array {
  $contents = file_get_contents($path);
  $rows = array();
  $lines = explode("\n", $contents);
  foreach ($lines as $line) {
    $line = rtrim($line);
    $parts = explode(',', $line);
    array_push($rows, $parts);
  }
  return $rows;
}

function mean(array $numbers): float {
  $total = 0;
  foreach ($numbers as $number) {
    $total += $number;
  }
  $total /= count($numbers);
  return $total;
}

// returns all rows with the instrument name
function find_instrument_rows_by_subject(array $csv, array $columns, string $name): ?array {
  $column = $columns['Inst'];
  $rows = array();
  foreach ($csv as $row) {
    if ($row[$column] == $name) {
      $subject_id = $row[0];
      $rows[$subject_id][] = $row;
    }
  }
  return $rows;
}

function calculate_reaction_times(string $path, string $target_instrument): array {
  $csv = read_csv($path);
  $pin_column = array_search("PIN", $csv[0]);
  $inst_column = array_search("Inst", $csv[0]);
  $rt_column = array_search("ResponseTime", $csv[0]);

  $times = array();
  $patients = array();

  foreach ($csv as $row) {
    if ($row[$inst_column] == $target_instrument) {
      $time = $row[$rt_column];
      $patients[$row[$pin_column]][] = $time;
    }
  }

  $results = array();
  foreach ($patients as $pin => $times) {
    $results[$pin] = mean($times);
  }

  // print_r($results);
  return $results;
}

// TODO: integrate this
function is_assessment_valid(string $assessment, int $number): bool {
  if (preg_match('/^NIHPart(\d+)$/', $assessment, $matches)) {
    return $matches[1] = $number + 1;
  } else {
    return false;
  }
}

function add_instrument_info(string $subject_id, array $info, array $header, array $instrument_rows, &$data): void {
  $instrument_var = $info['prefix'];
  $scores = $info['columns'];

  // find the row with the latest date
  if (count($instrument_rows) > 1) {
    $last_time = 0;
    foreach ($instrument_rows as $row) {
      $date = $row[(int)$header['DateFinished']];
      $time = strtotime($date);
      if ($time > $last_time) {
        $instrument_row = $row;
        $last_time = $time;
      }
    }
  } else {
    $instrument_row = $instrument_rows[0];
  }

  foreach ($scores as $score_name => $score_var) {
    $column_index = $header[$score_name];
    $row_value = null;
    // if the column index is invalid this means we can't find the
    // column name in the csv file and we may have a function that needs parsing
    if (!$column_index) {
      if (preg_match('/^function => (.*)/', $score_name, $matches)) {
        $func = $matches[1];
        // ***NOTICE***!! we're going to do some magic and inject PHP code
        // which is going to be evaluated in real-time.
        // names with the $(X) format will be expanded to column data from the current row
        
        // 1) replace the $() variables with actual variable that contains the data
        $func = preg_replace('/\$\((.*?)\)/', '$instrument_row[$header[\'$1\']]', $func);

        // 2) evaluate the statement and assign results to the final row value
        eval("\$row_value = $func;");
      } else {  
        // the header name doesn't exist so just provide an empty value
        $row_value = "";
      }
    } else {
      $row_value = $instrument_row[$column_index];
    }
    $var_name = $instrument_var.'_'.$score_var;
    $data[$subject_id][$var_name] = $row_value;
  }
}

function parse_assessment_scores(string $path, bool $upload): bool {
  global $instruments;

  // process assessment scores
  $csv = read_csv($path);

  // the first row is the header with column names
  $header = array_flip($csv[0]);
  $data = array();

  foreach ($instruments as $instrument_key => $instrument_info) {
    $rows = find_instrument_rows_by_subject($csv, $header, $instrument_key);
    foreach ($rows as $subject_id => $instrument_rows) {

      // TODO: multiple parts are deprecated now
      // instrument has multiple parts
      // if ($instrument_info['parts']) {
      //   for ($i=0; $i < count($instrument_info['parts']); $i++) { 
      //     add_instrument_info($subject_id, $i, $instrument_info['parts'][$i], $header, $instrument_rows, $data);
      //   }
      // } else {
      //   add_instrument_info($subject_id, 0, $instrument_info, $header, $instrument_rows, $data);
      // }

      add_instrument_info($subject_id, $instrument_info, $header, $instrument_rows, $data);
    }
  }

  // reformat dict as array and add subject_id
  // SUBJECT_ID = array[PART1, PART2]
  $list = array();
  foreach ($data as $key => $value) {
    $part = $value;
    $part['subject_id'] = $key;
    array_push($list, $part);
  }

  $data = json_encode($list);

  if ($upload) {
    return upload_json($data);
  } else {
    // print_r($data);
    print_r($list);
    return true;
  }
}

function parse_assessment_data(string $path, bool $upload): bool {
  global $patient_reaction_times;
  $data = array();

  // caluclate mean reaction times on assessment data
  foreach ($patient_reaction_times as $test => $var_name) {
    $reaction_times = calculate_reaction_times($path, $test);
    foreach ($reaction_times as $id => $average) {
      $data[$id][$var_name] = $average;
    }
  }

  // reformat dict as array with record id
  $list = array();
  foreach ($data as $key => $value) {
    $value['subject_id'] = $key;
    array_push($list, $value);
  }

  $data = json_encode($list);

  if ($upload) {
    return upload_json($data);
  } else {
    print_r($data);
    return true;
  }
}

function upload_json(string $data): bool {
  // $token = 'E5072A0366869F0509B7F7AFD4C87697';
  // $endpoint = 'https://redcap.asph.sc.edu/api/';

  $token = '14CDB83A08909A8495DF84537B542B45';
  $endpoint = 'https://redcap.healthsciencessc.org/api/';

  $params = array(
    'token' => $token,
    'content' => 'record',
    'format' => 'json',
    'type' => 'flat',
    'overwriteBehavior' => 'normal',
    'data' => $data,
    'returnContent' => 'count',
    'returnFormat' => 'json',
  );

  $request = new RestCallRequest($endpoint, 'POST', $params);
  $request->execute();
  $response = $request->getResponseBody();
  $response = json_decode($response, true);
  if ($response['error']) {
    // if we're on the server then print errors to a log file
    // otherwise print to stdout when testing locally
    if ($_SERVER['REMOTE_ADDR']) {
      file_put_contents('errors.txt', $response);
    } else {
      print_r($response); 
    }
    return false;
  } else {
    return true;
  }
}
// calculate_reaction_times("/Users/ryanjoseph/Desktop/Work/redcapScripts/test_new/2019-08-19 10.42.39 Assessment Data.csv", "NIH Toolbox Flanker Inhibitory Control and Attention Test Age 12+ v2.1");

// if (parse_assessment_scores('/Users/ryanjoseph/Downloads/2019-09-25 10.37.12 Assessment Scores.csv', false)) {
//   print("success!\n");
// }

?>