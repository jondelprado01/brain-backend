<?php
header('Access-Control-Allow-Origin: *');
include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("../TEST/ADI_FUNCTIONS_022.PHP");
ini_set("error_reporting",E_ALL);
ini_set("display_errors",1);

$aryRequest = $_REQUEST;
// $requestMedthod = $_SERVER['REQUEST_METHOD'];
date_default_timezone_set('Asia/Manila');

$iConRLM = Open_ILink("localhost");

// if ($requestMedthod != "POST") {
//     exit("Invalid HTTP Method!");
// }

$bolDebug = false;
if(isset($aryRequest["DEBUG"]) && $aryRequest["DEBUG"] != "") {
	$bolDebug=true;
}

$bolJSON = true;
if(isset($aryRequest["NO_JSON"]) && $aryRequest["NO_JSON"] != "") {
	$bolJSON = false;
}

$process = "";
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

if ($process == "GET_PRIMARY") {
    print_r(json_encode(GET_PRIMARY($iConRLM, $aryRequest["INPUT"])));
}
elseif ($process == "SET_PRIMARY") {
    print_r(json_encode(SET_PRIMARY($iConRLM, $_POST['primary_setup_data'], $_POST['user_details'])));
}
elseif ($process == "GET_HW_SET_DED_AUTO") {
    print_r(json_encode(GET_HW_SET_DED_AUTO($iConRLM, $_POST['payload'])));
}

mysqli_close($iConRLM);

// PLANNING ASSUMPTIONS REVAMP - V2
function GET_PRIMARY($iConRLM, $data){
    $result = [];
    $partnum = [];
    
    foreach ($data as $key => $dt) {
        array_push($partnum, mysqli_real_escape_string($iConRLM, $dt));
    }
    $get_primary = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE MFG_PART_NUM IN ("'.implode('","', $partnum).'") AND DED_ID = 0';
    $res_get_primary = ExecuteIQuery($get_primary,$iConRLM);
    while($row = mysqli_fetch_assoc($res_get_primary)){
        array_push($result, $row);
    }
    return $result;
}

function SET_PRIMARY($iConRLM, $data, $user_details){
    $curdate = date('Y-m-d H:i:s');
    $schedule = GENERATE_SCHEDULE($curdate);
    $values = "";
    $result = "";
    $setup_status = "COMPLETED";
    $data_length = count($data);

    foreach ($data as $key => $dt) {
        if ($dt[12] == "") {
            $separator = ",";
            if ($key == $data_length - 1) {
                $separator = "";
            }
            $setup_dt = json_decode($dt[11]);
            $values .= '("", "", "'.$setup_dt->PART.'", "'.$setup_dt->SITE.'", "'.$setup_dt->STEP.'", "'.$setup_dt->ATOM_TESTER.'", "'.$setup_dt->ATOM_HANDLER.'",
                         "1", "'.$setup_dt->UNIVERSAL_ID.'", "'.$setup_dt->RTE_ID.'", "'.$setup_dt->HW_SET_ID.'", "'.$setup_dt->RES_SET_ID.'", "", "'.$setup_status.'",
                         "'.$curdate.'", "'.$user_details['emp_name'].'", "", "", "", "")'.$separator.'';
        }
        else{
            $is_primary = 1;
            $has_hw_change = 0;
            $dft_prim_data = json_decode($dt[12]);
            $dft_prim_hw_set = explode(",", $dft_prim_data);
            $is_ded_dft = isset($dft_prim_data->DED_ID) ? $dft_prim_data->DED_ID : 0;

            $new_prim_data = json_decode($dt[11]);
            $new_prim_hw_set = explode(",", $new_prim_data);
            $is_ded_new = isset($new_prim_data->DED_ID) ? $new_prim_data->DED_ID : 0;

            if (count($dft_prim_hw_set) > 1 || count($new_prim_hw_set) > 1) { //if multiple hw_set found, trigger handshake process - 24 hours grace period
                $setup_status = "PENDING_HW_CHANGE";
                $is_primary = 0;
                $has_hw_change++;
            }
            else{ //if hw_set is invalid (does not match), trigger handshake process - email notif only
                $setup_status = "COMPLETED_HW_CHANGE";
            }

            //both not dedication - tested 03/18/2026
            if ($is_ded_dft == 0 && $is_ded_new == 0) {
                $new_prim = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES ("", "", "'.$new_prim_data->PART.'", "'.$new_prim_data->SITE.'", "'.$new_prim_data->STEP.'", 
                                "'.$new_prim_data->ATOM_TESTER.'", "'.$new_prim_data->ATOM_HANDLER.'", "'.$is_primary.'", "'.$new_prim_data->UNIVERSAL_ID.'", "'.$new_prim_data->RTE_ID.'", 
                                "'.$new_prim_data->HW_SET_ID.'", "'.$new_prim_data->RES_SET_ID.'", "", "'.$setup_status.'", "'.$curdate.'", "'.$user_details['emp_name'].'", "", "", "", "")';
                $new_prim_res = ExecuteIQuery($new_prim,$iConRLM);

                if ($has_hw_change == 0) {
                    $delete_dft = 'DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE ID = '.$dft_prim_data->ID.''; //FINISH HANDSHAKE PROCESS LOGIC
                    $delete_dft_res = ExecuteIQuery($delete_dft,$iConRLM);
                }

                $result = $new_prim_res;
            }
            //both are dedication (primary & alternate) - tested 03/18/2026
            elseif ($is_ded_dft != 0 && $is_ded_new != 0) {
                $ded_setup_arr = [
                    [0, $dft_prim_data->ID,],
                    [$is_primary, $new_prim_data->ID, ]
                ];
                foreach ($ded_setup_arr as $dsakey => $dsa) {
                    $update_ded_setup = 'UPDATE TEST.ADI_PRIMARY_SETUP SET IS_PRIMARY = '.$dsa[0].' WHERE ID = '.$dsa[1].'';
                    $update_ded_setup_res = ExecuteIQuery($update_ded_setup,$iConRLM);
                    $result = $update_ded_setup_res;
                }
            }
            //default primary = dedication; new = normal setup - tested 03/18/2026
            elseif ($is_ded_dft != 0 && $is_ded_new == 0) {
                $new_prim = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES ("", "", "'.$new_prim_data->PART.'", "'.$new_prim_data->SITE.'", "'.$new_prim_data->STEP.'", 
                                "'.$new_prim_data->ATOM_TESTER.'", "'.$new_prim_data->ATOM_HANDLER.'", "'.$is_primary.'", "'.$new_prim_data->UNIVERSAL_ID.'", "'.$new_prim_data->RTE_ID.'", 
                                "'.$new_prim_data->HW_SET_ID.'", "'.$new_prim_data->RES_SET_ID.'", "", "'.$setup_status.'", "'.$curdate.'", "'.$user_details['emp_name'].'", "", "", "", "")';
                $new_prim_res = ExecuteIQuery($new_prim,$iConRLM);

                $update_dft_ded = 'UPDATE TEST.ADI_PRIMARY_SETUP SET IS_PRIMARY = 0 WHERE ID = '.$dft_prim_data->ID.'';
                $update_dft_ded_res = ExecuteIQuery($update_dft_ded,$iConRLM);

                $result = $update_dft_ded_res;
            }
            //default primary != dedication; new = dedication - tested 03/18/2026
            elseif ($is_ded_dft == 0 && $is_ded_new != 0) {
                $delete_dft = 'DELETE FROM TEST.ADI_PRIMARY_SETUP WHERE ID = '.$dft_prim_data->ID.'';
                $delete_dft_res = ExecuteIQuery($delete_dft,$iConRLM);

                $update_new_ded = 'UPDATE TEST.ADI_PRIMARY_SETUP SET IS_PRIMARY = 1 WHERE ID = '.$new_prim_data->ID.'';
                $update_new_ded_res = ExecuteIQuery($update_new_ded,$iConRLM);

                $result = $update_new_ded_res;
            }
        }
    }

    if ($values != '') {
        $query = 'INSERT INTO TEST.ADI_PRIMARY_SETUP VALUES '.$values.'';
        $result = ExecuteIQuery($query,$iConRLM);
    }

    return $result;
}

