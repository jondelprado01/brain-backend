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

if ($requestMedthod != "POST") {
    exit("Invalid HTTP Method!");
}

$bolDebug = false;
if(isset($aryRequest["DEBUG"]) && $aryRequest["DEBUG"] != "") {
	$bolDebug=true;
}

$bolJSON = true;
if(isset($aryRequest["NO_JSON"]) && $aryRequest["NO_JSON"] != "") {
	$bolJSON = false;
}

$process = "set";
if(isset($aryRequest["PROCESS_TYPE"]) && $aryRequest["PROCESS_TYPE"] != "") {
	$process = $aryRequest["PROCESS_TYPE"];
}

$aryIO = array([
    'INPUT_TYPE' => [
        'MFG_PART_NUM'
    ],
    'OUTPUT_TYPE' => [
        'BODS_JDA_ADI'
    ]
]);

// $checkRequest = REQUEST_VALIDATE($aryIO, $aryRequest);
if ($process == "set") {
    print_r(json_encode(SET_PRIMARY($iConRLM, $_POST['payload'], $_POST['user_details'])));
}
elseif($process == "HARDWARE_CHANGE") {
    print_r(json_encode(PROCESS_HARDWARE_CHANGE($iConRLM, $_POST['process'], $_POST['payload']['data'], (isset($_POST['payload']['flag_data'])) ? $_POST['payload']['flag_data'] : [], $_POST['user_details'])));
}
elseif($process == "PRIMARY_HARDWARE_CHANGE") {
    print_r(json_encode(CHANGE_PRIMARY_HARDWARE($iConRLM, $_POST['payload'], $_POST['user_details'])));
}
elseif($process == "PROCESS_PENDING_HW_CHANGE") {
    print_r(json_encode(PROCESS_PENDING_HW_CHANGE($iConRLM)));
}
elseif ($process == "FLAG_SETUPS") {
    $unplannable = (isset($_POST['unplannable_data'])) ? $_POST['unplannable_data'] : [];
    $plannable = (isset($_POST['plannable_data'])) ? $_POST['plannable_data'] : [];
    print_r(json_encode(FLAG_SETUPS($iConRLM, $unplannable, $plannable)));
}
elseif ($process == "SET_DEDICATION") {
    print_r(json_encode(SET_DEDICATION($iConRLM, $_POST['payload'], $_POST['user_details'])));
}
elseif ($process == "REMOVE_DEDICATION") {
    print_r(json_encode(REMOVE_DEDICATION($iConRLM, $_POST['payload'], $_POST['primary'], $_POST['user_details'])));
}
else{
    print_r(json_encode(RESET_PRIMARY($iConRLM, $_POST['payload'])));
}

mysqli_close($iConRLM);

