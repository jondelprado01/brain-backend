<?php

header('Access-Control-Allow-Origin: *');
include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("../TEST/ADI_FUNCTIONS_022.PHP");
ini_set("error_reporting",E_ALL);
ini_set("display_errors",1);

$aryRequest = $_REQUEST;
$requestMedthod = $_SERVER['REQUEST_METHOD'];
date_default_timezone_set('Asia/Manila');

$iConRLM = Open_ILink("localhost");

print_r(json_encode(ADD_LOG($iConRLM, $_POST['payload'], $_POST['user_details'], $_POST['module'])));

mysqli_close($iConRLM);

function ADD_LOG($iConRLM, $payload, $user_details, $module){

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
    
    //TIMEPHASED MODULE
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
    elseif ($module == "assign default atom") {
        $iden_field = ["ATOM_ID", "TYPE", "SITE", "ENG_NAME"];
        $iden_data  = [$payload['data']['atom_id'], $payload['data']['type'], $payload['data']['site'], $payload['data']['eng_name']];

        $info_field = ["ATOM_NAME", "RES_AREA"];
        $old_data   = [$payload['old_data']['old_atom_name'], $payload['old_data']['old_res_area']];
        $new_data   = [$payload['data']['atom_name'], $payload['data']['res_area']];
    }
    elseif (strpos($module, "hw-override") !== false) {
        if (strpos($module, "plan non-board") !== false) {
            $iden_field = ["SITE_NUM", "RES_AREA", "HW_TYPE_HMS", "HW_TYPE_SUS", "HW_NAME", "HMS_COUNT"];
            $iden_data  = $payload;
            $is_multiple = true;
        }
        elseif (strpos($module, "capacity override") !== false){
            if (strpos($module, "delete") !== false) {
               
            }
            else{
                //ADD ONLY
                $iden_payload = [];
                $iden_field = ["HW_NAME", "HMS_COUNT", "HW_TYPE", "SITE_NUM", "RES_AREA", "ID"];

                $iden_payload = array_map(function ($row) {
                    unset($row[1], $row[2], $row[3], $row[6]);
                    return array_values($row);
                }, $payload);

                $iden_data  = $iden_payload;
                $is_multiple = true;

                $info_payload = [];
                $info_field = ["FYWW_START", "FYWW_END", "OVERRIDE_CAP", "ID"];

                $info_payload = array_map(function ($row) {
                    unset($row[0], $row[4], $row[5], $row[6], $row[7], $row[8]);
                    return array_values($row);
                }, $payload);

                foreach ($info_payload as $key => $info) {
                    array_push($multiple_data, [
                        "old_data_arr" => [],
                        "new_data_arr" => $info
                    ]);
                }
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
                    $new_val = $multi['new_data_arr'][$key];

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

?>