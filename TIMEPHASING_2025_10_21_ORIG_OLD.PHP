<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("ADI_FUNCTIONS.PHP");
    ini_set("error_reporting",E_ALL);
    ini_set("display_errors",1);

    $aryRequest = $_REQUEST;
    $requestMedthod = $_SERVER['REQUEST_METHOD'];
    date_default_timezone_set('Asia/Manila');
    $iConRLM = Open_ILink("MXHTAFOT01L");

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

    if(isset($aryRequest["PROCESS_TYPE"]) && $aryRequest["PROCESS_TYPE"] != "") {
        $process = $aryRequest["PROCESS_TYPE"];
    }
 
    $retrieve_week = ""; 
    $retrieve_calendar = "";

    if ($process == "GET_CURRENT_WEEK") {
        print_r(json_encode(GET_CURRENT_WEEK($iConRLM)));
    }
    elseif ($process == "GET_CALENDAR") {
        print_r(json_encode(GET_CALENDAR($iConRLM, $_POST['date'], $_POST['type'], $_POST['crud'])));
    }
    elseif ($process == "GET_TIMEPHASE"){
        print_r(json_encode(GET_TIMEPHASE($iConRLM, $_REQUEST['INPUT'])));
    }
    elseif ($process == "SET_TIMEPHASE") {
        print_r(json_encode(SET_TIMEPHASE($iConRLM, $_POST['data'], $_POST['user_details'])));
    }
    elseif ($process == "EDIT_TIMEPHASE") {
        print_r(json_encode(EDIT_TIMEPHASE($iConRLM, $_POST['data'], $_POST['user_details'])));
    }
    elseif ($process == "DELETE_TIMEPHASE") {
        print_r(json_encode(DELETE_TIMEPHASE_V2($iConRLM, $_POST['data'], $_POST['user_details'])));
    }
    #elseif ($process == "DELETE_TIMEPHASE") {
    #    print_r(json_encode(DELETE_TIMEPHASE($iConRLM, $_POST['data'], $_POST['user_details'])));
    #}

    mysqli_close($iConRLM);


    function GET_CURRENT_WEEK($iConRLM){
        $week = "";
        $date = "";
        $current_date = date('Y-m-d');
        $current_week = 'SELECT MFG_YEAR, WEEK_NUMBER, MFG_DATE FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE = "'.$current_date.'" ORDER BY MFG_DATE LIMIT 1';
        $current_week_res = ExecuteIQuery($current_week,$iConRLM);
        while($row = mysqli_fetch_assoc($current_week_res)){
            $week = "FY".substr($row['MFG_YEAR'], -2)."_W".$row['WEEK_NUMBER'];
            $date = date('Y-m-d', strtotime('last Sunday', strtotime($row['MFG_DATE'])));
        }
        return [
            "WEEK" => $week,
            "DATE" => $date
        ];
    }

    function GET_CALENDAR($iConRLM, $date, $instance_type, $crud){
        $calendar = [];
        if ($crud == "edit") {
            $adjust_date = strtotime(''.$date.' - 7 week');
            $date = date('Y-m-d',$adjust_date);
        }
        else{
            if ($crud == "paginate-next") {
                $adjust_date = strtotime(''.$date.' + 1 week');
                $date = date('Y-m-d',$adjust_date);
            }
            elseif ($crud == "paginate-previous"){
                $adjust_date = strtotime(''.$date.' - 13 week');
                $date = date('Y-m-d',$adjust_date);
            }
        }
        $current_date = ($date != null && $date != '') ? $date : date('Y-m-d');
        $week_start = "";
        $start_date = ((date('N', strtotime($current_date)) >= 6)) ? $current_date : date('Y-m-d', strtotime('last Sunday', strtotime($current_date)));
        $mfg_year = 0;

        $get_week_no = 'SELECT WEEK_NUMBER, MFG_YEAR FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE >= "'.$start_date.'" ORDER BY MFG_DATE LIMIT 1';
        $get_week_no_res = ExecuteIQuery($get_week_no,$iConRLM);
        while($row = mysqli_fetch_assoc($get_week_no_res)){
            $week_start = ($instance_type == "clone") ? (int)$row['WEEK_NUMBER'] + 1 : (int)$row['WEEK_NUMBER'];
            $mfg_year = $row['MFG_YEAR'];
        }

        $week_diff = 0;
        $week_end = (int)$week_start + 12;
        $week_list = [];
        $start_year = (explode("-", $start_date)[0] != $mfg_year) ? $mfg_year : explode("-", $start_date)[0];
        $end_year = (int)$start_year + 1;
        $new_fy_start_date = "";

        $max_week = 0;
        $get_max_week = 'SELECT MAX(WEEK_NUMBER) FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE >= "'.$start_year.'-01-01" AND MFG_DATE <= "'.$end_year.'-12-31"';
        $get_max_week_res = ExecuteIQuery($get_max_week,$iConRLM);
        while($row = mysqli_fetch_assoc($get_max_week_res)){
            $max_week = (int)$row['MAX(WEEK_NUMBER)'];
        }

        if ($week_end > $max_week) {
            $week_diff = (int)$week_end - $max_week;
        }

        $get_week_list = 'SELECT *, DAYNAME(MFG_DATE) AS DAY_NAME, MONTHNAME(MFG_DATE) AS MONTH_NAME FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE >= "'.$start_date.'" 
        AND MFG_YEAR = '.$start_year.' AND WEEK_NUMBER BETWEEN '.$week_start.' AND '.$week_end.' AND WEEK_NUMBER <= '.$max_week.' ORDER BY MFG_DATE ASC';

        $get_week_list_res = ExecuteIQuery($get_week_list,$iConRLM);
        while($row = mysqli_fetch_assoc($get_week_list_res)){
            array_push($week_list, $row);
            
            $month = $row['MONTH_NAME'];
            if (!in_array($month, $calendar)) {
                $calendar[$month] = [];
            }
        }

        if ($week_diff > 0) {
            $new_fy_start_year = $end_year;
            $new_fy_start_date = date('Y-m-d', strtotime('last Sunday', strtotime(end($week_list)['MFG_DATE'])));
            $new_fy_week_list = 'SELECT *, DAYNAME(MFG_DATE) AS DAY_NAME, MONTHNAME(MFG_DATE) AS MONTH_NAME FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE >= "'.$new_fy_start_date.'" 
            AND MFG_YEAR = '.$new_fy_start_year.' AND WEEK_NUMBER BETWEEN 1 AND '.$week_diff.' AND WEEK_NUMBER <= '.$week_diff.' ORDER BY MFG_DATE ASC';
            $new_fy_week_list_res = ExecuteIQuery($new_fy_week_list,$iConRLM);
            while($row = mysqli_fetch_assoc($new_fy_week_list_res)){
                array_push($week_list, $row);
                
                $month = $row['MONTH_NAME'];
                if (!in_array($month, $calendar)) {
                    $calendar[$month] = [];
                }
            }
        }

        //CONSTRUCT MONTH + WEEK NUMBER CONTAINERS
        foreach ($week_list as $key => $wl) {
            $skip = 0;
            $wn = ((int)$wl['WEEK_NUMBER'] <= 9) ? "0".$wl['WEEK_NUMBER'] : $wl['WEEK_NUMBER'];
            $fyw = "FY".substr($wl['MFG_YEAR'], -2)."_W".$wn;

            foreach ($calendar as $key2 => $cl) {
                if (array_key_exists($fyw, $cl)) {
                    $skip++;
                }
            }

            if ($skip == 0) {
                if (!in_array($fyw, $calendar[$wl['MONTH_NAME']])) {
                    $calendar[$wl['MONTH_NAME']][$fyw] = [];
                }
            }
        }

        //CONSTRUCT FINAL WEEK LIST
        foreach ($week_list as $key => $wl) {
            $skip = 0;
            $target_key = "";
            $wn = ((int)$wl['WEEK_NUMBER'] <= 9) ? "0".$wl['WEEK_NUMBER'] : $wl['WEEK_NUMBER'];
            $fyw = "FY".substr($wl['MFG_YEAR'], -2)."_W".$wn;
            foreach ($calendar as $key2 => $cl) {
                if (array_key_exists($fyw, $cl)) {
                    $skip++;
                    $target_key = $key2; 
                }
            }
            if ($skip > 0) {
                array_push($calendar[$target_key][$fyw], $wl);
            }
        }

        return $calendar;
    }
    
    function GET_TIMEPHASE($iConRLM, $data){
        $result = [];
        $get_tp = 'SELECT * FROM BRAIN.TP_INSTANCE_MAIN WHERE SAP_RTE_ID = "'.$data.'" ORDER BY INSTANCE ASC';
        $get_tp_res = ExecuteIQuery($get_tp,$iConRLM);
        while($row = mysqli_fetch_assoc($get_tp_res)){
            array_push($result, $row);
        }
        return $result;
    }

    function SET_TIMEPHASE($iConRLM, $data, $user_details){
        $new_num = 0;
        $latest_instance = 'SELECT MAX(INSTANCE) FROM BRAIN.TP_INSTANCE_MAIN WHERE SAP_RTE_ID = "'.$data['route'].'"';
        $latest_instance_res = ExecuteIQuery($latest_instance,$iConRLM);
        while($row = mysqli_fetch_assoc($latest_instance_res)){
            $new_num = (int)$row['MAX(INSTANCE)'] + 1;
        }

        $insert_tp = 'INSERT INTO BRAIN.TP_INSTANCE_MAIN VALUES ("", "'.$data['route'].'", "'.$data['date'].'", "'.$data['week'].'", "'.$new_num.'", "'.$data['process'].'", "'.$data['notes'].'")';
        $insert_tp_res = ExecuteIQuery($insert_tp,$iConRLM);
        return $insert_tp_res;
    }

    function EDIT_TIMEPHASE($iConRLM, $data, $user_details){
        $new_instance_num = $data['num'];
        $new_fyww = (int)"20".substr($data['week'], 2, 2).substr($data['week'], 6, 2);

        $get_instance = 'SELECT * FROM BRAIN.TP_INSTANCE_MAIN WHERE SAP_RTE_ID = "'.$data['route'].'" AND TPI_ID != '.$data['id'].' ORDER BY INSTANCE ASC';
        $get_instance_res = ExecuteIQuery($get_instance,$iConRLM);
        while($row = mysqli_fetch_assoc($get_instance_res)){
             
            $ext_fyww = (int)$row['FYWW'];
            $reorder_instance = "";

            if ($new_fyww > $ext_fyww) {
                if((int)$data['num'] < $row['INSTANCE']){
                    $new_instance_num = $row['INSTANCE'];
                    $new_ext_instance = $row['INSTANCE'] - 1;
                    $reorder_instance = 'UPDATE BRAIN.TP_INSTANCE_MAIN SET INSTANCE = '.$new_ext_instance.' WHERE TPI_ID = '.$row['TPI_ID'].'';
                }
            }
            else if($new_fyww < $ext_fyww){
                if ((int)$data['num'] > $row['INSTANCE']) {
                    $new_instance_num = $row['INSTANCE'];
                    $new_ext_instance = $row['INSTANCE'] + 1;
                    $reorder_instance = 'UPDATE BRAIN.TP_INSTANCE_MAIN SET INSTANCE = '.$new_ext_instance.' WHERE TPI_ID = '.$row['TPI_ID'].'';
                }
            }

            if ($reorder_instance != "") {
                $reorder_instance_res = ExecuteIQuery($reorder_instance,$iConRLM);
            }
        }

        $update_tp = 'UPDATE BRAIN.TP_INSTANCE_MAIN SET EFF_START_DT = "'.$data['date'].'", FYWW = '.$new_fyww.', INSTANCE = '.$new_instance_num.', PROCESS_TYPE = "'.$data['process'].'", NOTES = "'.$data['notes'].'" WHERE TPI_ID = '.$data['id'].'';
        $update_tp_res = ExecuteIQuery($update_tp,$iConRLM);
        return $update_tp_res;
    }

    function DELETE_TIMEPHASE_V2($iConRLM,$data){
        $current_num = (int)$data['num'];

        #2025-10-09 RM
        #why retrieve them if you dont actually care what the actual sequence numbers are?
        #if youre just subtracting 1 from each instance > the current then just have mysql do it
        #2025-10-09 RM ALWAYS ALWAYS ALWAYS escape any inputs.

        $arySQL = array();

        $arySQL[] = "
            UPDATE
            BRAIN.TP_INSTANCE_MAIN
            SET INSTANCE = INSTANCE - 1
            WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$data['route'])."'
            AND INSTANCE > ".mysqli_real_escape_string($iConRLM,$current_num).";";

        $arySQL[] = "
            INSERT INTO
            BRAIN.TP_INSTANCE_HIST_MASTER
            (TPI_ID,HIST_TYPE,HIST_DT)
            VALUES
            (
                ".mysqli_real_escape_string($iConRLM,$data['id']).",
                'DELETE',
                NOW()
            );";

        $arySQL[] = "
            DELETE 
            FROM BRAIN.TP_INSTANCE_MAIN 
            WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$data['id']).";";

        foreach($arySQL as $strSQL) {
            $delete_tp_res = ExecuteIQuery($strSQL,$iConRLM);
        }

        return $delete_tp_res;
    }


    function DELETE_TIMEPHASE($iConRLM, $data){
        $current_num = (int)$data['num'];

        $get_instance = 'SELECT * FROM BRAIN.TP_INSTANCE_MAIN WHERE SAP_RTE_ID = "'.$data['route'].'" ORDER BY INSTANCE ASC';
        $get_instance_res = ExecuteIQuery($get_instance,$iConRLM);
        while($row = mysqli_fetch_assoc($get_instance_res)){
            if ($row['INSTANCE'] > $current_num) {
                $new_num = $row['INSTANCE'] - 1;
                $update_instance = 'UPDATE BRAIN.TP_INSTANCE_MAIN SET INSTANCE = '.$new_num.' WHERE TPI_ID = '.$row['TPI_ID'].'';
                $update_instance_res = ExecuteIQuery($update_instance,$iConRLM);
            }
        }

        $delete_tp = 'DELETE FROM BRAIN.TP_INSTANCE_MAIN WHERE TPI_ID = '.$data['id'].'';
        $delete_tp_res = ExecuteIQuery($delete_tp,$iConRLM);
        return $delete_tp_res;
    }
?>