//GET HW_SET OF DED_AUTO SETUPS - will only be triggered if the hw_set_id of ded_auto setups is not included in the GET_HARDWARE_RECORD_V3 API (TOP_SECTION.PHP) result.
function GET_HW_SET_DED_AUTO($iConRLM, $hw_set_id){
    $hw_set_result = [];
    $hw_set_name = [];
    $hw_qty_result = [];
    $result = [];    

    $hw_set_query = 'SELECT
			HW_SET_ID,
			HW_TYPE,
			HW_NM,
			REQUIRED_QTY,
			0 AS SORT_ORDER
			FROM BODS_JDA_ADI_EXPORT.HARDWARE_SET
			WHERE HW_SET_ID IN ("'.implode('","', $hw_set_id).'")
			UNION
			SELECT
			HW_SET_ID,
			HW_TYPE,
			HW_NM,
			REQUIRED_QTY,
			1 AS SORT_ORDER
			FROM BODS_JDA_ADI_EXPORT.HARDWARE_ALTERNATES
			WHERE HW_SET_ID IN ("'.implode('","', $hw_set_id).'")
			ORDER BY HW_SET_ID, HW_TYPE, HW_NM, SORT_ORDER;';

    $res_hw_set_query = ExecuteIQuery($hw_set_query,$iConRLM);
    while($row = mysqli_fetch_assoc($res_hw_set_query)){
        array_push($hw_set_result, $row);
        array_push($hw_set_name, $row['HW_NM']);
    }
    
    // will return hw quantity for the same hw_name but different site or res_area only because ded_auto does not have site_num or res_area as of the moment. will be adjusted in the future
    $hw_qty_query = 'SELECT
			PRR.SITE_NUM,
			PRR.RES_AREA,
			REF.HW_NM,
			MIN(HMS.AVAIL_QTY) AVAIL_QTY,
			MIN(HMS.TOTAL_QTY) TOTAL_QTY
			FROM BRAIN.HW_NONBOARD_HMS HMS
			INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
			INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
			WHERE REF.HW_NM IN ("'.implode('","', $hw_set_name).'") AND TOTAL_QTY > 0
			GROUP BY SITE_NUM, RES_AREA, HW_NM';

    $res_hw_qty_query = ExecuteIQuery($hw_qty_query,$iConRLM);
    while($row = mysqli_fetch_assoc($res_hw_qty_query)){
        array_push($hw_qty_result, $row);
    }
    
    foreach ($hw_set_result as $hwskey => $hws) {
        $qty = 0;
        foreach ($hw_qty_result as $hwqkey => $hwq) {
            if ($hwq['HW_NM'] == $hws['HW_NM']) {
                $qty = $hwq['TOTAL_QTY'];
            }
        }
        $result[$hws['HW_SET_ID']][$hws['HW_TYPE']][] = ["HW_NM" => $hws['HW_NM'], "REQUIRED_QTY" => $qty];
    }
    return $result;
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

?>