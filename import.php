<?php

// https://www.youtube.com/watch?v=i2Pl90MUBkE&frags=pl%2Cwn

require("RestCallRequest.php");

function read_cvs(string $path, int $columns): array {
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

// returns all rows with the instrument name
function find_instrument_rows(array $cvs, array $columns, string $name): ?array {
  $column = $columns['Inst'];
  $rows = array();
  foreach ($cvs as $row) {
    if ($row[$column] == $name) {
      array_push($rows, $row);
    }
  }
  return $rows;
}

// NIH toolbox instrument to Redcap field map
$instruments = array(
  'NIH Toolbox Flanker Inhibitory Control and Attention Test Age 12+ v2.1' => 'flanker',
  'NIH Toolbox Dimensional Change Card Sort Test Age 12+ v2.1' => 'dccs',
  'NIH Toolbox Picture Sequence Memory Test Age 8+ Form A v2.1' => 'picseq_wmtest',
  'NIH Toolbox List Sorting Working Memory Test Age 7+ v2.1' => 'listsort_wmtest',
  'NIH Toolbox Pattern Comparison Processing Speed Test Age 7+ v2.1' => 'pattcom_proc_speed',
  'NIH Toolbox Picture Vocabulary Test Age 3+ v2.1' => 'pvt',
  'Cognition Fluid Composite v1.1' => 'cog_fluid_comp',
);

// auto-generated variables which don't actually appear in Redcap
$undefined = array(
  'picseq_wmtest_mean_rt', 
  'listsort_wmtest_mean_rt', 
  'pattcom_proc_speed_mean_rt', 
  'pvt_uncorrected', 
  'pvt_mean_rt', 
  'cog_fluid_comp_mean_rt'
);

// NIH toolbox field to Redcap variable map
$scores = array(
  'Uncorrected Standard Score' => 'uncorrected',
  'Age-Corrected Standard Score' => 'agecorr',
  'National Percentile (age adjusted)' => 'natlpercent_ageadj',
  'Fully-Corrected T-score' => 'fullcorrect_t',
);

function parse_and_upload(string $path, bool $upload): bool {
  global $instruments;
  global $scores;
  global $undefined;

  $cvs = read_cvs($path, 28);
  $columns = array_flip($cvs[0]);
  $data = array();

  foreach ($instruments as $instrument_key => $instrument_var) {
    $rows = find_instrument_rows($cvs, $columns, $instrument_key);
    foreach ($rows as $instrument_row) {
      // record is always the first column
      $id = $instrument_row[0];
      foreach ($scores as $score_name => $score_var) {
        $index = $columns[$score_name];
        $var_name = $instrument_var.'_'.$score_var;
        if (!in_array($var_name, $undefined)) {
          $data[$id][$var_name] = $instrument_row[$index];
        }
      }
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
    $token = 'E5072A0366869F0509B7F7AFD4C87697';
    $endpoint = 'https://redcap.asph.sc.edu/api/';

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
    // var_dump($response);
    return true;
  } else {
    return true;
  }
}

// parse_and_upload('/Users/ryanjoseph/Sites/redcap/2019-06-14 13.57.23 Assessment Scores.csv', true);

?>