function SET_PRIMARY($iConRLM, $data, $user_details){
    $to_remove_arr = [];
    $aaa_id = [];
    $curdate = date('Y-m-d H:i:s');
    $aps_length = (isset($data['payload']['all_primary_setup'])) ? count($data['payload']['all_primary_setup']) : 0;
    $chp_length = (isset($data['payload']['changed_primary'])) ? count($data['payload']['changed_primary']) : 0;
    $schedule = GENERATE_SCHEDULE($curdate);

    //WILL REVAMP THIS CODE
    if ($chp_length > 0) {
        $chp_values = "";
        $chp_values_history = "";
        $chp_separator = ",";
        foreach ($data['payload']['changed_primary'] as $key => $chp) {
            if ($key == $chp_length - 1) {
                $chp_separator = "";
            }
            $to_remove = "";
            if (isset($chp['TO_REMOVE_RAW'])) {
                $to_remove = $chp['TO_REMOVE_RAW'][13].'|'.$chp['TO_REMOVE_RAW'][19];
            }

            //UPDATE PRIO_CD - FOR PRIMARY SETUP ONLY
            $current_prio = $chp['RES_PRIO_CD'];
            $string = $chp['MFG_PART_NUM'].'|'.$chp['SITE_NUM'].'|'.$chp['SAP_RTE_ID'].'|'.$chp['STEP_NM'].'|'.$chp['TEMP_CLASS'].'|'.$chp['ENG_TESTER'].'|'.$chp['ENG_HANDLER'].'|'.$chp['TESTER'].'|'.$chp['HANDLER'].'|'.$chp['ORIG_PRIO'];
            $hash = hash("sha256", $string);

            $alt_hash_arr = [];
            foreach ($chp['ALTERNATES'] as $key => $chps) {
                $expl = explode("|", $chps[15]);
                $alt_string = $expl[0].'|'.$expl[1].'|'.$expl[4].'|'.$expl[2].'|'.$chps[29].'|'.$chps[1].'|'.$chps[2].'|'.$chps[3].'|'.$chps[4].'|'.$chps[30];
                $alt_hash = hash("sha256", $alt_string);
                array_push($alt_hash_arr, $alt_hash);
            }

            if ($chp['TYPE'] == "DEFAULT") {
                $chp_values .= '("", "'.$chp['ATOM_MASTER_ID'].'", "'.$chp['MFG_PART_NUM'].'", "'.$chp['SITE_NUM'].'", 
                                 "'.$chp['STEP_NM'].'", "'.$chp['TESTER'].'", "'.$chp['HANDLER'].'", 1, 
                                 "'.$chp['HASH'].'", "'.$chp['SAP_RTE_ID'].'", "'.$chp['HW_SET_ID'].'", "'.$chp['RES_SET_ID'].'", 
                                 "'.$to_remove.'", "PENDING", "'.$curdate.'", "'.$user_details['emp_name'].'", "'.$schedule.'", "0", "'.implode('|', $alt_hash_arr).'", "'.$hash.'")'.$chp_separator.'';
                $chp_values_history .= '("", "'.$chp['ATOM_MASTER_ID'].'", "'.$chp['MFG_PART_NUM'].'", "'.$chp['SITE_NUM'].'",
                                         "'.$chp['STEP_NM'].'", "'.$chp['TESTER'].'", "'.$chp['HANDLER'].'", 1,
                                         "'.$chp['HASH'].'", "'.$chp['SAP_RTE_ID'].'", "'.$chp['HW_SET_ID'].'", "'.$chp['RES_SET_ID'].'",
                                         "'.$to_remove.'", "PENDING", "PENDING_HW_CHANGE", "'.$curdate.'", "", "", "'.$user_details['emp_name'].'", "", "")'.$chp_separator.'';
            }
            else{
                $update_ded = 'UPDATE TEST.ADI_PRIMARY_SETUP SET TO_REMOVE = "'.$to_remove.'", STATUS = "PENDING", SCHEDULE = "'.$schedule.'", ALTERNATES = "'.implode('|', $alt_hash_arr).'" WHERE DED_ID = '.$chp['DED_ID'].'';
                $update_ded_res = ExecuteIQuery($update_ded,$iConRLM);
            }
        }

        if ($chp_values != "") {
            $query = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES '.$chp_values.'';
            $query_history = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES '.$chp_values_history.'';
            $result = ExecuteIQuery($query,$iConRLM);
            $result_history = ExecuteIQuery($query_history,$iConRLM);
        }
    }

    if ($aps_length > 0) {
        $values = "";
        $values_history = "";
        $separator = ",";
        foreach ($data['payload']['all_primary_setup'] as $key => $aps) {
            
            if (isset($aps['TO_REMOVE_RAW'])) {
                $exp = explode("|", $aps['TO_REMOVE_RAW'][15]);
                $ded_id = ($aps['TO_REMOVE_RAW'][27] == 'DEDICATION') ? '|'.$aps['TO_REMOVE_RAW'][28] : '';
                $trs = $aps['TO_REMOVE_RAW'][13].'|'.$aps['TO_REMOVE_RAW'][19].'|'.$aps['TO_REMOVE_RAW'][20].$ded_id;
                array_push($to_remove_arr, $trs);

                $values_history .= '("", "'.$aps['TO_REMOVE_RAW'][25].'", "'.$exp[0].'", "'.$exp[1].'", 
                                 "'.$exp[2].'", "'.$aps['TO_REMOVE_RAW'][1].'", "'.$aps['TO_REMOVE_RAW'][2].'", 1, 
                                 "'.$exp[3].'", "'.$exp[4].'", "'.$exp[5].'", "'.$aps['TO_REMOVE_RAW'][24].'", "'.$trs.'", "'.$exp[6].'",
                                 "DELETED_NO_HW_CHANGE", "", "", "'.$curdate.'", "", "", "'.$user_details['emp_name'].'")'.$separator.'';
            }

            //UPDATE PRIO_CD - FOR PRIMARY SETUP ONLY
            $current_prio = $aps['RES_PRIO_CD'];
            if ($current_prio > 1) {
                $alt_hash_arr = [];
                $prio_arr = [];
                foreach ($aps['ALTERNATES'] as $alts) {
                    $expl = explode("|", $alts[15]);
                    $alt_string = $expl[0].'|'.$expl[1].'|'.$expl[4].'|'.$expl[2].'|'.$alts[29].'|'.$alts[1].'|'.$alts[2].'|'.$alts[3].'|'.$alts[4].'|'.$alts[30];
                    $alt_hash = hash("sha256", $alt_string);
                    array_push($alt_hash_arr, $alt_hash);
                }
    
                $get_alt_prio = 'SELECT * FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER IN ("'.implode('","', $alt_hash_arr).'") ORDER BY NEW_PRIO ASC';
                $get_alt_prio_res = ExecuteIQuery($get_alt_prio,$iConRLM);

                while($row = mysqli_fetch_assoc($get_alt_prio_res)){
                    $alt_new_prio = ($current_prio > $row['NEW_PRIO']) ? $row['NEW_PRIO'] + 1 : $row['NEW_PRIO'] - 1;
    
                    if (in_array($alt_new_prio, $prio_arr)) {
                        $alt_new_prio = $alt_new_prio + 1;
                    }
                    array_push($prio_arr, $alt_new_prio);

                    $update_alt_prio = 'UPDATE TEST.ADI_SETUP_PRIO SET NEW_PRIO = '.$alt_new_prio.', IS_PRIMARY = "NO" WHERE IDENTIFIER = "'.$row['IDENTIFIER'].'"';
                    $update_alt_prio_res = ExecuteIQuery($update_alt_prio,$iConRLM);
                }
            }

            $string = $aps['MFG_PART_NUM'].'|'.$aps['SITE_NUM'].'|'.$aps['SAP_RTE_ID'].'|'.$aps['STEP_NM'].'|'.$aps['TEMP_CLASS'].'|'.$aps['ENG_TESTER'].'|'.$aps['ENG_HANDLER'].'|'.$aps['TESTER'].'|'.$aps['HANDLER'].'|'.$aps['ORIG_PRIO'];
            $hash = hash("sha256", $string);
            $update_prio = 'UPDATE TEST.ADI_SETUP_PRIO SET NEW_PRIO = 1, IS_PRIMARY = "YES" WHERE IDENTIFIER = "'.$hash.'"';
            $update_prio_res = ExecuteIQuery($update_prio,$iConRLM);

            if ($key == $aps_length - 1) {
                $separator = "";
            }
            if ($aps['TYPE'] == "DEFAULT") {
                $values .= '("", "'.$aps['ATOM_MASTER_ID'].'", "'.$aps['MFG_PART_NUM'].'", "'.$aps['SITE_NUM'].'", 
                         "'.$aps['STEP_NM'].'", "'.$aps['TESTER'].'", "'.$aps['HANDLER'].'", 1, 
                         "'.$aps['HASH'].'", "'.$aps['SAP_RTE_ID'].'", "'.$aps['HW_SET_ID'].'", "'.$aps['RES_SET_ID'].'", 
                         "", "COMPLETED", "'.$curdate.'", "'.$user_details['emp_name'].'", "", "0", "", "'.$hash.'")'.$separator.'';

            }
            else{
                $update_ded = 'UPDATE TEST.ADI_DEDICATION SET TYPE = "PRIMARY" WHERE DED_ID = "'.$aps['DED_ID'].'"';
                $result_ded = ExecuteIQuery($update_ded,$iConRLM);
            }

            $values_history .= '("", "'.$aps['ATOM_MASTER_ID'].'", "'.$aps['MFG_PART_NUM'].'", "'.$aps['SITE_NUM'].'", 
                                    "'.$aps['STEP_NM'].'", "'.$aps['TESTER'].'", "'.$aps['HANDLER'].'", 1, 
                                    "'.$aps['HASH'].'", "'.$aps['SAP_RTE_ID'].'", "'.$aps['HW_SET_ID'].'", "'.$aps['RES_SET_ID'].'", "", "COMPLETED",
                                    "COMPLETED_NO_HW_CHANGE", "'.$curdate.'", "", "", "'.$user_details['emp_name'].'", "", "")'.$separator.'';
                                    
            array_push($aaa_id, $aps['ATOM_MASTER_ID']);
        }

        if ($values != '') {
            $query = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES '.$values.'';
            $result = ExecuteIQuery($query,$iConRLM);
        }

        $query_history = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES '.$values_history.'';
        $result_history = ExecuteIQuery($query_history,$iConRLM);
    }

    //DELETE UNPLANNABLE FLAG OF NEW PRIMARY SETUP IF IT EXISTS
    $delete_flag = 'DELETE FROM TEST.ADI_UNPLANNABLE_SETUP WHERE ATOM_MASTER_ID IN ("'.implode('","', $aaa_id).'")';
    $result_flag = ExecuteIQuery($delete_flag,$iConRLM);


    if (count($to_remove_arr) > 0) {
        foreach ($to_remove_arr as $to_remove) {
            $explode = explode("|", $to_remove);
            if (isset($explode[3])) {
                $ded_to_alternate = 'UPDATE TEST.ADI_DEDICATION SET TYPE = "ALTERNATE" WHERE DED_ID = "'.$explode[3].'"';
                $result_to_alternate = ExecuteIQuery($ded_to_alternate,$iConRLM);
            }
            else{
                $delete_query = "DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE HASH IN ('".$explode[0]."') AND HW_SET_ID IN ('".$explode[1]."') AND ID IN ('".$explode[2]."') AND DED_ID = 0";
                $delete_result = ExecuteIQuery($delete_query,$iConRLM);
            }
        }
    }
    if (count($data['all_primary_hw']) > 0) {
        CHANGE_PRIMARY_HARDWARE($iConRLM, $data, $user_details);
    }
    if (count($data['selected_hardware_prm']) > 0) {
        CHANGE_PRIMARY_HARDWARE($iConRLM, $data, $user_details);
    }

    if ((isset($data['unplannable_setups']) && count($data['unplannable_setups']) > 0) || (isset($data['plannable_setups']) && count($data['plannable_setups']) > 0)) {
        $unplannable = (isset($data['unplannable_setups'])) ? $data['unplannable_setups'] : [];
        $plannable = (isset($data['plannable_setups'])) ? $data['plannable_setups'] : [];
        FLAG_SETUPS($iConRLM, $unplannable, $plannable);
    }
}

