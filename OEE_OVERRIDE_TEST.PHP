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

    if(isset($aryRequest["PROCESS_TYPE"]) && $aryRequest["PROCESS_TYPE"] != "") {
        $process = $aryRequest["PROCESS_TYPE"];
    }

    if ($process == "SET_OVERRIDE") {
        print_r(json_encode(SET_OVERRIDE($iConRLM, $_POST['common_data'])));
    }
    elseif ($process == "GET_OVERRIDE") {
        print_r(json_encode(GET_OVERRIDE($iConRLM, $_POST['common_data'])));
    }
    elseif ($process == "GET_PRIMARY_SETUP") {
        print_r(json_encode(GET_PRIMARY_SETUP($iConRLM, $_POST['payload'])));
    }

    mysqli_close($iConRLM);

    function GET_OVERRIDE($iConRLM, $common){
        $result     = array(array());
        $return_res = [];
        foreach ($common as $key => $values) {
            $append = array();

            foreach($result as $product) {
                foreach($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        foreach ($result as $key => $value) {
            if (isset($value['OEE_FIELD'][6])) {
                $site_num   = $value['OEE_FIELD'][1];
                $res_area   = $value['OEE_FIELD'][2];
                $orig_sha1   = $value['OEE_FIELD'][6];
                $partnum    = isset($value['MFG_PART_NUM']) ? $value['MFG_PART_NUM'] : (($value['OEE_FIELD'][0] != '') ? $value['OEE_FIELD'][0] : "");
                $tester     = isset($value['TESTER'])       ? $value['TESTER']       : (($value['OEE_FIELD'][3] != '') ? $value['OEE_FIELD'][3] : "");
                $handler    = isset($value['HANDLER'])      ? $value['HANDLER']      : (($value['OEE_FIELD'][4] != '') ? $value['OEE_FIELD'][4] : "");
                $temp       = isset($value['TEMP_CLASS'])   ? $value['TEMP_CLASS']   : (($value['OEE_FIELD'][5] != '') ? $value['OEE_FIELD'][5] : "");
    
                $sha1 = 'SHA1(CONCAT_WS("|", "'.$partnum.'", "'.$site_num.'", "'.$res_area.'", "'.$tester.'", "'.$handler.'", "'.$temp.'"))';
    
                $check_oee = 'SELECT * FROM BRAIN.OEE_OVERRIDE_MAIN WHERE SHA1_ROW_VALUE = '.$sha1.'';
                $check_oee_res = ExecuteIQuery($check_oee, $iConRLM);
    
                while($row = mysqli_fetch_assoc($check_oee_res)){
                    array_push($return_res, [
                        "SHA1_ROW_VALUE" => $row['SHA1_ROW_VALUE'],
                        "ORIG_SHA1" => $orig_sha1,
                        "OEE_VAL" => $row['OEE_VAL'],
                    ]);
                }
            }
        }

        return $return_res;
    }

    function SET_OVERRIDE($iConRLM, $common){
        $result     = array(array());
        $return_res = "";
        foreach ($common as $key => $values) {
            $append = array();

            foreach($result as $product) {
                foreach($values as $item) {
                    $product[$key] = $item;
                    $append[] = $product;
                }
            }

            $result = $append;
        }

        foreach ($result as $key => $value) {

            $check_arr  = [];
            $site_num   = $value['OEE_FIELD'][1];
            $res_area   = $value['OEE_FIELD'][2];
            $override   = $value['OEE_FIELD'][6];
            // $sha1       = $value['OEE_FIELD'][7];
            $partnum    = isset($value['MFG_PART_NUM']) ? $value['MFG_PART_NUM'] : (($value['OEE_FIELD'][0] != '') ? $value['OEE_FIELD'][0] : "");
            $tester     = isset($value['TESTER'])       ? $value['TESTER']       : (($value['OEE_FIELD'][3] != '') ? $value['OEE_FIELD'][3] : "");
            $handler    = isset($value['HANDLER'])      ? $value['HANDLER']      : (($value['OEE_FIELD'][4] != '') ? $value['OEE_FIELD'][4] : "");
            $temp       = isset($value['TEMP_CLASS'])   ? $value['TEMP_CLASS']   : (($value['OEE_FIELD'][5] != '') ? $value['OEE_FIELD'][5] : "");

            // $sha1       = sha1($site_num.$res_area.$tester.$handler.$temp);
            $sha1 = 'SHA1(CONCAT_WS("|", "'.$partnum.'", "'.$site_num.'", "'.$res_area.'", "'.$tester.'", "'.$handler.'", "'.$temp.'"))';

            $check_oee = 'SELECT * FROM BRAIN.OEE_OVERRIDE_MAIN WHERE SHA1_ROW_VALUE = '.$sha1.'';
            $check_oee_res = ExecuteIQuery($check_oee, $iConRLM);

            while($row = mysqli_fetch_assoc($check_oee_res)){
                array_push($check_arr, $row);
            }

            if (count($check_arr) > 0) {
                foreach ($check_arr as $key => $check) {

                    $change_type = ($override != null || $override != '') ? "UPDATE_OVERRIDE" : "DELETE_OVERRIDE";
                    $oee_val = ($change_type == "UPDATE_OVERRIDE") ? $override : $check['OEE_VAL'];
                    $update_oee_history = 'INSERT INTO BRAIN.OEE_OVERRIDE_MAIN_HIST VALUES ("'.$check['OEEO_ID'].'", "'.$check['SHA1_ROW_VALUE'].'", "'.$check['SITE_NUM'].'", "'.$check['RES_AREA'].'", 
                                                "'.$check['TESTER'].'", "'.$check['HANDLER'].'", "'.$check['TEMP_CLASS'].'", "'.$oee_val.'", "'.date('Y-m-d H:i:s').'", "ADMIN", "'.$change_type.'")';
                    $update_oee_history_res = ExecuteIQuery($update_oee_history, $iConRLM);

                    if ($override != null || $override != '') {
                        $update_oee = 'UPDATE BRAIN.OEE_OVERRIDE_MAIN SET OEE_VAL = "'.$override.'" WHERE OEEO_ID = '.$check['OEEO_ID'].'';
                        $update_oee_res = ExecuteIQuery($update_oee, $iConRLM);
                        $return_res = $update_oee_res;
                    }
                    else{
                        $get_oee_part = 'SELECT * FROM BRAIN.OEE_OVERRIDE_PART WHERE OEEO_ID = '.$check['OEEO_ID'].'';
                        $get_oee_part_res = ExecuteIQuery($get_oee_part, $iConRLM);

                        while($row = mysqli_fetch_assoc($get_oee_part_res)){
                            $update_part_history = 'INSERT INTO BRAIN.OEE_OVERRIDE_PART_HIST VALUES ("'.$row['OEEO_ID'].'", "'.$row['MFG_PART_NUM'].'", "'.date('Y-m-d H:i:s').'", "ADMIN", "DELETE_OVERRIDE_PART")';
                            $update_part_history_res = ExecuteIQuery($update_part_history, $iConRLM);
                        }

                        $delete_oee = 'DELETE FROM BRAIN.OEE_OVERRIDE_MAIN WHERE OEEO_ID = '.$check['OEEO_ID'].'';
                        $delete_oee_res = ExecuteIQuery($delete_oee, $iConRLM);
                        
                        $delete_oee_part = 'DELETE FROM BRAIN.OEE_OVERRIDE_PART WHERE OEEO_ID = '.$check['OEEO_ID'].'';
                        $delete_oee_part_res = ExecuteIQuery($delete_oee_part, $iConRLM);

                        $return_res = $delete_oee_res;
                    }
                }
            }
            else{
                $insert_oee = 'INSERT INTO BRAIN.OEE_OVERRIDE_MAIN VALUES ("", '.$sha1.', "'.$site_num.'", "'.$res_area.'", "'.$tester.'", "'.$handler.'", "'.$temp.'", "'.$override.'")';
                $insert_oee_res = ExecuteIQuery($insert_oee, $iConRLM);

                //INSERT HISTORY
                $get_oee_id = 'SELECT LAST_INSERT_ID()';
                $get_oee_id_res = ExecuteIQuery($get_oee_id, $iConRLM);
                while($row = mysqli_fetch_assoc($get_oee_id_res)){
                    $insert_oee_history = 'INSERT INTO BRAIN.OEE_OVERRIDE_MAIN_HIST VALUES ("'.$row['LAST_INSERT_ID()'].'", '.$sha1.', "'.$site_num.'", "'.$res_area.'", "'.$tester.'", "'.$handler.'", "'.$temp.'", "'.$override.'",
                                          "'.date('Y-m-d H:i:s').'", "ADMIN", "NEW_OVERRIDE")';
                    $insert_oee_history_res = ExecuteIQuery($insert_oee_history, $iConRLM);
                }


                // $get_oee_id = 'SELECT LAST_INSERT_ID()';
                // $get_oee_id_res = ExecuteIQuery($get_oee_id, $iConRLM);
                // while($row = mysqli_fetch_assoc($get_oee_id_res)){
                //     $add_sha1 = 'UPDATE BRAIN.OEE_OVERRIDE_MAIN SET SHA1_ROW_VALUE = SHA1(CONCAT_WS("|", "'.$partnum.'", "'.$site_num.'", "'.$res_area.'", "'.$tester.'", "'.$handler.'", "'.$temp.'")) WHERE OEEO_ID = "'.$row['LAST_INSERT_ID()'].'"';
                //     $add_sha1_res = ExecuteIQuery($add_sha1, $iConRLM);
                //     $return_res = $add_sha1_res;
                // }

                if ($insert_oee_res) {
                    if ($partnum != "") {
                        $get_id = 'SELECT LAST_INSERT_ID()';
                        $get_id_res = ExecuteIQuery($get_id, $iConRLM);
                        while($row = mysqli_fetch_assoc($get_id_res)){
                            $insert_part = 'INSERT INTO BRAIN.OEE_OVERRIDE_PART VALUES ("'.$row['LAST_INSERT_ID()'].'", "'.$partnum.'")';
                            $insert_part_res = ExecuteIQuery($insert_part, $iConRLM);
                            $return_res = $insert_part_res;

                            $insert_part_history = 'INSERT INTO BRAIN.OEE_OVERRIDE_PART_HIST VALUES ("'.$row['LAST_INSERT_ID()'].'", "'.$partnum.'", "'.date('Y-m-d H:i:s').'", "ADMIN", "NEW_OVERRIDE_PART")';
                            $insert_part_history_res = ExecuteIQuery($insert_part_history, $iConRLM);
                        }
                    }
                    else{
                        $return_res = $insert_oee_res;
                    }
                }
            }
        }

        return $return_res;
    }

    function GET_PRIMARY_SETUP($iConRLM, $data){
        $result = [];
        $get_primary = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP WHERE MFG_PART_NUM IN ("'.implode('","', $data).'")';
        $get_primary_res = ExecuteIQuery($get_primary, $iConRLM);
        while($row = mysqli_fetch_assoc($get_primary_res)){
            array_push($result, $row);
        }
        return $result;
    }
?>