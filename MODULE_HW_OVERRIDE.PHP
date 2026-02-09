<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("../TEST/ADI_FUNCTIONS_022.PHP");
    ini_set("error_reporting",E_ALL);
    ini_set("display_errors",1);

    $iConRLM = Open_ILink("MXHDAFOT01L");
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
        case 'SEARCH_PART':
            print_r(json_encode(SEARCH_PARTS($iConRLM, $_POST['payload'])));
            break;
        case 'SEARCH_HW':
            print_r(json_encode(SEARCH_HW($iConRLM, $_POST['payload'])));
            break;
        case 'SEARCH_GP_HW':
            print_r(json_encode(SEARCH_GP_HW($iConRLM, $_POST['payload'])));
            break;
        case 'SEARCH_NON_BOARD':
            print_r(json_encode(SEARCH_NON_BOARD($iConRLM, $_POST['payload'], $_POST['limit'])));
            break;
        case 'GET_DUMMY_HW':
            print_r(json_encode(GET_DUMMY_HW($iConRLM)));
            break;
        case 'GET_HW_CAPACITY':
            print_r(json_encode(GET_HW_CAPACITY($iConRLM)));
            break;
        case 'GET_NON_BOARD':
            print_r(json_encode(GET_NON_BOARD($iConRLM)));
            break;
        case 'GET_WEEKS':
            print_r(json_encode(GET_WEEKS($iConRLM)));
            break;
        case 'GET_OPEN_WEEK':
            print_r(json_encode(GET_OPEN_WEEK($iConRLM, $_POST['year'], $_POST['week'])));
            break;
        case 'ADD_DUMMY_HW':
            print_r(json_encode(ADD_DUMMY_HW($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;
        case 'EDIT_DUMMY_HW':
            print_r(json_encode(EDIT_DUMMY_HW($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;
        case 'DELETE_DUMMY_HW':
            print_r(json_encode(DELETE_DUMMY_HW($iConRLM, $_POST['payload'], false)));
            break;
        case 'DELETE_DUMMY_HW_CSV':
            print_r(json_encode(DELETE_DUMMY_HW($iConRLM, $_POST['payload'], true)));
            break;
        case 'ADD_HW_CAPACITY':
            print_r(json_encode(ADD_HW_CAPACITY($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;
        case 'EDIT_HW_CAPACITY':
            print_r(json_encode(EDIT_HW_CAPACITY($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;
        case 'DELETE_HW_CAPACITY':
            print_r(json_encode(DELETE_HW_CAPACITY($iConRLM, $_POST['payload'], false)));
            break;
        case 'DELETE_HW_CAPACITY_CSV':
            print_r(json_encode(DELETE_HW_CAPACITY($iConRLM, $_POST['payload'], true)));
            break;
        case 'ADD_NON_BOARD':
            print_r(json_encode(ADD_NON_BOARD($iConRLM, $_POST['payload'], $_POST['user_details'])));
            break;
        case 'DELETE_NON_BOARD':
            print_r(json_encode(DELETE_NON_BOARD($iConRLM, $_POST['payload'])));
            break;
        default:
            # code...
            break;
    }

    mysqli_close($iConRLM);

    function SEARCH_PARTS($iConRLM, $data){
        $res_arr = [];
        $parts_arr = [];
        $hw_set_id = [];
        $hw_raw_arr = [];
        $hw_arr = [];
        $temp_arr = [];
        $hw_type_arr = [];
        $temp_arr = [];
        
        if ($data != null || $data != '') {

            if (gettype($data) == "string") {
                $get_parts = 'SELECT DISTINCT MFG_PART_NUM, HW_SET_ID, SITE_NUM, RES_AREA FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL WHERE MFG_PART_NUM LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%" ORDER BY MFG_PART_NUM ASC LIMIT 10';
            }
            elseif (gettype($data) == "array") {
                $trim_data = array();
                foreach($data as $dt) {
                    $trim_data[] = mysqli_real_escape_string($iConRLM,$dt);
                }
                $get_parts = 'SELECT DISTINCT MFG_PART_NUM, HW_SET_ID, SITE_NUM, RES_AREA FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL WHERE MFG_PART_NUM IN ("'.implode('","', $trim_data).'") ORDER BY MFG_PART_NUM ASC';
            }
            $res_parts = ExecuteIQuery($get_parts,Open_ILink("MXHTAFOT01L"));

            while($row = mysqli_fetch_assoc($res_parts)){
                array_push($parts_arr, $row);
                array_push($hw_set_id, mysqli_real_escape_string($iConRLM,$row['HW_SET_ID']));
            }
        
            if (count($parts_arr) == 0) {
                return [];
            }

            $hw_set_id = array_unique($hw_set_id);

            $hw_type_arr = GET_HW_TYPES(1);

            $get_raw_hw = 'SELECT DISTINCT HW_SET_ID, HW_TYPE, REPLACE(HW_NM,"_ZEROCAP","") HW_NM FROM BODS_JDA_ADI_EXPORT.hardware_set 
                            WHERE HW_SET_ID IN ("'.implode('","', $hw_set_id).'") AND HW_TYPE IN ("'.implode('","', $hw_type_arr).'") AND HW_NM NOT LIKE "%FAKE"
                            UNION
                            SELECT DISTINCT HW_SET_ID, HW_TYPE, REPLACE(HW_NM,"_ZEROCAP","") HW_NM FROM BODS_JDA_ADI_EXPORT.hardware_alternates 
                            WHERE HW_SET_ID IN ("'.implode('","', $hw_set_id).'") AND HW_TYPE IN ("'.implode('","', $hw_type_arr).'") AND HW_NM NOT LIKE "%FAKE" ORDER BY HW_TYPE, HW_NM';

            $res_raw_hw = ExecuteIQuery($get_raw_hw,Open_ILink("MXHTAFOT01L"));
            
            while($row = mysqli_fetch_assoc($res_raw_hw)){
                array_push($hw_raw_arr, $row);
            }

            $hw_names = array_map(function($item) {
                return $item['HW_NM'];
            }, $hw_raw_arr);
            
            $get_hw = 'SELECT
                PRR.SITE_NUM,
                PRR.RES_AREA,
                REF.HW_NM,
                HMS.HW_TYPE_HMS AS HW_TYPE,
                MIN(HMS.AVAIL_QTY) AVAIL_QTY,
                MIN(HMS.TOTAL_QTY) TOTAL_QTY
                FROM BRAIN.HW_NONBOARD_HMS HMS
                INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
                INNER JOIN BRAIN.HW_NONBOARD_SETUP_ACTIVE SACTIVE ON PRR.REF_SETUP_ID = SACTIVE.REF_SETUP_ID
                INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
                WHERE REF.HW_NM IN ("'.implode('","', $hw_names) .'")
                GROUP BY SITE_NUM, RES_AREA, HW_NM, HW_TYPE LIMIT 100';

            $res_hw = ExecuteIQuery($get_hw,Open_ILink("MXHTAFOT01L"));
            
            while($row = mysqli_fetch_assoc($res_hw)){
                array_push($hw_arr, $row);
            }

            foreach ($hw_raw_arr as $key1 => $hwr) {
                $hw_type = $hwr['HW_TYPE'];
                $hw_name = $hwr['HW_NM'];

                foreach ($hw_arr as $key2 => $hw) {

                    $site_num = $hw['SITE_NUM'];
                    $res_area = $hw['RES_AREA'];
                    $capacity = $hw['AVAIL_QTY'];

                    if ($hw_type == $hw['HW_TYPE'] && $hw_name == $hw['HW_NM']) {
                        $hw_raw_arr[$key1]['SITE_NUM'] = $site_num;
                        $hw_raw_arr[$key1]['RES_AREA'] = $res_area;
                        $hw_raw_arr[$key1]['REQUIRED_QTY'] = $capacity;
                    }
                }
            }

            foreach ($parts_arr as $pk => $p) {
                $part = $p['MFG_PART_NUM'];
                $p_hw_set_id = $p['HW_SET_ID'];
                $p_site_num = $p['SITE_NUM'];
                $p_res_area = $p['RES_AREA'];
                foreach ($hw_raw_arr as $hwk => $hw) {
                    if ($p_hw_set_id == $hw['HW_SET_ID']) {
                        if (array_key_exists('REQUIRED_QTY', $hw)) {
                            $res_arr[$p['MFG_PART_NUM']][] = [
                                'HW_NM' => $hw['HW_NM'],
                                'HW_TYPE' => $hw['HW_TYPE'],
                                'REQUIRED_QTY' => (double)$hw['REQUIRED_QTY'],
                                'SITE_NUM' => $p_site_num,
                                'RES_AREA' => $p_res_area
                            ];
                        }
                    }
                }
            }
            
            return $res_arr;
        }
    }

    function GET_HW_TYPES($type){
        $iConRLM = Open_ILink("MXHTAFOT01L");
        $res_arr = [];

        if ($type == 1 || $type == 2) {
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
        }
        else{
            $query = 'SELECT DISTINCT
                    CA_VALUE HW_TYPE
                    FROM ADI_ESLDATA.CUSTOM_ACTION CA
                    INNER JOIN ADI_ESLDATA.CUSTOM_ACTION_IDEN CAI ON CA.CA_ID = CAI.CA_ID
                    WHERE CA.CA_NAME != "HWTYPE_PLANNABLE"
                    HAVING HW_TYPE NOT IN ("BURNIN_BOARD") and hw_type not in (
	                    SELECT DISTINCT
	                    CA_VALUE HW_TYPE
	                    FROM ADI_ESLDATA.CUSTOM_ACTION CA
	                    INNER JOIN ADI_ESLDATA.CUSTOM_ACTION_IDEN CAI ON CA.CA_ID = CAI.CA_ID
	                    WHERE CA.CA_NAME = "HWTYPE_PLANNABLE"
	                    HAVING HW_TYPE NOT IN ("BURNIN_BOARD")
	                    ORDER BY HW_TYPE
                    )
                    ORDER BY HW_TYPE';

            $result = ExecuteIQuery($query,$iConRLM);
            while($row = mysqli_fetch_assoc($result)){
                array_push($res_arr, $row['HW_TYPE']);
            }
        }

        return $res_arr;
    }

    function GET_HW_FROM_TYPE_3($string_val = null){
        $iConRLM = Open_ILink("MXHDAFOT01L");
        $res_arr = [];

        if (gettype($string_val) == "string") {
            $where = ($string_val != null) ? 'WHERE HW_NAME LIKE "'.mysqli_real_escape_string($iConRLM,$string_val).'%"' : '';
        }
        elseif (gettype($string_val) == "array") {
            $trim_data = array();
            foreach($string_val as $dt) {
                $trim_data[] = mysqli_real_escape_string($iConRLM,$dt);
            }
            $where = 'WHERE HW_NAME IN ("'.implode('","', $trim_data).'")';
        }
        
        $query = 'SELECT * FROM TEST.ADI_HW_OVERRIDE_TYPE_3 '.$where.'';
        $result = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($result)){
            array_push($res_arr, [
                "HW_TYPE"   => $row['HW_TYPE_HMS'],
                "HW_NM"     => $row['HW_NAME'],
                "SITE_NUM"  => $row['SITE_NUM'],
                "RES_AREA"  => $row['RES_AREA'],
                "CAPACITY"  => $row['CAPACITY'],
            ]);
        }
        
        return $res_arr;
    }

    function GET_GENPOOL_CAPACITY_V2($iConRLM, $data){
        $res_arr = [];
        $query = 'SELECT * FROM BODS_JDA_ADI.RESOURCE_CAPACITY WHERE 
                    SITE_NUM IN ("'.implode('","', $data['SITE_NUM']).'") AND 
                    RES_AREA IN ("'.implode('","', $data['RES_AREA']).'") AND 
                    RES_TYPE IN ("'.implode('","', $data['HW_TYPE']).'") AND 
                    RES_NM IN ("'.implode('","', $data['HW_NM']).'")';

        $result = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($result)){
            array_push($res_arr, $row);
        }
        return $res_arr;
    }

    function SEARCH_HW($iConRLM, $data){
        $hw_arr = [];
        $cap_arr = [];
        $res_arr = [];
        $temp_arr = [];
        $col_arr = [];
        $hw_type_arr = [];
        $hw_type3_arr = [];
        
        if (gettype($data) == "string") {
            $get_hw_query = 'LIKE "'.mysqli_real_escape_string($iConRLM,$data).'%"';
        }
        elseif (gettype($data) == "array") {
            $trim_data = array();
            foreach($data as $dt) {
                $trim_data[] = mysqli_real_escape_string($iConRLM,$dt);
            }
            $get_hw_query = 'IN ("'.implode('","', $trim_data).'")';
        }

        if ($data != null || $data != ''){

            $hw_type_arr = GET_HW_TYPES(2);

            $get_hw = 'SELECT
                        PRR.SITE_NUM,
                        PRR.RES_AREA,
                        REF.HW_NM,
                        HMS.HW_TYPE_HMS AS HW_TYPE,
                        MIN(HMS.AVAIL_QTY) AVAIL_QTY,
                        MIN(HMS.TOTAL_QTY) TOTAL_QTY
                        FROM BRAIN.HW_NONBOARD_HMS HMS
                        INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
                        INNER JOIN BRAIN.HW_NONBOARD_SETUP_ACTIVE SACTIVE ON PRR.REF_SETUP_ID = SACTIVE.REF_SETUP_ID
                        INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
                        WHERE REF.HW_NM '.$get_hw_query.' AND
                        HMS.HW_TYPE_HMS IN ("'.implode('","', $hw_type_arr).'")
                        GROUP BY SITE_NUM, RES_AREA, HW_NM, HW_TYPE LIMIT 100';
            
            $res_hw = ExecuteIQuery($get_hw, Open_ILink("MXHTAFOT01L"));
            while($row = mysqli_fetch_assoc($res_hw)){

                $res_arr[$row['HW_TYPE']][] = [
                    'HW_NM' => $row['HW_NM'],
                    'HW_TYPE' => $row['HW_TYPE'],
                    'REQUIRED_QTY' => (double)$row['AVAIL_QTY'],
                    'SITE_NUM' => $row['SITE_NUM'],
                    'RES_AREA' => $row['RES_AREA']
                ];

                // array_push($hw_arr, $row);
                // $temp_arr['SITE_NUM'][] = $row['SITE_NUM'];
                // $temp_arr['RES_AREA'][] = $row['RES_AREA'];
                // $temp_arr['HW_TYPE'][] = $row['HW_TYPE'];
                // $temp_arr['HW_NM'][] = $row['HW_NM'];
            }

            $hw_type3_arr = GET_HW_FROM_TYPE_3($data);

            foreach ($hw_type3_arr as $key => $hwt3) {
                $res_arr[$hwt3['HW_TYPE']][] = [
                    'HW_NM' => $hwt3['HW_NM'],
                    'HW_TYPE' => $hwt3['HW_TYPE'],
                    'REQUIRED_QTY' => (double)$hwt3['CAPACITY'],
                    'SITE_NUM' => $hwt3['SITE_NUM'],
                    'RES_AREA' => $hwt3['RES_AREA']
                ];
            }

            return $res_arr;

            // if (count($hw_arr) == 0) {
            //     return [];
            // }

            // $col_arr = [
            //     'HW_TYPE'   => array_unique($temp_arr['HW_TYPE']),
            //     'HW_NM'     => array_unique($temp_arr['HW_NM']),
            //     'SITE_NUM'  => array_unique($temp_arr['SITE_NUM']),
            //     'RES_AREA'  => array_unique($temp_arr['RES_AREA'])
            // ];

            // $gp_cap_arr = GET_GENPOOL_CAPACITY_V2($iConRLM, $col_arr);

            // $hw_type3_arr = GET_HW_FROM_TYPE_3();

            // //GET PLANNABLE NON-BOARDS - TYPE 3
            // if (count($hw_type3_arr) > 0) {
            //     foreach ($hw_type3_arr as $key => $hwt3) {
            //         array_push($hw_arr, [
            //             'SITE_NUM' => $hwt3['SITE_NUM'],
            //             'RES_AREA' => $hwt3['RES_AREA'],
            //             'HW_TYPE'  => $hwt3['HW_TYPE'],
            //             'HW_NM'    => $hwt3['HW_NM'],
            //         ]);

            //         array_push($gp_cap_arr, [
            //             'RES_NM'        => $hwt3['HW_NM'],
            //             'RES_TYPE'      => $hwt3['HW_TYPE'],
            //             'SITE_NUM'      => $hwt3['SITE_NUM'],
            //             'RES_AREA'      => $hwt3['RES_AREA'],
            //             'EFF_RES_CNT'   => $hwt3['CAPACITY']
            //         ]);
            //     }
            // }

            // foreach ($hw_arr as $key => $hw) {
                
            //     $hw_nm = $hw['HW_NM'];
            //     $hw_type = $hw['HW_TYPE'];
            //     $site_num = $hw['SITE_NUM'];
            //     $res_area = $hw['RES_AREA'];

            //     $res_map = array_map(function($item) use ($hw_nm, $hw_type, $site_num, $res_area) {
            //     return (

            //             $item['RES_NM'] === $hw_nm &&
            //             $item['RES_TYPE'] === $hw_type &&
            //             $item['SITE_NUM'] === $site_num &&
            //             $item['RES_AREA'] === $res_area

            //         ) ? $item['EFF_RES_CNT'] : null;
            //     }, $gp_cap_arr);
                    
            //     $res_map = array_filter($res_map, function($value) {
            //         return $value !== null;
            //     });

            //     $value = reset($res_map);

            //     $res_arr[$hw['HW_TYPE']][] = [
            //         'HW_NM' => $hw_nm,
            //         'HW_TYPE' => $hw_type,
            //         'REQUIRED_QTY' => (double)$value,
            //         'SITE_NUM' => $site_num,
            //         'RES_AREA' => $res_area
            //     ];
            // }
        }

        // return $res_arr;
    }

    function SEARCH_GP_HW($iConRLM, $data){

        $result = [];
        $res_arr = [];
        $temp_arr = [];

        // $get_hw = 'SELECT DISTINCT HW_TYPE, REPLACE(HW_NM,"_ZEROCAP","") HW_NM FROM BODS_JDA_ADI_EXPORT.hardware_set 
        //            WHERE HW_NM IN ("'.implode('","', $data).'") AND HW_NM NOT LIKE "%FAKE" GROUP BY HW_NM 
        //                   UNION
        //            SELECT DISTINCT HW_TYPE, REPLACE(HW_NM,"_ZEROCAP","") HW_NM FROM BODS_JDA_ADI_EXPORT.hardware_alternates 
        //            WHERE HW_NM IN ("'.implode('","', $data).'") AND HW_NM NOT LIKE "%FAKE" GROUP BY HW_NM ORDER BY HW_TYPE, HW_NM';

        $hw_type_arr = GET_HW_TYPES(2);
        $get_hw = 'SELECT
                    PRR.SITE_NUM,
                    PRR.RES_AREA,
                    REF.HW_NM,
                    HMS.HW_TYPE_HMS AS HW_TYPE,
                    MIN(HMS.AVAIL_QTY) REQUIRED_QTY,
                    MIN(HMS.TOTAL_QTY) TOTAL_QTY
                    FROM BRAIN.HW_NONBOARD_HMS HMS
                    INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
                    INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
                    WHERE REF.HW_NM IN ("'.implode('","', $data).'") AND
                    HMS.HW_TYPE_HMS IN ("'.implode('","', $hw_type_arr).'")
                    GROUP BY SITE_NUM, RES_AREA, HW_NM, HW_TYPE LIMIT 100';

        $res_hw = ExecuteIQuery($get_hw, Open_ILink("MXHTAFOT01L"));

        while($row = mysqli_fetch_assoc($res_hw)){
            array_push($result, $row);
            // $temp_arr['HW_TYPE'][] = $row['HW_TYPE'];
            // $temp_arr['HW_NM'][] = $row['HW_NM'];
        }

        return $result;

        // $col_arr = [
        //     'HW_TYPE'   => array_unique($temp_arr['HW_TYPE']),
        //     'HW_NM'     => array_unique($temp_arr['HW_NM'])
        // ];

        // $query = 'SELECT SITE_NUM, RES_AREA, RES_TYPE, RES_NM, MAX(EFF_RES_CNT) AS EFF_RES_CNT FROM BODS_JDA_ADI.RESOURCE_CAPACITY WHERE
        //             RES_TYPE IN ("'.implode('","', $temp_arr['HW_TYPE']).'") AND 
        //             RES_NM IN ("'.implode('","', $temp_arr['HW_NM']).'") GROUP BY RES_NM';

        // $res_query = ExecuteIQuery($query,$iConRLM);
        // while($row = mysqli_fetch_assoc($res_query)){
        //     array_push($res_arr, $row);
        // }
        
        // foreach ($result as $key => $hw) {
        //     $hw_nm = $hw['HW_NM'];
        //     $hw_type = $hw['HW_TYPE'];
        //     $res_map = array_map(function($item) use ($hw_nm, $hw_type) {
        //         return (
        //                 $item['RES_NM'] === $hw_nm &&
        //                 $item['RES_TYPE'] === $hw_type
        //             ) ? $item['EFF_RES_CNT'] : null;
        //         }, $res_arr);
                    
        //     $res_map = array_filter($res_map, function($value) {
        //         return $value !== null;
        //     });

        //     $value = reset($res_map);

        //     $result[$key]['REQUIRED_QTY'] = $value;
        // }

        // return $result;
    }

    function SEARCH_NON_BOARD($iConRLM, $data, $limit){
        $iConRLM = Open_ILink("MXHTAFOT01L"); //TEMPORARY
        $result = [];
        $hw_type_arr = [];

        $hw_type_arr = GET_HW_TYPES(2);
        
        $get_nonboard = 'SELECT
                        PRR.SITE_NUM,
                        PRR.RES_AREA,
                        REF.HW_NM,
                        HMS.HW_TYPE_HMS,
                        MIN(HMS.AVAIL_QTY) AVAIL_QTY,
                        MIN(HMS.TOTAL_QTY) TOTAL_QTY,
                        GROUP_CONCAT(DISTINCT REF.HW_TYPE_SUS ORDER BY HW_TYPE_SUS) HW_TYPE_SUS
                        FROM BRAIN.HW_NONBOARD_HMS HMS
                        INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
                        INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
                        WHERE HMS.HW_NM LIKE "'.$data.'%" AND HMS.HW_TYPE_HMS NOT IN ("'.implode('","', $hw_type_arr).'")
                        GROUP BY SITE_NUM, RES_AREA, HW_NB_HMS_ID LIMIT '.$limit.'';

        $get_nonboard_res = ExecuteIQuery($get_nonboard,$iConRLM);
        while($row = mysqli_fetch_assoc($get_nonboard_res)){
            array_push($result, $row);
        }
        return $result;
    }

    function GET_DUMMY_HW($iConRLM){
        $dummy_arr = [];
        $get_dummy = 'SELECT * FROM TEST.ADI_HW_OVERRIDE_TYPE_1 ORDER BY CREATED_AT DESC';
        $res_dummy = ExecuteIQuery($get_dummy,$iConRLM);
        while($row = mysqli_fetch_assoc($res_dummy)){
            array_push($dummy_arr, $row);
        }
        return $dummy_arr;
    }

    function GET_HW_CAPACITY($iConRLM){
        $capacity_arr = [];
        $get_capacity = 'SELECT * FROM TEST.ADI_HW_OVERRIDE_TYPE_2 ORDER BY CREATED_AT DESC';
        $res_capacity = ExecuteIQuery($get_capacity,$iConRLM);
        while($row = mysqli_fetch_assoc($res_capacity)){
            array_push($capacity_arr, $row);
        }
        return $capacity_arr;
    }

    function GET_NON_BOARD($iConRLM){
        $nonboard_arr = [];
        $get_nonboard = 'SELECT * FROM TEST.ADI_HW_OVERRIDE_TYPE_3 ORDER BY CREATED_AT DESC';
        $res_nonboard = ExecuteIQuery($get_nonboard,$iConRLM);
        while($row = mysqli_fetch_assoc($res_nonboard)){
            array_push($nonboard_arr, $row);
        }
        return $nonboard_arr;
    }

    function ADD_DUMMY_HW($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $values = [];
        $values_edit = ['HW_NM' => [],'EFF_START' => [],'EFF_END' => [],'CAPACITY' => [],'UPDATED_AT' => []];
        $values_edit_id = [];
        $values_delete_id = [];
        $result = '';
        foreach ($data as $key => $dt) {
            if ($dt[6] != 'null') {
                if (!isset($dt[10])) {
                    $query_edit = 'UPDATE TEST.ADI_HW_OVERRIDE_TYPE_1 SET HW_NM = "'.strtoupper($dt[1]).'", EFF_START = "'.$dt[2].'", EFF_END = "'.$dt[3].'", CAPACITY = "'.$dt[4].'", UPDATED_AT = "'.$curdate.'" WHERE ID = '.$dt[6].'';
                    $result = ExecuteIQuery($query_edit,$iConRLM);
                }
                else{
                    if ($dt[10] == 'UPDATE') {
                        array_push($values_edit_id, $dt[6]);
                        array_push($values_edit['HW_NM'],       'WHEN '.$dt[6].' THEN "'.strtoupper($dt[1]).'"');
                        array_push($values_edit['EFF_START'],   'WHEN '.$dt[6].' THEN "'.$dt[2].'"');
                        array_push($values_edit['EFF_END'],     'WHEN '.$dt[6].' THEN "'.$dt[3].'"');
                        array_push($values_edit['CAPACITY'],    'WHEN '.$dt[6].' THEN "'.$dt[4].'"');
                        array_push($values_edit['UPDATED_AT'],  'WHEN '.$dt[6].' THEN "'.$curdate.'"');
                    }
                    else{
                        array_push($values_delete_id, $dt[6]);
                    }
                }
            }
            else{
                array_push($values, '("", "'.$dt[0].'", "'.strtoupper($dt[1]).'", "'.$dt[2].'", "'.$dt[3].'", "'.$dt[7].'", "'.$dt[8].'", "'.$dt[4].'", "'.$dt[5].'", "'.$curdate.'", "'.$user['emp_name'].'", "", "'.$dt[9].'")');
            }
        }

        if (count($values_edit_id) > 0) {
            $batch_update = 'UPDATE TEST.ADI_HW_OVERRIDE_TYPE_1
                            SET 
                                HW_NM = CASE ID
                                    '.implode(" ", $values_edit['HW_NM']).'
                                END,
                                EFF_START = CASE ID
                                    '.implode(" ", $values_edit['EFF_START']).'
                                END,
                                EFF_END = CASE ID
                                    '.implode(" ", $values_edit['EFF_END']).'
                                END,
                                CAPACITY = CASE ID
                                    '.implode(" ", $values_edit['CAPACITY']).'
                                END,
                                UPDATED_AT = CASE ID
                                    '.implode(" ", $values_edit['UPDATED_AT']).'
                                END
                            WHERE ID IN ("'.implode('","', $values_edit_id).'");';
            
            $result = ExecuteIQuery($batch_update,$iConRLM);
        }
        
        //FINISH VALIDATION, IF PROCESS TYPE IS DELETE OR UPDATE. ADJUST EMPTY CELL VALIDATION, MAKE IT VALID FOR SOME.
        // AND ADJUST GENPOOL VALIDATION (OVERLAPPING WEEKS). IF UPDATE ADJUST SOME, IF DELETE DON'T RUN THROUGH IT.
        // if (count($values_delete_id) > 0) {
        //     $batch_delete = 'DELETE FROM  TEST.ADI_HW_OVERRIDE_TYPE_1 WHERE ID IN ("'.implode('","', $values_delete_id).'")';
        //     $result = ExecuteIQuery($batch_delete,$iConRLM);
        // }

        if (count($values) > 0) {
            $query = 'INSERT INTO TEST.ADI_HW_OVERRIDE_TYPE_1 VALUES '.implode(",", $values).'';
            $result = ExecuteIQuery($query,$iConRLM);
        }
        return $result;
    }

    function ADD_HW_CAPACITY($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $values = [];
        $values_edit = ['HW_NM' => [],'EFF_START' => [],'EFF_END' => [],'OVERRIDE_CAP' => [],'UPDATED_AT' => []];
        $values_edit_id = [];
        $values_delete_id = [];
        $result = '';
        $change_log = [];

        foreach ($data as $key => $dt) {
            if ($dt[8] != 'null' && isset($dt[9])) {
                // $query_edit = 'UPDATE TEST.ADI_HW_OVERRIDE_TYPE_2 SET HW_NM = "'.strtoupper($dt[0]).'", EFF_START = "'.$dt[1].'", EFF_END = "'.$dt[2].'", OVERRIDE_CAP = "'.$dt[3].'", UPDATED_AT = "'.$curdate.'" WHERE ID = '.$dt[6].'';
                // $result = ExecuteIQuery($query_edit,$iConRLM);
                if ($dt[9] == 'UPDATE') {
                    array_push($values_edit_id, $dt[8]);
                    array_push($values_edit['EFF_START'],    'WHEN '.$dt[8].' THEN "'.$dt[1].'"');
                    array_push($values_edit['EFF_END'],      'WHEN '.$dt[8].' THEN "'.$dt[2].'"');
                    array_push($values_edit['OVERRIDE_CAP'], 'WHEN '.$dt[8].' THEN "'.$dt[3].'"');
                    array_push($values_edit['UPDATED_AT'],   'WHEN '.$dt[8].' THEN "'.$curdate.'"');
                }
                else{
                    // array_push($values_delete_id, $dt[6]);
                }
            }
            else{
                $query = 'INSERT INTO TEST.ADI_HW_OVERRIDE_TYPE_2 VALUES ("", "'.strtoupper($dt[0]).'", "'.$dt[1].'", "'.$dt[2].'", "'.$dt[3].'", "'.$dt[4].'", "'.$dt[5].'", "'.$dt[7].'", "'.$dt[8].'", "'.$curdate.'", "'.$user['emp_name'].'", "")';
                $result = ExecuteIQuery($query,$iConRLM);
                
                $get_id = 'SELECT LAST_INSERT_ID()';
                $get_id_res = ExecuteIQuery($get_id, $iConRLM);

                while($row = mysqli_fetch_assoc($get_id_res)){
                    $dt[] = $row['LAST_INSERT_ID()'];
                    array_push($change_log, $dt);
                }

                // array_push($values, '("", "'.strtoupper($dt[0]).'", "'.$dt[1].'", "'.$dt[2].'", "'.$dt[3].'", "'.$dt[4].'", "'.$dt[5].'", "'.$dt[7].'", "'.$dt[8].'", "'.$curdate.'", "'.$user['emp_name'].'", "")');
            }
        }

        if (count($values_edit_id) > 0) {
            $batch_update = 'UPDATE TEST.ADI_HW_OVERRIDE_TYPE_2
                            SET 
                                EFF_START = CASE ID
                                    '.implode(" ", $values_edit['EFF_START']).'
                                END,
                                EFF_END = CASE ID
                                    '.implode(" ", $values_edit['EFF_END']).'
                                END,
                                OVERRIDE_CAP = CASE ID
                                    '.implode(" ", $values_edit['OVERRIDE_CAP']).'
                                END,
                                UPDATED_AT = CASE ID
                                    '.implode(" ", $values_edit['UPDATED_AT']).'
                                END
                            WHERE ID IN ("'.implode('","', $values_edit_id).'");';
            
            $result = ExecuteIQuery($batch_update,$iConRLM);
        }
        
        
        // if (count($values) > 0) {
        //     $query = 'INSERT INTO TEST.ADI_HW_OVERRIDE_TYPE_2 VALUES '.implode(",", $values).'';
        //     $result = ExecuteIQuery($query,$iConRLM);
            
        //     $get_id = 'SELECT LAST_INSERT_ID()';
        //     $get_id_res = ExecuteIQuery($get_id, $iConRLM);
        // }
        
        return [
            "STATUS" => $result,
            "CHANGE_LOG_DATA" => $change_log
        ];
    }

    function EDIT_DUMMY_HW($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $query = 'UPDATE TEST.ADI_HW_OVERRIDE_TYPE_1 SET HW_NM = "'.strtoupper($data[3]).'", EFF_START = "'.$data[4].'", EFF_END = "'.$data[5].'", CAPACITY = "'.$data[6].'", UPDATED_AT = "'.$curdate.'" WHERE ID = '.$data[0].'';
        $result = ExecuteIQuery($query,$iConRLM);
        return $result;
    }

    function EDIT_HW_CAPACITY($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $query = 'UPDATE TEST.ADI_HW_OVERRIDE_TYPE_2 SET EFF_START = "'.$data[3].'", EFF_END = "'.$data[4].'", OVERRIDE_CAP = "'.$data[2].'", UPDATED_AT = "'.$curdate.'" WHERE ID = '.$data[0].'';
        $result = ExecuteIQuery($query,$iConRLM);
        return $result;
    }

    function DELETE_DUMMY_HW($iConRLM, $data, $is_csv){
        $ids = [];
        $curdate = date('Y-m-d H:i:s');

        if ($is_csv) {
            $ids = $data;
        }
        else{
            foreach ($data as $key => $dt) {
                array_push($ids, $dt['ID']);
            }
        }

        $query = 'DELETE FROM  TEST.ADI_HW_OVERRIDE_TYPE_1 WHERE ID IN ("'.implode('","', $ids).'")';
        $result = ExecuteIQuery($query,$iConRLM);
        return $result;
    }

    function DELETE_HW_CAPACITY($iConRLM, $data, $is_csv){
        $ids = [];
        $curdate = date('Y-m-d H:i:s');
        
        if ($is_csv) {
            $ids = $data;
        }
        else{
            foreach ($data as $key => $dt) {
                array_push($ids, $dt['ID']);
            }
        }

        $query = 'DELETE FROM  TEST.ADI_HW_OVERRIDE_TYPE_2 WHERE ID IN ("'.implode('","', $ids).'")';
        $result = ExecuteIQuery($query,$iConRLM);
        return $result;
    }

    function ADD_NON_BOARD($iConRLM, $data, $user){
        $curdate = date('Y-m-d H:i:s');
        $values = [];
        $result = '';
        foreach ($data as $key => $dt) {
            array_push($values, '("", "'.$dt[0].'", "'.$dt[1].'", "'.$dt[2].'", "'.$dt[3].'", "'.$dt[4].'", "'.(double)$dt[5].'", "'.$curdate.'", "'.$user['emp_name'].'")');
        }
        if (count($values) > 0) {
            $query = 'INSERT INTO TEST.ADI_HW_OVERRIDE_TYPE_3 VALUES '.implode(",", $values).'';
            $result = ExecuteIQuery($query,$iConRLM);
        }
        return $result;
    }

    function DELETE_NON_BOARD($iConRLM, $data){
        $ids = [];
        $curdate = date('Y-m-d H:i:s');
        foreach ($data as $key => $dt) {
            array_push($ids, $dt['ID']);
        }
        $query = 'DELETE FROM  TEST.ADI_HW_OVERRIDE_TYPE_3 WHERE ID IN ("'.implode('","', $ids).'")';
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

    function GET_OPEN_WEEK($iConRLM, $year, $week, $fyww_multi = null){
        $week_arr = [];
        $query = 'SELECT DISTINCT MFG_YEAR * 100 + WEEK_NUMBER FYWW FROM SHARED.DMCLS_MEMORY WHERE MFG_DATE >= (select MFG_DATE from SHARED.DMCLS_MEMORY where MFG_YEAR = '.$year.' and WEEK_NUMBER = '.$week.' order by MFG_DATE asc limit 1) ORDER BY FYWW LIMIT 26, 1';
        $result = ExecuteIQuery($query,$iConRLM);
        while($row = mysqli_fetch_assoc($result)){
            array_push($week_arr, $row['FYWW']);
        }
        return $week_arr;
    }
?>

