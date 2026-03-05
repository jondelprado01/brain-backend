<?php

header('Access-Control-Allow-Origin: *');
include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("../TEST/ADI_FUNCTIONS_022.PHP");
ini_set("error_reporting",E_ALL);
ini_set("display_errors",1);

$aryRequest = $_REQUEST;
$process = "";
date_default_timezone_set('Asia/Manila');

$iConRLM = Open_ILink("localhost");

$old_data_payload = isset($_POST['old_data']) ? $_POST['old_data'] : [];

if(isset($aryRequest["PROCESS_TYPE"]) && $aryRequest["PROCESS_TYPE"] != "") {
    $process = $aryRequest["PROCESS_TYPE"];
}

if ($process != "") {
     print_r(json_encode(GET_CHANGE_LOG($iConRLM, $_POST['emp_id'], $_POST['module'], $_POST['data'], $_POST['start_date'], $_POST['end_date'])));
}
else{
    print_r(json_encode(ADD_LOG($iConRLM, $_POST['payload'], $_POST['user_details'], $_POST['module'], $old_data_payload)));
}

mysqli_close($iConRLM);

function ADD_LOG($iConRLM, $payload, $user_details, $module, $old_data_payload){

    $change_id  = "";
    $iden_field = [];
    $info_field = [];
    $iden_data  = [];
    $old_data   = [];
    $new_data   = [];

    $is_multiple = false;
    $multiple_data = [];

    $iden_query = "";
    $info_query = "";

    //CHANGE MASTER TABLE
    $curdate = date('Y-m-d H:i:s');
    $query = 'INSERT INTO BRAIN.CHANGE_MASTER VALUES ("", "'.$user_details['emp_id'].'", "'.$curdate.'", "'.$module.'")';
    $result = ExecuteIQuery($query,$iConRLM);
    
    $get_id = 'SELECT LAST_INSERT_ID()';
    $get_id_res = ExecuteIQuery($get_id, $iConRLM);
    while($row = mysqli_fetch_assoc($get_id_res)){
        $change_id = $row['LAST_INSERT_ID()'];
    }
    
    //PLANNING ASSUMPTIONS - TIMEPHASED MODULE
    if(strpos($module, "time-phased instance") !== false){
        if (strpos($module, "deleted") !== false) {
            $pld_len = count($payload[0]);
            $count = 1;
            foreach ($payload[0] as $key => $value) {
                $delimiter = "";
                if ($key != "CHANGE_USER") {
                    if ($count < $pld_len) {
                        $delimiter = ",";
                    }
                    $iden_query .= '("", "'.$change_id.'", "'.$key.'", "'.$value.'")'.$delimiter;
                }
                $count++;
            }
        }
        else{
            $iden_field = ["TPI_ID", "MFG_PART_NUM", "SAP_RTE_ID"];
            $iden_data  = [$payload['data']['id'], $payload['data']['part'], $payload['data']['route']];

            $info_field = ["DATE", "NOTES", "INSTANCE_NUMBER", "PROCESS", "FYWW"];
            $old_data   = [$payload['old_data']['old_date'], $payload['old_data']['old_notes'], $payload['old_data']['old_num'], $payload['old_data']['old_process'], $payload['old_data']['old_week']];
            $new_data   = [$payload['data']['date'], $payload['data']['notes'], $payload['data']['num'], implode(",", $payload['data']['process']), $payload['data']['week']];
        }
    }
    //DEFAULT ATOM MODULE
    elseif ($module == "assign default atom") {
        $iden_field = ["ATOM_ID", "TYPE", "SITE", "ENG_NAME"];
        $iden_data  = [$payload['data']['atom_id'], $payload['data']['type'], $payload['data']['site'], $payload['data']['eng_name']];

        $info_field = ["ATOM_NAME", "RES_AREA"];
        $old_data   = [$payload['old_data']['old_atom_name'], $payload['old_data']['old_res_area']];
        $new_data   = [$payload['data']['atom_name'], $payload['data']['res_area']];
    }
    //HW OVERRIDE MODULE
    elseif (strpos($module, "hw-override") !== false) {

        if (strpos($module, "mapping") !== false) {
            //HW MAPPING - TYPE 1
            //ADD, UPDATE, DELETE (CSV PROCESS TYPE INCLUDED)
            $iden_payload = [];
            $iden_field = ["MFG_PART_NUM", "HW_TYPE", "ID", "GENPOOL", "GENPOOL_CAPACITY", "GP_HW_TYPE"];

            $iden_payload = array_map(function ($row) {
                unset($row[1], $row[2], $row[3], $row[4], $row[10]);
                return array_values($row);
            }, $payload);

            $iden_data  = $iden_payload;
            $is_multiple = true;

            $info_payload = [];
            $info_field = ["HW_NM", "EFF_START", "EFF_END", "CAPACITY", "ID"];

            $info_payload = array_map(function ($row) {
                unset($row[0], $row[5], $row[7], $row[8], $row[9], $row[10]);
                return array_values($row);
            }, $payload);

            foreach ($info_payload as $key => $info) {
                array_push($multiple_data, [
                    "old_data_arr" => (count($old_data_payload) > 0) ? $old_data_payload[$key] : [],
                    "new_data_arr" => (strpos($module, "delete")) ? [] : $info
                ]);
            }

        }
        elseif (strpos($module, "capacity override") !== false){
            //HW CAPACITY - TYPE 2
            //ADD, UPDATE, DELETE (CSV PROCESS TYPE INCLUDED)
            $iden_payload = [];
            $iden_field = ["HW_NAME", "HMS_COUNT", "HW_TYPE", "SITE_NUM", "RES_AREA", "ID"];

            $iden_payload = array_map(function ($row) {
                unset($row[1], $row[2], $row[3], $row[6]);
                return array_values($row);
            }, $payload);

            $iden_data  = $iden_payload;
            $is_multiple = true;

            $info_payload = [];
            $info_field = ["EFF_START", "EFF_END", "OVERRIDE_CAP", "ID"];

            $info_payload = array_map(function ($row) {
                unset($row[0], $row[4], $row[5], $row[6], $row[7], $row[8]);
                return array_values($row);
            }, $payload);

            foreach ($info_payload as $key => $info) {
                array_push($multiple_data, [
                    "old_data_arr" => (count($old_data_payload) > 0) ? $old_data_payload[$key] : [],
                    "new_data_arr" => (strpos($module, "delete")) ? [] : $info
                ]);
            }
        }
        elseif (strpos($module, "plan non-board") !== false) {
            //PLAN NON-BOARD - TYPE 3
            //ADD, DELETE
            $iden_field = ["SITE_NUM", "RES_AREA", "HW_TYPE_HMS", "HW_TYPE_SUS", "HW_NAME", "HMS_COUNT", "ID"];
            $iden_data  = $payload;
            $is_multiple = true;
        }
    }
    elseif (strpos($module, "non-ate") !== false) {
        
        if (strpos($module, "load/unload hours") !== false) {
            //NON-ATE TYPE 1
            $iden_payload = [];
            $iden_field = ["MFG_PART_NUM", "BOARD", "BI_HOURS", "ID"];

            $iden_payload = array_map(function ($row) {
                unset($row[3], $row[4], $row[5], $row[6], $row[7]);
                return array_values($row);
            }, $payload);

            $iden_data  = $iden_payload;
            $is_multiple = true;

            $info_payload = [];
            $info_field = ["LOAD_HOURS", "LOAD_UNLOAD", "TURNS_PER_WEEK", "ID"];

            $info_payload = array_map(function ($row) {
                unset($row[0], $row[1], $row[2], $row[6], $row[7]);
                return array_values($row);
            }, $payload);

            $old_data_payload = [];

            foreach ($payload as $key => $pld) {
                $id = $pld[8];
                $old_data = json_decode($pld[7]);
                array_push($old_data_payload, [
                    $old_data[3],
                    $old_data[4],
                    $old_data[5],
                    $id,
                ]);
            }

            foreach ($info_payload as $key => $info) {
                array_push($multiple_data, [
                    "old_data_arr" => $old_data_payload[$key],
                    "new_data_arr" => $info
                ]);
            }

        }
        elseif (strpos($module, "socket efficiency") !== false) {
            //NON-ATE TYPE 2
            $iden_payload = [];
            $iden_field = ["BOARD", "ID"];

            $iden_payload = array_map(function ($row) {
                unset($row[1], $row[2], $row[4]);
                return array_values($row);
            }, $payload);

            $iden_data  = $iden_payload;
            $is_multiple = true;

            $info_payload = [];
            $info_field = ["SOCKET_EFFICIENCY", "ID"];

            $info_payload = array_map(function ($row) {
                unset($row[0], $row[2], $row[4]);
                return array_values($row);
            }, $payload);

            $old_data_payload = [];

            foreach ($payload as $key => $pld) {
                $id = $pld[3];
                $old_data = json_decode($pld[4]);
                array_push($old_data_payload, [
                    $old_data[1],
                    $id,
                ]);
            }

            foreach ($info_payload as $key => $info) {
                array_push($multiple_data, [
                    "old_data_arr" => $old_data_payload[$key],
                    "new_data_arr" => $info
                ]);
            }
        }
        elseif (strpos($module, "capacity override") !== false) {
            //NON-ATE TYPE 3
            $iden_payload = [];
            $iden_field = ["HW_NM", "HW_TYPE", "ID", "SITE_NUM", "RES_AREA"];

            $iden_payload = array_map(function ($row) {
                unset($row[1], $row[2], $row[3], $row[4]);
                return array_values($row);
            }, $payload);

            $iden_data  = $iden_payload;
            $is_multiple = true;

            $info_payload = [];
            $info_field = ["EFF_START", "EFF_END", "OVERRIDE_CAP", "CURRENT_CAP", "ID"];

            $info_payload = array_map(function ($row) {
                unset($row[0], $row[5], $row[7], $row[8]);
                return array_values($row);
            }, $payload);

            foreach ($info_payload as $key => $info) {
                array_push($multiple_data, [
                    "old_data_arr" => (count($old_data_payload) > 0) ? $old_data_payload[$key] : [],
                    "new_data_arr" => (strpos($module, "delete")) ? [] : $info
                ]);
            }
        }
    }

    $iden_query = CONSTRUCT_IDEN_QUERY($change_id, $iden_field, $iden_data, $is_multiple);

    if (count($info_field) > 0) {
        $info_query = CONSTRUCT_INFO_QUERY($change_id, $info_field, $old_data, $new_data, $is_multiple, $multiple_data);
    }

    //CHANGE IDEN TABLE
    $insert_iden = 'INSERT INTO BRAIN.CHANGE_IDEN VALUES '.$iden_query.'';
    $insert_iden_res = ExecuteIQuery($insert_iden,$iConRLM);

    //CHANGE INFO TABLE
    if ($info_query != "") {
        $insert_info = 'INSERT INTO BRAIN.CHANGE_INFO VALUES '.$info_query.'';
        $insert_info_res = ExecuteIQuery($insert_info,$iConRLM);
    }

    return $insert_iden_res;
}