function PROCESS_HARDWARE_CHANGE($iConRLM, $type, $data, $flag_data, $user_details){
    $values_history = "";
    $temp_arr = [];
    
    date_default_timezone_set('Asia/Manila');
    $updated_at = "";
    $updated_by = "";
    $deleted_at = "";
    $deleted_by = "";

    $get_record = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE ID = "'.$data.'"';
    $retrieve = ExecuteIQuery($get_record,$iConRLM);
    while($row = mysqli_fetch_assoc($retrieve)){
        if ($row['STATUS'] == "PENDING") {
            array_push($temp_arr, [
                $row['MFG_PART_NUM'],
                $row['SITE_NUM'],
                $row['STEP_NM'],
                $row['HASH'],
                $row['SAP_RTE_ID'],
                $row['HW_SET_ID'],
                $row['STATUS'],
                $row['TESTER'],
                $row['HANDLER'],
                $row['TO_REMOVE'],
                $row['RES_SET_ID'],
                $row['ATOM_MASTER_ID'],
                $row['ALTERNATES'],
                $row['IDENTIFIER'],
                $row['DED_ID']
            ]);
        }
    }

    switch ($type) {
        case 'cancel-change':

            if ($temp_arr[0][14] == 0) {
                $status = "";
                $state = "DELETED_PENDING_HW_CHANGE";
                $deleted_at = date('Y-m-d H:i:s');
                $deleted_by = $user_details['emp_name'];
                $query = "DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE ID = '".$data."' AND STATUS = 'PENDING'";
                $result = ExecuteIQuery($query,$iConRLM);

                if (count($flag_data) > 0) {
                    $delete_flag = "DELETE FROM TEST.ADI_UNPLANNABLE_SETUP WHERE ID = '".$flag_data."'";
                    $result_flag = ExecuteIQuery($delete_flag,$iConRLM);
                }
            }
            else{
                $update_ded = 'UPDATE TEST.ADI_PRIMARY_SETUP SET TO_REMOVE = NULL, STATUS = "COMPLETED", SCHEDULE = NULL WHERE ID = '.$data.'';
                $update_ded_res = ExecuteIQuery($update_ded,$iConRLM);
            }

            break;
        
        default: //expedite-change

            //DELETE CURRENT PRIMARY
            $query_del = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE ID = "'.$data.'"';
            $retrieve_del = ExecuteIQuery($query_del,$iConRLM);
            while($row = mysqli_fetch_assoc($retrieve_del)){
                $to_remove = explode("|", $row['TO_REMOVE']);
                $aaa_id = $row['ATOM_MASTER_ID'];
            }
            
            $get_record_delete = "SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE HASH = '".$to_remove[0]."' AND HW_SET_ID = '".$to_remove[1]."' AND ID != '".$data."' AND STATUS = 'COMPLETED'";
            $retrieve_delete = ExecuteIQuery($get_record_delete,$iConRLM);
            while($row = mysqli_fetch_assoc($retrieve_delete)){
                $values_history .= '("", "'.$row['ATOM_MASTER_ID'].'", "'.$row['MFG_PART_NUM'].'", "'.$row['SITE_NUM'].'",
                                "'.$row['STEP_NM'].'", "'.$row['TESTER'].'", "'.$row['HANDLER'].'", 1, 
                                "'.$row['HASH'].'", "'.$row['SAP_RTE_ID'].'", "'.$row['HW_SET_ID'].'", "'.$row['RES_SET_ID'].'", "'.$row['TO_REMOVE'].'", "'.$row['STATUS'].'",
                                "DELETED_HW_CHANGE", "", "", "'.date('Y-m-d H:i:s').'", "", "", "'.$user_details['emp_name'].'"),';
            }

            //UPDATE PRIO_CD FROM ALTERNATES DATA
            foreach ($temp_arr as $tarr) {
                $current_prio = "";
                $prio_arr = [];
                $alt_hash_arr = explode("|", $tarr[12]);
                $get_current_prio = 'SELECT * FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER = "'.$tarr[13].'" LIMIT 1';
                $get_current_prio_res = ExecuteIQuery($get_current_prio,$iConRLM);
                while($row = mysqli_fetch_assoc($get_current_prio_res)){
                    $current_prio = $row['NEW_PRIO'];
                }
                $get_alt_prio = 'SELECT * FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER IN ("'.implode('","', $alt_hash_arr).'") ORDER BY NEW_PRIO ASC';
                $get_alt_prio_res = ExecuteIQuery($get_alt_prio,$iConRLM);
                while($row = mysqli_fetch_assoc($get_alt_prio_res)){
                    $alt_new_prio = ($current_prio > $row['NEW_PRIO']) ? $row['NEW_PRIO'] + 1 : $row['NEW_PRIO'] - 1;
                    if (in_array($alt_new_prio, $prio_arr)) {
                        $alt_new_prio = $alt_new_prio + 1;
                    }
                    array_push($prio_arr, $alt_new_prio);

                    $update_alt_prio = 'UPDATE TEST.ADI_SETUP_PRIO SET NEW_PRIO = '.$alt_new_prio.', IS_PRIMARY = "NO" WHERE IDENTIFIER = "'.$row['IDENTIFIER'].'"';
                    $update_alt_prio_res = ExecuteIQuery($update_alt_prio,$iConRLM);
                }

                //PROCEED TO UPDATE THE PRIMARY'S PRIO_CD
                $update_prio = 'UPDATE TEST.ADI_SETUP_PRIO SET NEW_PRIO = 1, IS_PRIMARY = "YES" WHERE IDENTIFIER = "'.$tarr[13].'"';
                $update_prio_res = ExecuteIQuery($update_prio,$iConRLM);

                //UPDATE DETAILS IF THE PRIMARY IS A DEDICATION
                $update_details = 'UPDATE TEST.ADI_DEDICATION SET TYPE = "PRIMARY" WHERE DED_ID = '.$tarr[14].'';
                $update_details_res = ExecuteIQuery($update_details,$iConRLM);
            }

            // UPDATE NEW PRIMARY SET AS CURRENT
            $status = "COMPLETED";
            $state = "COMPLETED_HW_CHANGE";
            $updated_at = date('Y-m-d H:i:s');
            $updated_by = $user_details['emp_name'];
            $update_new = "UPDATE TEST.ADI_PRIMARY_SETUP SET STATUS = 'COMPLETED', TO_REMOVE = '' WHERE ID = '".$data."'";
            $res_update = ExecuteIQuery($update_new,$iConRLM);

            //DELETE UNPLANNABLE FLAG OF NEW PRIMARY SETUP IF IT EXISTS
            $delete_flag = "DELETE FROM TEST.ADI_UNPLANNABLE_SETUP WHERE ATOM_MASTER_ID = '".$aaa_id."'";
            $result_flag = ExecuteIQuery($delete_flag,$iConRLM);

            //DELETE CURRENT PRIMARY IF IT'S A DEFAULT SETUP OTHERWISE UPDATE DEDICATION
            $delete_current = "DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE HASH = '".$to_remove[0]."' AND HW_SET_ID = '".$to_remove[1]."' AND ID != '".$data."' AND STATUS = 'COMPLETED' AND DED_ID = 0";
            $res_delete = ExecuteIQuery($delete_current,$iConRLM);

            $get_ded_details = 'SELECT DED_ID, ID FROM TEST.ADI_PRIMARY_SETUP WHERE HASH = "'.$to_remove[0].'" AND HW_SET_ID = "'.$to_remove[1].'" AND ID != "'.$data.'" AND DED_ID != 0';
            $get_ded_details_res = ExecuteIQuery($get_ded_details,$iConRLM);
            $test = [];
            while($row = mysqli_fetch_assoc($get_ded_details_res)){
                $update_ded_prim = 'UPDATE TEST.ADI_PRIMARY_SETUP SET TO_REMOVE = NULL, STATUS = "COMPLETED", SCHEDULE = NULL WHERE ID = '.$row['ID'].'';
                $update_ded_alt = 'UPDATE TEST.ADI_DEDICATION SET TYPE = "ALTERNATE" WHERE DED_ID = '.$row['DED_ID'].'';
                $update_ded_prim_res = ExecuteIQuery($update_ded_prim,$iConRLM);
                $update_ded_alt_res = ExecuteIQuery($update_ded_alt,$iConRLM);
                array_push($test, $row);
            }

            break;
    }

    $status = ($status != "") ? $status : $temp_arr[0][6];
    $values_history .= '("", "'.$temp_arr[0][11].'", "'.$temp_arr[0][0].'", "'.$temp_arr[0][1].'",
                                 "'.$temp_arr[0][2].'", "'.$temp_arr[0][7].'", "'.$temp_arr[0][8].'", 1, 
                                 "'.$temp_arr[0][3].'", "'.$temp_arr[0][4].'", "'.$temp_arr[0][5].'", "'.$temp_arr[0][10].'", "'.$temp_arr[0][9].'", "'.$status.'",
                                 "'.$state.'", "", "'.$updated_at.'", "'.$deleted_at.'", "", "'.$updated_by.'", "'.$deleted_by.'")';

    $query_history = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES '.$values_history.'';
    $result_history = ExecuteIQuery($query_history,$iConRLM);

    return $temp_arr;
}

