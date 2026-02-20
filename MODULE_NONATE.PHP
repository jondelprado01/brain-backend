<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("../TEST/ADI_FUNCTIONS_022.PHP");
    ini_set("error_reporting",E_ALL);
    ini_set("display_errors",1);

    $iConRLM = Open_ILink("MXHDAFOT01L");
    $iConRLMxhT = Open_ILink("MXHTAFOT01L");
    // MXHTAFOT01L
    // MXHDAFOT01L

    $aryRequest = $_REQUEST;
    $requestMedthod = $_SERVER['REQUEST_METHOD'];
    date_default_timezone_set('Asia/Manila');

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

    switch ($process) {
        case 'SEARCH_BOARD_BURNIN':
            print_r(json_encode(SEARCH_BOARD_BURNIN($iConRLMxhT, $_POST['payload'])));
            break;

        case 'GET_BOARD_BURNIN':
            print_r(json_encode(GET_BOARD_BURNIN($iConRLM)));
            break;

        case 'ADD_BOARD_BURNIN':
            print_r(json_encode(ADD_BOARD_BURNIN($iConRLM, $_POST['payload'], ['emp_name' => 'Jonathan Del Prado'])));
            break;

        case 'SEARCH_BOARD_SOCKET':
            print_r(json_encode(SEARCH_BOARD_SOCKET($iConRLMxhT, $_POST['payload'])));
            break;

        case 'GET_SOCKET':
            print_r(json_encode(GET_SOCKET($iConRLM)));
            break;

        case 'ADD_SOCKET':
            print_r(json_encode(ADD_SOCKET($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;

        case 'SEARCH_HW':
            print_r(json_encode(SEARCH_HW($iConRLMxhT, $_POST['payload'])));
            break;
            
        case 'GET_HW_CAPACITY':
            print_r(json_encode(GET_HW_CAPACITY($iConRLM)));
            break;

        case 'ADD_HW_CAPACITY':
            print_r(json_encode(ADD_HW_CAPACITY($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;

        case 'EDIT_HW_CAPACITY':
            print_r(json_encode(EDIT_HW_CAPACITY($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;

        case 'DELETE_HW_CAPACITY':
            print_r(json_encode(DELETE_HW_CAPACITY($iConRLM, $_POST['payload'])));
            break;

        case 'GET_WEEKS':
            print_r(json_encode(GET_WEEKS($iConRLM)));
            break;

        case 'MASS_SEARCH_BOARD_BURNIN':
            print_r(json_encode(MASS_SEARCH_BOARD_BURNIN($iConRLMxhT, $_POST['partnum'], $_POST['board'])));
            break;

        default:
            # code...
            break;
    }

    mysqli_close($iConRLM);

    function SEARCH_BOARD_BURNIN($iConRLM, $data){

        $result = [];

        //OLD BURNIN HOURS QUERY
        // $query = 'SELECT DISTINCT
        //         MFG_PART_NUM,
        //         BURNIN_BOARD.HW_NM BURNIN_BOARD,
        //         BURNIN_HOURS_1.ATTR_VAL_CHAR BURNIN_HOURS_1,
        //         BURNIN_TYPE.ATTR_VAL_CHAR BURNIN_TYPE
        //         FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA
        //         INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_SET BURNIN_BOARD ON AAA.HW_SET_ID = BURNIN_BOARD.HW_SET_ID AND BURNIN_BOARD.HW_TYPE = "BURNIN_BOARD"
        //         LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_HOURS_1 ON AAA.ATTR_SET_ID = BURNIN_HOURS_1.ATTR_SET_ID AND BURNIN_HOURS_1.ATTR_NM = "BURNIN_HOURS_1"
        //         LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_TYPE ON AAA.ATTR_SET_ID = BURNIN_TYPE.ATTR_SET_ID AND BURNIN_TYPE.ATTR_NM = "BURNIN_TYPE"
        //         WHERE BURNIN_BOARD.HW_NM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%" OR MFG_PART_NUM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%"';

        //NEW BURNIN HOURS QUERY - WILL REUSE IN THE FUTURE
        #IFNULL(BURNIN_HOURS_2.ATTR_VAL_NUM,BURNIN_HOURS_2.ATTR_VAL_CHAR) BURNIN_HOURS_2,
        #LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_HOURS_2 ON AAA.ATTR_SET_ID = BURNIN_HOURS_2.ATTR_SET_ID AND BURNIN_HOURS_2.ATTR_NM = "BURNIN_HOURS_2"
        #HAVING BURNIN_BOARD IS NOT NULL OR BURNIN_HOURS_1 IS NOT NULL OR BURNIN_HOURS_2 IS NOT NULL OR BURNIN_TYPE IS NOT NULL

        // $query = 'SELECT DISTINCT
        //         MFG_PART_NUM,
        //         BURNIN_BOARD.HW_NM BURNIN_BOARD,
        //         IFNULL(BURNIN_HOURS_1.ATTR_VAL_NUM,BURNIN_HOURS_1.ATTR_VAL_CHAR) BURNIN_HOURS_1,
                
        //         BURNIN_TYPE.ATTR_VAL_CHAR BURNIN_TYPE
        //         FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA
        //         INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_SET BURNIN_BOARD ON AAA.HW_SET_ID = BURNIN_BOARD.HW_SET_ID AND BURNIN_BOARD.HW_TYPE = "BURNIN_BOARD"
        //         LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_HOURS_1 ON AAA.ATTR_SET_ID = BURNIN_HOURS_1.ATTR_SET_ID AND BURNIN_HOURS_1.ATTR_NM = "BURNIN_HOURS_1"
                
        //         LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_TYPE ON AAA.ATTR_SET_ID = BURNIN_TYPE.ATTR_SET_ID AND BURNIN_TYPE.ATTR_NM = "BURNIN_TYPE"
        //         WHERE BURNIN_BOARD.HW_NM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%" OR MFG_PART_NUM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%"
        //         HAVING BURNIN_BOARD IS NOT NULL OR BURNIN_HOURS_1 IS NOT NULL OR BURNIN_TYPE IS NOT NULL';
        
        $query = 'SELECT
                MFG_PART_NUM,
                COALESCE(BURNIN_BOARD) BURNIN_BOARD,
                COALESCE(BURNIN_HOURS_1) BURNIN_HOURS_1,
                COALESCE(BURNIN_TYPE) BURNIN_TYPE
                FROM
                (
                    SELECT DISTINCT
                    MFG_PART_NUM,
                    BURNIN_BOARD.HW_NM BURNIN_BOARD,
                    IFNULL(BURNIN_HOURS_1.ATTR_VAL_NUM,BURNIN_HOURS_1.ATTR_VAL_CHAR) BURNIN_HOURS_1,
                    BURNIN_TYPE.ATTR_VAL_CHAR BURNIN_TYPE
                    FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA
                    INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_SET BURNIN_BOARD ON AAA.HW_SET_ID = BURNIN_BOARD.HW_SET_ID AND BURNIN_BOARD.HW_TYPE = "BURNIN_BOARD"
                    LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_HOURS_1 ON AAA.ATTR_SET_ID = BURNIN_HOURS_1.ATTR_SET_ID AND BURNIN_HOURS_1.ATTR_NM = "BURNIN_HOURS_1"
                    LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_TYPE ON AAA.ATTR_SET_ID = BURNIN_TYPE.ATTR_SET_ID AND BURNIN_TYPE.ATTR_NM = "BURNIN_TYPE"
                    WHERE BURNIN_BOARD.HW_NM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%" OR MFG_PART_NUM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%"
                    ORDER BY IF(BURNIN_BOARD IS NOT NULL,0,1), IF(BURNIN_HOURS_1 IS NOT NULL,0,1), IF(BURNIN_TYPE IS NOT NULL,0,1)
                ) BI_SUB
                GROUP BY MFG_PART_NUM';

        $res_query = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($res_query)){
            if ($row['BURNIN_HOURS_1'] == NULL) {
                $row['BURNIN_HOURS_1'] = 24;
            }
            $string = $row['MFG_PART_NUM']."|".$row['BURNIN_BOARD'];
            $row['BID'] = hash("sha256", $string);
            array_push($result, $row);
        }
        return $result;
    }

    function MASS_SEARCH_BOARD_BURNIN($iConRLM, $part_data, $board_data){
        $result = [];
        $query = 'SELECT
                MFG_PART_NUM,
                COALESCE(BURNIN_BOARD) BURNIN_BOARD,
                COALESCE(BURNIN_HOURS_1) BURNIN_HOURS_1,
                COALESCE(BURNIN_TYPE) BURNIN_TYPE
                FROM
                (
                    SELECT DISTINCT
                    MFG_PART_NUM,
                    BURNIN_BOARD.HW_NM BURNIN_BOARD,
                    IFNULL(BURNIN_HOURS_1.ATTR_VAL_NUM,BURNIN_HOURS_1.ATTR_VAL_CHAR) BURNIN_HOURS_1,
                    BURNIN_TYPE.ATTR_VAL_CHAR BURNIN_TYPE
                    FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA
                    INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_SET BURNIN_BOARD ON AAA.HW_SET_ID = BURNIN_BOARD.HW_SET_ID AND BURNIN_BOARD.HW_TYPE = "BURNIN_BOARD"
                    LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_HOURS_1 ON AAA.ATTR_SET_ID = BURNIN_HOURS_1.ATTR_SET_ID AND BURNIN_HOURS_1.ATTR_NM = "BURNIN_HOURS_1"
                    LEFT JOIN BODS_JDA_ADI_EXPORT.RESOURCE_ATTR BURNIN_TYPE ON AAA.ATTR_SET_ID = BURNIN_TYPE.ATTR_SET_ID AND BURNIN_TYPE.ATTR_NM = "BURNIN_TYPE"
                    WHERE BURNIN_BOARD.HW_NM IN ("'.implode('","', $board_data).'") OR MFG_PART_NUM IN ("'.implode('","', $part_data).'")
                    ORDER BY IF(BURNIN_BOARD IS NOT NULL,0,1), IF(BURNIN_HOURS_1 IS NOT NULL,0,1), IF(BURNIN_TYPE IS NOT NULL,0,1)
                ) BI_SUB
                GROUP BY MFG_PART_NUM';

        $res_query = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($res_query)){
            if ($row['BURNIN_HOURS_1'] == NULL) {
                $row['BURNIN_HOURS_1'] = 24;
            }
            $string = $row['MFG_PART_NUM']."|".$row['BURNIN_BOARD'];
            $row['BID'] = hash("sha256", $string);
            array_push($result, $row);
        }
        return $result;
    }

    function GET_BOARD_BURNIN($iConRLM){
        $result = [];
        $query = 'SELECT * FROM TEST.ADI_NONATE_TYPE_1';
        $res_query = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($res_query)){
            array_push($result, $row);
        }
        return $result;
    }

    function ADD_BOARD_BURNIN($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $result = '';
        $values = [];
        $change_log = [];

        foreach ($data as $key => $dt) {

            $is_exists = 0;
            $check_rec = 'SELECT COUNT(*) FROM TEST.ADI_NONATE_TYPE_1 WHERE HASH = "'.$dt[6].'"';
            $res_check_rec = ExecuteIQuery($check_rec,$iConRLM);
            while($row = mysqli_fetch_assoc($res_check_rec)){
                $is_exists = $row['COUNT(*)'];
            }

            if ($is_exists > 0) {
                $update_query = 'UPDATE TEST.ADI_NONATE_TYPE_1 SET LOAD_HOURS = "'.$dt[3].'", LOAD_UNLOAD = "'.$dt[4].'", UPDATED_AT = "'.$curdate.'", UPDATED_BY = "'.$user['emp_name'].'" WHERE HASH = "'.$dt[6].'" ';
                $result = ExecuteIQuery($update_query,$iConRLM);
                array_push($change_log, $dt);
            }
            else{
                $query = 'INSERT INTO TEST.ADI_NONATE_TYPE_1 VALUES ("", "'.$dt[0].'", "'.$dt[1].'", "'.$dt[2].'", "'.$dt[3].'", "'.$dt[4].'", "'.$dt[6].'", "'.$curdate.'", "'.$user['emp_name'].'", "", "")';
                $result = ExecuteIQuery($query,$iConRLM);

                $get_id = 'SELECT LAST_INSERT_ID()';
                $get_id_res = ExecuteIQuery($get_id, $iConRLM);

                while($row = mysqli_fetch_assoc($get_id_res)){
                    $dt[8] = $row['LAST_INSERT_ID()'];
                    array_push($change_log, $dt);
                }
            }
        }

        return [
            "STATUS" => $result,
            "CHANGE_LOG_DATA" => $change_log,
            "OLD_DATA" => []
        ];
    }

    function SEARCH_BOARD_SOCKET($iConRLM, $data){
        $result = [];
        $query = 'SELECT DISTINCT
                BURNIN_BOARD.HW_NM BURNIN_BOARD
                FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA
                INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_SET BURNIN_BOARD ON AAA.HW_SET_ID = BURNIN_BOARD.HW_SET_ID AND BURNIN_BOARD.HW_TYPE = "BURNIN_BOARD"
                WHERE BURNIN_BOARD.HW_NM LIKE "'.$data.'%"';

        $res_query = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($res_query)){
            $row['SOCKET_EFFICIENCY'] = '95';
            $string = $row['BURNIN_BOARD']."|".$row['SOCKET_EFFICIENCY'];
            $row['SEID'] = hash("sha256", $string);
            array_push($result, $row);
        }
        return $result;
    }

    function GET_SOCKET($iConRLM){
        $result = [];
        $query = 'SELECT * FROM TEST.ADI_NONATE_TYPE_2';
        $res_query = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($res_query)){
            array_push($result, $row);
        }
        return $result;
    }

    function ADD_SOCKET($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $values = [];
        $result = '';

        foreach ($data as $key => $dt) {

            $is_exists = 0;
            $check_rec = 'SELECT COUNT(*) FROM TEST.ADI_NONATE_TYPE_2 WHERE HASH = "'.$dt[2].'"';
            $res_check_rec = ExecuteIQuery($check_rec,$iConRLM);
            while($row = mysqli_fetch_assoc($res_check_rec)){
                $is_exists = $row['COUNT(*)'];
            }

            if ($is_exists > 0) {
                $update_query = 'UPDATE TEST.ADI_NONATE_TYPE_2 SET SOCKET_EFFICIENCY = "'.$dt[1].'", UPDATED_AT = "'.$curdate.'", UPDATED_BY = "'.$user['emp_name'].'" WHERE HASH = "'.$dt[2].'" ';
                $result = ExecuteIQuery($update_query,$iConRLM);
            }
            else{
                array_push($values, '("", "'.$dt[0].'", "'.$dt[1].'", "'.$dt[2].'", "'.$curdate.'", "'.$user['emp_name'].'", "", "")');
            }
        }

        if (count($values) > 0) {
            $query = 'INSERT INTO TEST.ADI_NONATE_TYPE_2 VALUES '.implode(",", $values).'';
            $result = ExecuteIQuery($query,$iConRLM);
        }
        return $result;
    }

    function SEARCH_HW($iConRLM, $data){
        $hw_arr = [];
        $res_arr = [];
            
        if ($data != null || $data != ''){

            $get_hw = 'SELECT DISTINCT SITE_NUM, RES_AREA, HW_TYPE, REPLACE(HW_NM,"_ZEROCAP","") HW_NM FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_SET HWS ON AAA.HW_SET_ID = HWS.HW_SET_ID
                       WHERE HW_NM LIKE "'.$data.'%" AND HW_NM NOT LIKE "%FAKE" AND HW_TYPE = "BURNIN_BOARD"
                       UNION 
                       SELECT DISTINCT SITE_NUM, RES_AREA, HW_TYPE, REPLACE(HW_NM,"_ZEROCAP","") HW_NM FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA INNER JOIN BODS_JDA_ADI_EXPORT.HARDWARE_ALTERNATES HWA ON AAA.HW_SET_ID = HWA.HW_SET_ID
                       WHERE HW_NM LIKE "'.$data.'%" AND HW_NM NOT LIKE "%FAKE" AND HW_TYPE = "BURNIN_BOARD"';
            

            $res_hw = ExecuteIQuery($get_hw, Open_ILink("MXHTAFOT01L"));
            while($row = mysqli_fetch_assoc($res_hw)){
                array_push($hw_arr, $row);
            }

            $hw_col_arr["HW_NM"] = array_unique(array_map(function($item) {
                return $item['HW_NM'];
            }, $hw_arr));

            $hw_col_arr["SITE_NUM"] = array_unique(array_map(function($item) {
                return $item['SITE_NUM'];
            }, $hw_arr));

            $hw_col_arr["RES_AREA"] = array_unique(array_map(function($item) {
                return $item['RES_AREA'];
            }, $hw_arr));

            $hw_col_arr["HW_TYPE"] = array_unique(array_map(function($item) {
                return $item['HW_TYPE'];
            }, $hw_arr));

            $get_hw_cap = 'SELECT DISTINCT SITE_NUM, RES_AREA, RES_TYPE, RES_NM, EFF_RES_CNT FROM BODS_JDA_ADI.RESOURCE_CAPACITY WHERE SITE_NUM IN ("'.implode('","', $hw_col_arr["SITE_NUM"]).'") AND 
                            RES_AREA IN ("'.implode('","', $hw_col_arr["RES_AREA"]).'") AND RES_TYPE IN ("'.implode('","', $hw_col_arr["HW_TYPE"]).'") AND 
                            RES_NM IN ("'.implode('","', $hw_col_arr["HW_NM"]).'")
                            AND EFF_START_DT IS NOT NULL AND EFF_RES_CNT > 0';
            $res_hw_cap = ExecuteIQuery($get_hw_cap, Open_ILink("MXHDAFOT01L")); //CHANGE THIS IN THE FUTURE
            while($row = mysqli_fetch_assoc($res_hw_cap)){
                $res_arr[$row['RES_TYPE']][] = [
                    'HW_NM' => $row['RES_NM'],
                    'HW_TYPE' => $row['RES_TYPE'],
                    'REQUIRED_QTY' => (double)$row['EFF_RES_CNT'],
                    'SITE_NUM' => $row['SITE_NUM'],
                    'RES_AREA' => $row['RES_AREA']
                ];
            }

            return $res_arr;

            // foreach ($hw_arr as $key => $hw) {
            //     $cap = 0;
            //     $get_hw_cap = 'SELECT EFF_RES_CNT FROM BODS_JDA_ADI.RESOURCE_CAPACITY WHERE SITE_NUM = "'.$hw['SITE_NUM'].'" AND RES_AREA = "'.$hw['RES_AREA'].'" AND RES_TYPE = "'.$hw['HW_TYPE'].'" AND RES_NM = "'.$hw['HW_NM'].'"
            //                   AND EFF_START_DT IS NOT NULL';
            //     $res_hw_cap = ExecuteIQuery($get_hw_cap, $iConRLM);
            //     while($row = mysqli_fetch_assoc($res_hw_cap)){
            //         $cap = $row['EFF_RES_CNT'];
            //     }

            //     $res_arr[$hw['HW_TYPE']][] = [
            //         'HW_NM' => $hw['HW_NM'],
            //         'HW_TYPE' => $hw['HW_TYPE'],
            //         'REQUIRED_QTY' => (double)$cap,
            //         'SITE_NUM' => $hw['SITE_NUM'],
            //         'RES_AREA' => $hw['RES_AREA']
            //     ];
            // }



            // $hw_type_arr = GET_HW_TYPES();
            // $get_hw = 'SELECT
            //             PRR.SITE_NUM,
            //             PRR.RES_AREA,
            //             REF.HW_NM,
            //             HMS.HW_TYPE_HMS AS HW_TYPE,
            //             MIN(HMS.AVAIL_QTY) AVAIL_QTY,
            //             MIN(HMS.TOTAL_QTY) TOTAL_QTY
            //             FROM BRAIN.HW_NONBOARD_HMS HMS
            //             INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
            //             INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
            //             WHERE REF.HW_NM LIKE "'.$data.'%" AND
            //             HMS.HW_TYPE_HMS IN ("'.implode('","', $hw_type_arr).'")
            //             GROUP BY SITE_NUM, RES_AREA, HW_NM, HW_TYPE LIMIT 100';
            
            // $res_hw = ExecuteIQuery($get_hw, Open_ILink("MXHTAFOT01L"));
            
            // while($row = mysqli_fetch_assoc($res_hw)){
            //     $res_arr[$row['HW_TYPE']][] = [
            //         'HW_NM' => $row['HW_NM'],
            //         'HW_TYPE' => $row['HW_TYPE'],
            //         'REQUIRED_QTY' => (double)$row['AVAIL_QTY'],
            //         'SITE_NUM' => $row['SITE_NUM'],
            //         'RES_AREA' => $row['RES_AREA']
            //     ];
            // }
        }

        return $res_arr;
    }

    function GET_HW_TYPES(){
        $iConRLM = Open_ILink("MXHTAFOT01L");
        $res_arr = [];

        $query = 'SELECT DISTINCT
                    CA_VALUE HW_TYPE
                    FROM ADI_ESLDATA.CUSTOM_ACTION CA
                    INNER JOIN ADI_ESLDATA.CUSTOM_ACTION_IDEN CAI ON CA.CA_ID = CAI.CA_ID
                    WHERE CA.CA_NAME = "HWTYPE_PLANNABLE"
                    HAVING HW_TYPE NOT IN ("BURNIN_BOARD")
                    ORDER BY HW_TYPE';

        $result = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($result)){
            array_push($res_arr, $row['HW_TYPE']);
        }
        return $res_arr;
    }

    function GET_HW_CAPACITY($iConRLM){
        $capacity_arr = [];
        $get_capacity = 'SELECT * FROM TEST.ADI_NONATE_TYPE_3 ORDER BY CREATED_AT DESC';
        $res_capacity = ExecuteIQuery($get_capacity,$iConRLM);
        while($row = mysqli_fetch_assoc($res_capacity)){
            array_push($capacity_arr, $row);
        }
        return $capacity_arr;
    }

    function ADD_HW_CAPACITY($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $values = [];
        $result = '';
        foreach ($data as $key => $dt) {
            array_push($values, '("", "'.strtoupper($dt[0]).'", "'.$dt[1].'", "'.$dt[2].'", "'.$dt[3].'", "'.$dt[4].'", "'.$dt[5].'", "'.$dt[7].'", "'.$dt[8].'", "'.$curdate.'", "'.$user['emp_name'].'", "", "")');
        }
        if (count($values) > 0) {
            $query = 'INSERT INTO TEST.ADI_NONATE_TYPE_3 VALUES '.implode(",", $values).'';
            $result = ExecuteIQuery($query,$iConRLM);
        }
        return $result;
    }

    function EDIT_HW_CAPACITY($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $query = 'UPDATE TEST.ADI_NONATE_TYPE_3 SET EFF_START = "'.$data[3].'", EFF_END = "'.$data[4].'", OVERRIDE_CAP = "'.$data[2].'", UPDATED_AT = "'.$curdate.'", UPDATED_BY = "'.$user['emp_name'].'" WHERE ID = '.$data[0].'';
        $result = ExecuteIQuery($query,$iConRLM);
        return $result;
    }

    function DELETE_HW_CAPACITY($iConRLM, $data){
        $ids = [];
        $curdate = date('Y-m-d H:i:s');
        foreach ($data as $key => $dt) {
            array_push($ids, $dt['ID']);
        }
        $query = 'DELETE FROM  TEST.ADI_NONATE_TYPE_3 WHERE ID IN ("'.implode('","', $ids).'")';
        $result = ExecuteIQuery($query,$iConRLM);
        return $result;
    }

    function GET_WEEKS($iConRLM){
        $week_arr = [];
        $query = 'SELECT DISTINCT MFG_YEAR * 100 + WEEK_NUMBER FYWW FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE >= DATE_FORMAT(NOW(),"%Y-%m-%d") ORDER BY FYWW LIMIT 0,53';
        $result = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($result)){
            array_push($week_arr, $row['FYWW']);
        }
        return $week_arr;
    }

?>