function CONSTRUCT_IDEN_QUERY($change_id, $field, $data, $is_multiple){

    $query = "";
    $field_len = count($field);
    $count = 1;

    if ($is_multiple) {
        foreach($data as $dkey => $dt){
            foreach ($field as $key => $fld) {
                $query .= '("", "'.$change_id.'", "'.$fld.'", "'.$dt[$key].'"),';
            }
        }
        $query = substr_replace($query, "", -1);
    }
    else{
        foreach ($field as $key => $fld) {
            $delimiter = "";
            if ($count < $field_len) {
                $delimiter = ",";
            }
            $query .= '("", "'.$change_id.'", "'.$fld.'", "'.$data[$key].'")'.$delimiter;
            $count++;
        }
    }

    return $query;
}

function CONSTRUCT_INFO_QUERY($change_id, $field, $old_data, $new_data, $is_multiple, $multiple_data = []){

    $query = "";
    $field_len = count($field);
    $count = 1;

    if ($is_multiple) {
        if (count($multiple_data) > 0) {
            foreach ($multiple_data as $mkey => $multi) {
                foreach ($field as $key => $fld) {
                    $old_val = (count($multi['old_data_arr']) > 0) ? $multi['old_data_arr'][$key] : '';
                    $new_val = (count($multi['new_data_arr']) > 0) ? $multi['new_data_arr'][$key] : '';

                    $query .= '("", "'.$change_id.'", "'.$fld.'", "'.$old_val.'", "'.$new_val.'"),';
                }
            }
            $query = substr_replace($query, "", -1);
        }
    }
    else{
        foreach ($field as $key => $fld) {
    
            $old_val = $old_data[$key];
            $new_val = $new_data[$key];
    
            $delimiter = "";
            if ($count < $field_len) {
                $delimiter = ",";
            }
            $query .= '("", "'.$change_id.'", "'.$fld.'", "'.$old_val.'", "'.$new_val.'")'.$delimiter;
            $count++;
        }
    }

    return $query;

}