function CHANGE_PRIMARY_HARDWARE($iConRLM, $data, $user_details){
    date_default_timezone_set('Asia/Manila');
    $res_update = false;
    $deleted_at = date('Y-m-d H:i:s');
    if (count($data['selected_hardware_prm']) > 0) {
        foreach ($data['selected_hardware_prm'] as $key => $dt) {
            $values_history = "";
            $expl = explode("_", $dt['value']);
        
            $get_record_update = "SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE ID = '".$expl[1]."' AND STATUS = 'COMPLETED'";
            $retrieve_update = ExecuteIQuery($get_record_update,$iConRLM);
            while($row = mysqli_fetch_assoc($retrieve_update)){
                $values_history .= '("", "'.$row['ATOM_MASTER_ID'].'", "'.$row['MFG_PART_NUM'].'", "'.$row['SITE_NUM'].'",
                                "'.$row['STEP_NM'].'", "'.$row['TESTER'].'", "'.$row['HANDLER'].'", 1, 
                                "'.$row['HASH'].'", "'.$row['SAP_RTE_ID'].'", "'.$row['HW_SET_ID'].'", "'.$row['RES_SET_ID'].'", "'.$row['TO_REMOVE'].'", "'.$row['STATUS'].'",
                                "UPDATED_PRIMARY_HW_CURRENT", "", "'.date('Y-m-d H:i:s').'", "", "", "'.$user_details['emp_name'].'", ""),';
        
                $values_history .= '("", "'.$row['ATOM_MASTER_ID'].'", "'.$row['MFG_PART_NUM'].'", "'.$row['SITE_NUM'].'",
                                "'.$row['STEP_NM'].'", "'.$row['TESTER'].'", "'.$row['HANDLER'].'", 1, 
                                "'.$row['HASH'].'", "'.$row['SAP_RTE_ID'].'", "'.$expl[0].'",  "'.$row['RES_SET_ID'].'", "'.$row['TO_REMOVE'].'", "'.$row['STATUS'].'",
                                "UPDATED_PRIMARY_HW_NEW", "", "'.date('Y-m-d H:i:s').'", "", "", "'.$user_details['emp_name'].'", "")';
            }
        
            $query_history = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES '.$values_history.'';
            $result_history = ExecuteIQuery($query_history,$iConRLM);
        
            $update_hw = "UPDATE TEST.ADI_PRIMARY_SETUP SET HW_SET_ID = '".$expl[0]."' WHERE ID = '".$expl[1]."'";
            $res_update = ExecuteIQuery($update_hw,$iConRLM);
        }
    }
    if (count($data['all_primary_hw']) > 0) {
        $split = explode("|", $data['all_primary_hw'][0]['apply_tstr_hndlr']);
        $route = $data['all_primary_hw'][0]['apply_route_id'];
        $hardware = $data['all_primary_hw'][0]['apply_hw'];
        $tester = $split[0];
        $handler = $split[1];

        $update_apply_hw = "UPDATE TEST.ADI_PRIMARY_SETUP SET HW_SET_ID = '".$hardware."' WHERE SAP_RTE_ID = '".$route."' AND TESTER = '".$tester."' AND HANDLER = '".$handler."' AND STATUS = 'COMPLETED'";
        $res_update = ExecuteIQuery($update_apply_hw,$iConRLM);
    }
    return $res_update;
}

function RESET_PRIMARY($iConRLM, $data){
    $hash = implode("','", $data);
    $delete_query = "DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE HASH IN ('".$hash."')";
    $delete_result = ExecuteIQuery($delete_query,$iConRLM);
    return $delete_result;
}

function GENERATE_SCHEDULE($date){
    $curr_date = $date;
    $curr_day = date('l', strtotime($curr_date));
    $exec_date = '';

    if ($curr_day == 'Thursday' || $curr_day == 'Friday') {
        $get_curr_thurs = date('Y-m-d', strtotime('thursday this week'));
        $get_next_tues = date('Y-m-d', strtotime('next tuesday'));

        $deadline = strtotime(date(''.$get_curr_thurs.' 23:59:00'));
        $totime = strtotime($curr_date);

        if ($deadline > $totime) {
            $exec_date = date('Y-m-d H:i:s', strtotime('+24 hours', strtotime($curr_date)));
        }
        else{
            $curr_time = date('H:i:s', strtotime($curr_date));
            $exec_date = date('Y-m-d '.$curr_time.'', strtotime($get_next_tues));
        }
    }
    else{
        $exec_date = date('Y-m-d H:i:s', strtotime('+24 hours', strtotime($curr_date)));
    }

    return $exec_date;
}

function PROCESS_PENDING_HW_CHANGE($iConRLM){
    $result_history = "";
    $curr_date = strtotime(date('Y-m-d H:i:s'));
    $query = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE STATUS = "PENDING"';
    $result = ExecuteIQuery($query,$iConRLM);
    while($row = mysqli_fetch_assoc($result)){
        $schedule = strtotime($row['SCHEDULE']);
        if ($curr_date >= $schedule) {
            $to_remove = explode("|", $row['TO_REMOVE']);
            $update_new = "UPDATE TEST.ADI_PRIMARY_SETUP SET STATUS = 'COMPLETED', TO_REMOVE = '' WHERE ID = '".$row['ID']."'";
            $res_update = ExecuteIQuery($update_new,$iConRLM);

            $delete_current = "DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE HASH = '".$to_remove[0]."' AND HW_SET_ID = '".$to_remove[1]."' AND ID != '".$row['ID']."' AND STATUS = 'COMPLETED'";
            $res_delete = ExecuteIQuery($delete_current,$iConRLM);

            $query_history = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES ("", "'.$row['ATOM_MASTER_ID'].'", "'.$row['MFG_PART_NUM'].'", "'.$row['SITE_NUM'].'",
                        "'.$row['STEP_NM'].'", "'.$row['TESTER'].'", "'.$row['HANDLER'].'", 1, 
                        "'.$row['HASH'].'", "'.$row['SAP_RTE_ID'].'", "'.$row['HW_SET_ID'].'", "'.$row['RES_SET_ID'].'", "'.$row['TO_REMOVE'].'", "'.$row['STATUS'].'",
                        "COMPLETED_HW_CHANGE", "", "'.date('Y-m-d H:i:s').'", "", "'.$row['CREATED_BY'].'", "SYSTEM", "")';
            $result_history = ExecuteIQuery($query_history,$iConRLM);
        }
    }
    return $result_history;
}