function GET_CHANGE_LOG($iConRLM, $emp_id, $module, $data, $start_date, $end_date){

    $result = [];
    $raw_result = [];
    $target_module = "";
    $change_type = "";

    if ($module == "HW OVERRIDE TYPE-1") {
        $target_module = "mapping";
        $change_type = ['ADD', 'UPDATE', 'DELETE'];
    }
    elseif ($module == "NON-ATE TYPE-1") {
        $target_module = "burn-in load/unload hours";
        $change_type = ['UPDATE'];
    }
    
    if ($target_module != "") {
        $check_master = 'SELECT 
            CM.*,
    
            (SELECT GROUP_CONCAT(IDEN_VALUE)
            FROM BRAIN.CHANGE_IDEN 
            WHERE CHANGE_ID = CM.CHANGE_ID) AS IDEN_VALUES,
    
            (
            SELECT GROUP_CONCAT(
                CONCAT_WS("|", CHANGE_INFO_ID, CHANGE_ID, INFO_FIELD, INFO_VALUE_OLD, INFO_VALUE_NEW)
                SEPARATOR "; "
            )
            FROM BRAIN.CHANGE_INFO
            WHERE CHANGE_ID = CM.CHANGE_ID
        ) AS INFO_ROWS
    
        FROM BRAIN.CHANGE_MASTER CM
    
        WHERE CM.EMP_ID = "'.$emp_id.'"
        AND CM.MODULE LIKE "%'.$target_module.'%"
        AND CM.DATE BETWEEN "'.$start_date.'" AND "'.$end_date.'"
        AND EXISTS (
            SELECT 1 
            FROM BRAIN.CHANGE_IDEN CID 
            WHERE CID.CHANGE_ID = CM.CHANGE_ID
            AND CID.IDEN_VALUE LIKE "%'.$data.'%"
        ) ORDER BY CM.DATE DESC;';
    
        $check_master_res = ExecuteIQuery($check_master, $iConRLM);
        while($row = mysqli_fetch_assoc($check_master_res)){
            array_push($raw_result, $row);
        }
    
        if (count($raw_result) > 0) {
            $start = strtotime($start_date);
            $end   = strtotime($end_date);
        
            $dates = array();
            $current = $start;
        
            while ($current <= $end) {
                $range_val = [];
                $start_range = date('m/d/Y 06:00:00', $current);
                $end_range = date('m/d/Y 05:59:00', strtotime('+1 day', $current));
        
                foreach ($raw_result as $rkey => $raw) {
                    $sr_totime = strtotime($start_range);
                    $er_totime = strtotime($end_range);
                    $rw_totime = strtotime($raw['DATE']);
        
                    if ($rw_totime >= $sr_totime && $rw_totime <= $er_totime) {
                        if (strpos($raw['IDEN_VALUES'], $data) !== false) {
        
                            $data_key = "";
                            $split_data = explode(",", $raw['IDEN_VALUES']);
        
                            foreach ($split_data as $skey => $split) {
                                if (strpos($split, $data) !== false) {
                                    $data_key = $split;
                                    break;
                                }
                            }
        
                            $range_val[$data_key][] = $raw;
                        }
                    }
                }
                
                $date_val = $start_range." - ".$end_range;
                $dates[$date_val] = $range_val;
                $current = strtotime('+1 day', $current);
            }
    
            $dates = array_filter($dates);

            uksort($dates, function($a, $b) {

                // Extract start date (before " - ")
                list($dateA) = explode(' - ', $a);
                list($dateB) = explode(' - ', $b);

                // Convert to timestamp (MM/DD/YYYY format)
                $timeA = strtotime($dateA);
                $timeB = strtotime($dateB);

                // DESC order (latest first)
                return $timeB - $timeA;
            });

    
            $result = [
                $module => [
                    $dates
                ]
            ];
        }
    }

    return $result;
}

?>