function FLAG_SETUPS($iConRLM, $unplannable, $plannable){
    $result = "";
    if (count($unplannable) > 0 || count($plannable) > 0) {
        if (count($unplannable) > 0) {
            $unplannable_length = count($unplannable);
            $curr_date = strtotime(date('Y-m-d H:i:s'));
            $separator = ",";
            $values = "";
    
            foreach ($unplannable as $key => $dt) {
                if ($key == $unplannable_length - 1) {
                    $separator = "";
                }
                $values .= '("", "'.$dt['PART'].'", "'.$dt['SITE'].'", "'.$dt['STEP'].'", "'.$dt['ATOM_TESTER'].'", 
                            "'.$dt['ATOM_HANDLER'].'", "'.$dt['RTE_ID'].'", "'.$dt['HW_SET_ID'].'", "'.$dt['RES_SET_ID'].'", 
                            "'.$dt['ATOM_MASTER_ID'].'")'.$separator.'';
            }
            $query = 'INSERT INTO TEST.ADI_UNPLANNABLE_SETUP VALUES '.$values.'';
            $result = ExecuteIQuery($query,$iConRLM);
        }
        if(count($plannable) > 0) {
            $flag_id = [];
            foreach ($plannable as $key => $dt) {
                array_push($flag_id, $dt['DB_ID']);
            }

            $query = 'DELETE FROM TEST.ADI_UNPLANNABLE_SETUP WHERE ID IN ("'.implode('","', $flag_id).'")';
            $result = ExecuteIQuery($query,$iConRLM);
        }
    }

    return $result;
}

function SET_DEDICATION($iConRLM, $data, $user_details){
    
    $values = "";
    $values_history = "";
    $separator = ",";
    $data_length = count($data);
    $curdate = date('Y-m-d H:i:s');

    foreach ($data as $key => $dt) {
        $ded_id = "";
        $flag_id = [];
        $hash_arr = [];
        $alt_hash_arr = [];
        $existing_prio = [];
        $new_prio_cd = $dt['PRIO_CD'] + 1;
        $setup_type = $dt['TYPE'];

        //ADD TO PRIO LIST (IF DOES NOT EXISTS ALREADY)
        $string = $dt['MFG_PART_NUM'].'|'.$dt['SITE_NUM'].'|'.$dt['SAP_RTE_ID'].'|'.$dt['STEP_NM'].'|'.$dt['TEMP_CLASS'].'|'.$dt['ENGR_TESTER'].'|'.$dt['ENGR_HANDLER'].'|'.$dt['TESTER'].'|'.$dt['HANDLER'].'|'.$dt['ORIG_PRIO'];
        $hash = hash("sha256", $string);
        
        $check_prio = 'SELECT * FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER = "'.$hash.'"';
        $check_prio_res = ExecuteIQuery($check_prio,$iConRLM);
        while($row = mysqli_fetch_assoc($check_prio_res)){
            array_push($existing_prio, $row);
        }
        
        if (count($existing_prio) == 0) {
            //UPDATE ALTERNATES AND PRIMARY OF CREATED DEDICATION
            foreach ($dt['ALTERNATES'] as $alts) {
                if (isset($alts[29]) && isset($alts[30])) {
                    $expl = explode("|", $alts[15]);
                    $alt_string = $expl[0].'|'.$expl[1].'|'.$expl[4].'|'.$expl[2].'|'.$alts[29].'|'.$alts[1].'|'.$alts[2].'|'.$alts[3].'|'.$alts[4].'|'.$alts[30];
                    $alt_hash = hash("sha256", $alt_string);
                    array_push($alt_hash_arr, $alt_hash);
                }
            }

            $get_prio = 'SELECT * FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER IN ("'.implode('","', $alt_hash_arr).'") ORDER BY NEW_PRIO ASC';
            $get_prio_res = ExecuteIQuery($get_prio,$iConRLM);
            while($row = mysqli_fetch_assoc($get_prio_res)){
                $update_query = "";
                $row_id = $row['ID'];

                if ($setup_type == "PRIMARY") {
                    $new_prio_cd = 1;

                    if ($row['IS_PRIMARY'] == "YES") {
                        $update_query = 'SET IS_PRIMARY = "NO", NEW_PRIO = 2';
                    }
                    else{
                        if ($row['NEW_PRIO'] >= 2) {
                            $other_prio_cd = $row['NEW_PRIO'] + 1;
                            $update_query = 'SET NEW_PRIO = '.$other_prio_cd.'';
                        }
                    }
                }
                else{
                    if ($row['NEW_PRIO']>= $new_prio_cd && $row['IS_PRIMARY'] == "NO") {
                        $other_prio_cd = $row['NEW_PRIO'] + 1;
                        $update_query = 'SET NEW_PRIO = '.$other_prio_cd.'';
                    }
                }

                if ($update_query != "") {
                    $update_prio = 'UPDATE TEST.ADI_SETUP_PRIO '.$update_query.' WHERE ID = '.$row_id.'';
                    $update_prio_res = ExecuteIQuery($update_prio,$iConRLM);
                }
            }

            $is_primary = ($setup_type == "PRIMARY") ? "YES" : "NO";            
            $insert_prio = 'INSERT INTO TEST.ADI_SETUP_PRIO VALUES (
                "", "'.$hash.'", "'.$dt['MFG_PART_NUM'].'", "'.$dt['SITE_NUM'].'", "'.$dt['SAP_RTE_ID'].'",
                "'.$dt['STEP_NM'].'", "DEDICATION", "'.$is_primary.'", "'.$dt['ORIG_PRIO'].'", 
                "'.$new_prio_cd.'"
            )';
            $insert_prio_res = ExecuteIQuery($insert_prio,$iConRLM);
            $response = $insert_prio_res;
        }

        if (count($dt['FLAG_SETUPS']) > 0) {
            foreach ($dt['FLAG_SETUPS'] as $fs) {
                $values_flag = '("", "'.$fs['PART'].'", "'.$fs['SITE'].'", "'.$fs['STEP'].'", "'.$fs['ATOM_TESTER'].'", 
                                "'.$fs['ATOM_HANDLER'].'", "'.$fs['RTE_ID'].'", "'.$fs['HW_SET_ID'].'", "'.$fs['RES_SET_ID'].'", 
                                "'.$fs['ATOM_MASTER_ID'].'")';
                $query_flag = 'INSERT INTO TEST.ADI_UNPLANNABLE_SETUP VALUES '.$values_flag.'';
                $result_flag = ExecuteIQuery($query_flag,$iConRLM);
    
                $get_flag_id = 'SELECT LAST_INSERT_ID()';
                $get_flag_id_res = ExecuteIQuery($get_flag_id, $iConRLM);
                while($row = mysqli_fetch_assoc($get_flag_id_res)){
                    array_push($flag_id, $row['LAST_INSERT_ID()']);
                }
            }
        }
        
        $insert_ded_details = 'INSERT INTO TEST.ADI_DEDICATION VALUES ("", "'.$dt['ATOM_MASTER_ID'].'", "'.$dt['MFG_PART_NUM'].'", "'.$dt['SITE_NUM'].'", "'.$dt['STEP_NM'].'", "'.$dt['DETAILS'][5].'", 
                              "'.$dt['DETAILS'][6].'", "'.$dt['DETAILS'][7].'", "'.$dt['DETAILS'][8].'", "'.$dt['DETAILS'][9].'", "'.$dt['DETAILS'][10].'", "'.$dt['DETAILS'][11].'", "'.$dt['ENGR_TESTER'].'", "'.$dt['ENGR_HANDLER'].'", 
                              "'.$dt['PRIO_CD'].'", "'.$dt['EXCLUSIVE'].'", "'.implode(',', $flag_id).'", "'.$dt['TYPE'].'", "'.$dt['PARENT_TABLE'].'")';

        $insert_ded_details_res = ExecuteIQuery($insert_ded_details,$iConRLM);

        $get_id = 'SELECT LAST_INSERT_ID()';
        $get_id_res = ExecuteIQuery($get_id, $iConRLM);
        while($row = mysqli_fetch_assoc($get_id_res)){
            $ded_id = $row['LAST_INSERT_ID()'];
        }

        if ($key == $data_length - 1) {
            $separator = "";
        }

        $values .= '("", "'.$dt['ATOM_MASTER_ID'].'", "'.$dt['MFG_PART_NUM'].'", "'.$dt['SITE_NUM'].'", 
                        "'.$dt['STEP_NM'].'", "'.$dt['TESTER'].'", "'.$dt['HANDLER'].'", 1, 
                        "'.$dt['HASH'].'", "'.$dt['SAP_RTE_ID'].'", "'.$dt['HW_SET_ID'].'", "'.$dt['RES_SET_ID'].'", 
                        "", "COMPLETED", "'.$curdate.'", "'.$user_details['emp_name'].'", "", "'.$ded_id.'", "'.implode("|", $alt_hash_arr).'", "'.$hash.'")'.$separator.'';

        $values_history .= '("", "'.$dt['ATOM_MASTER_ID'].'", "'.$dt['MFG_PART_NUM'].'", "'.$dt['SITE_NUM'].'", 
                                "'.$dt['STEP_NM'].'", "'.$dt['TESTER'].'", "'.$dt['HANDLER'].'", 1, 
                                "'.$dt['HASH'].'", "'.$dt['SAP_RTE_ID'].'", "'.$dt['HW_SET_ID'].'", "'.$dt['RES_SET_ID'].'", "", "COMPLETED",
                                "COMPLETED_DEDICATION", "'.$curdate.'", "", "", "'.$user_details['emp_name'].'", "", "")'.$separator.'';

        if ($dt['TO_REMOVE'] != null || $dt['TO_REMOVE'] != '') {

            $res_arr = [];
            $get_primary = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP APS RIGHT JOIN TEST.ADI_DEDICATION AD ON APS.DED_ID = AD.DED_ID WHERE ID = '.$dt['TO_REMOVE'].' AND APS.DED_ID != 0';
            $get_primary_res = ExecuteIQuery($get_primary,$iConRLM);
            while($row = mysqli_fetch_assoc($get_primary_res)){
                array_push($res_arr, $row);
            }

            if (count($res_arr) > 0) {
                foreach ($res_arr as $arr) {
                    $update_ded = 'UPDATE TEST.ADI_DEDICATION SET TYPE = "ALTERNATE" WHERE DED_ID = '.$arr['DED_ID'].'';
                    $update_ded_res = ExecuteIQuery($update_ded,$iConRLM);
                }
            }
            else{
                $delete_primary = 'DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE ID = '.$dt['TO_REMOVE'].'';
                $delete_primary_res = ExecuteIQuery($delete_primary,$iConRLM);
            }
        }
    }

    $insert_primary_ded = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES '.$values.'';
    $insert_primary_ded_hist = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES '.$values_history.'';

    $result = ExecuteIQuery($insert_primary_ded,$iConRLM);
    $result_history = ExecuteIQuery($insert_primary_ded_hist,$iConRLM);

    return $result;
}

function REMOVE_DEDICATION($iConRLM, $ded_id, $default_primary, $user_details){
    $response = "";
    $curdate = date('Y-m-d H:i:s');
    foreach ($ded_id as $id) {
        $flag_id = "";
        $get_details = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP APS INNER JOIN TEST.ADI_DEDICATION ADN ON APS.DED_ID = ADN.DED_ID WHERE APS.ID = '.$id.'';
        $get_details_res = ExecuteIQuery($get_details,$iConRLM);
        while($row = mysqli_fetch_assoc($get_details_res)){
            $alternates = [];
            $ded_prio_id = 0;
            $ded_prio_cd = 0;
            $flag_id = $row['UNPLANNABLE'];
            $delete_hist = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES ("", "'.$row['ATOM_MASTER_ID'].'", "'.$row['MFG_PART_NUM'].'", "'.$row['SITE_NUM'].'", 
                                    "'.$row['STEP_NM'].'", "'.$row['TESTER'].'", "'.$row['HANDLER'].'", 1, 
                                    "'.$row['HASH'].'", "'.$row['SAP_RTE_ID'].'", "'.$row['HW_SET_ID'].'", "'.$row['RES_SET_ID'].'", "", "DELETED",
                                    "DELETED_DEDICATION", "'.$curdate.'", "", "", "'.$user_details['emp_name'].'", "", "")';
    
            $delete_hist_res =  ExecuteIQuery($delete_hist,$iConRLM);;
    
            $delete_details = 'DELETE FROM TEST.ADI_DEDICATION WHERE DED_ID = '.$row['DED_ID'].'';
            $delete_details_res = ExecuteIQuery($delete_details,$iConRLM);

            //GET DEDICATION'S PRIO_CD
            $get_ded_prio = 'SELECT NEW_PRIO, ID FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER = "'.$row['IDENTIFIER'].'"';
            $get_ded_prio_res = ExecuteIQuery($get_ded_prio,$iConRLM);
            while($row2 = mysqli_fetch_assoc($get_ded_prio_res)){
                $ded_prio_cd = $row2['NEW_PRIO'];
                $ded_prio_id = $row2['ID'];
            }

            //UPDATE PRIO_CD LIST UPON DELETING DEDICATION
            $split = explode("|", $row['ALTERNATES']);
            $get_alternates = 'SELECT * FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER IN ("'.implode('","', $split).'")';
            $get_alternates_res = ExecuteIQuery($get_alternates,$iConRLM);
            while($row3 = mysqli_fetch_assoc($get_alternates_res)){
                if ($row3['IS_PRIMARY'] == "NO") {
                    if ($row3['NEW_PRIO'] > $ded_prio_cd) {
                        $other_prio_cd = $row3['NEW_PRIO'] - 1;
                        $update_prio = 'UPDATE TEST.ADI_SETUP_PRIO SET NEW_PRIO = '.$other_prio_cd.' WHERE ID = '.$row3['ID'].'';
                        $update_prio_res = ExecuteIQuery($update_prio,$iConRLM);
                    }
                }
            }

            //DELETE DEDICATION'S PRIO RECORD FROM TABLE
            $delete_ded_prio = 'DELETE FROM TEST.ADI_SETUP_PRIO WHERE ID = '.$ded_prio_id.'';
            $delete_ded_prio_res = ExecuteIQuery($delete_ded_prio,$iConRLM);
        }
        
        //DELETE DEDICATION FROM TABLE
        $delete_dedication = 'DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE ID = '.$id.'';
        $delete_dedication_res = ExecuteIQuery($delete_dedication,$iConRLM);
    
        if ($flag_id != "" || $flag_id != null) {
            $delete_flag = 'DELETE FROM TEST.ADI_UNPLANNABLE_SETUP WHERE ID IN ('.$flag_id.')';
            $delete_flag_res = ExecuteIQuery($delete_flag,$iConRLM);
        }
        $response = $delete_dedication_res;
    }

    if (count($default_primary) > 0) {
        foreach ($default_primary as $dp) {
            $expl = explode("|", $dp[15]);
            if ($dp[27] == "DEDICATION" && ($dp[28] != '' || $dp[28] != null)) {
                $update_alt_ded = 'UPDATE TEST.ADI_DEDICATION SET TYPE = "PRIMARY" WHERE DED_ID = '.$dp[28].'';
                $update_alt_ded_res = ExecuteIQuery($update_alt_ded,$iConRLM);
            }
            else{
                $values = '("", "'.$dp[25].'", "'.$expl[0].'", "'.$expl[1].'", 
                        "'.$expl[2].'", "'.$dp[3].'", "'.$dp[4].'", 1, 
                        "'.$expl[3].'", "'.$expl[4].'", "'.$dp[22].'", "'.$dp[24].'", 
                        "", "COMPLETED", "'.$curdate.'", "'.$user_details['emp_name'].'", "", "", "", "")';

                $insert_default_primary = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES '.$values.'';
                $result = ExecuteIQuery($insert_default_primary,$iConRLM);
            }
            $values_history = '("", "'.$dp[25].'", "'.$expl[0].'", "'.$expl[1].'", 
                                        "'.$expl[2].'", "'.$dp[3].'", "'.$dp[4].'", 1, 
                                        "'.$expl[3].'", "'.$expl[4].'", "'.$dp[22].'", "'.$dp[24].'", "", "COMPLETED",
                                        "COMPLETED_DEDICATION", "'.$curdate.'", "", "", "'.$user_details['emp_name'].'", "", "")';

            
            $insert_default_primary_hist = 'INSERT INTO TEST.ADI_PRIMARY_SETUP_HISTORY VALUES '.$values_history.'';
            $result_history = ExecuteIQuery($insert_default_primary_hist,$iConRLM);
        }
    }

    return $response;
}

?>