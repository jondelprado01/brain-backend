<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("ADI_FUNCTIONS.PHP");
    ini_set("error_reporting",E_ALL);
    ini_set("display_errors",1);

    $aryRequest = $_REQUEST;
    $requestMedthod = $_SERVER['REQUEST_METHOD'];
    date_default_timezone_set('Asia/Manila');
    $iConRLM = Open_ILink("MXHDAFOT01L");

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

    if ($process == "GET_USERS") {
        print_r(json_encode(GET_USERS($iConRLM)));
    }
    elseif ($process == "CHECK_LOGIN_INFO") {
        print_r(json_encode(CHECK_LOGIN_INFO($iConRLM, $_REQUEST['EMPLOYEE_ID'])));
    }

    mysqli_close($iConRLM);

    function CHECK_LOGIN_INFO($iConRLM, $emp_id){

        $account_details = [];
        $get_info = 'SELECT UA.EMP_ID, UA.EMP_NAME, UA.EMP_EMAIL, UAR.ROLE, GROUP_CONCAT(UAR.USER_GROUP) AS USER_GROUP FROM BRAIN.USER_ACCOUNT UA INNER JOIN BRAIN.USER_ACCOUNT_ROLES UAR ON UA.emp_id = uar.emp_id WHERE UA.emp_id = '.$emp_id.'';
        $get_info_res = ExecuteIQuery($get_info,$iConRLM);
        while($row = mysqli_fetch_assoc($get_info_res)){
            if (isset($row['EMP_ID'])) {
                $account_details = $row;
            }
        }

        if (count($account_details) == 0) {
            $details = [];
            $strSQL = "call personnel.CHECK_V_WORKDAY_MEMORY();";
            ExecuteIQuery($strSQL,$iConRLM);

            $get_details = 'SELECT VWM.EMPLOYEE_NO, VWM.PREFERRED_NAME, VWM.LAST_NAME, VWM.EMAIL, UAR.EMP_ID, GROUP_CONCAT(UAR.USER_GROUP) AS USER_GROUP 
                            FROM PERSONNEL.V_WORKDAY_MEMORY VWM RIGHT JOIN BRAIN.USER_ACCOUNT_ROLES UAR ON VWM.SUPERVISOR_NO = UAR.EMP_ID WHERE VWM.EMPLOYEE_NO = '.$emp_id.' AND UAR.ROLE = "inheritable"';
            $get_details_res = ExecuteIQuery($get_details,$iConRLM);
            while($row = mysqli_fetch_assoc($get_details_res)){
                if (isset($row['EMPLOYEE_NO'])) {
                    $details = $row;
                }
            }

            if (count($details) > 0) {
                $fullname = $details['PREFERRED_NAME']." ".$details['LAST_NAME'];
                $user_group = explode(",", $details['USER_GROUP']);
                $insert_user = 'INSERT INTO BRAIN.USER_ACCOUNT VALUES ("", '.$emp_id.', "'.$fullname.'", "'.$details['EMAIL'].'")';
                $insert_user_res = ExecuteIQuery($insert_user,$iConRLM);
                
                foreach ($user_group as $key => $group) {
                    $insert_role = 'INSERT INTO BRAIN.USER_ACCOUNT_ROLES VALUES ("", '.$emp_id.', "assigned", "'.$group.'")';
                    $insert_role_res = ExecuteIQuery($insert_role,$iConRLM);
                }

                if ($insert_user_res) {
                    $details = [
                        'EMP_ID'         => $emp_id,
                        'EMP_NAME'       => $fullname,
                        'EMP_EMAIL'      => $details['EMAIL'],
                        'ROLE'           => "assigned",
                        'USER_GROUP'     => $details['USER_GROUP']
                    ];
                }
            }

            return $details;
        }
        else{
            return $account_details;
        }
    }

    function GET_USERS($iConRLM){

        $users = [];
        $get_users = 'SELECT * FROM BRAIN.USER_ACCOUNT';
        $get_users_res = ExecuteIQuery($get_users,$iConRLM);
        while($row = mysqli_fetch_assoc($get_users_res)){
            array_push($users, $row);
        }

        foreach ($users as $key => $user) {
            $users[$key]['ROLES'] = [];
            $get_roles = 'SELECT * FROM BRAIN.USER_ACCOUNT_ROLES WHERE emp_id = '.$user['emp_id'].'';
            $get_roles_res = ExecuteIQuery($get_roles,$iConRLM);
            while($row = mysqli_fetch_assoc($get_roles_res)){
                array_push($users[$key]['ROLES'], $row);
            }
        }

        return $users;
    }

    function ADD_USER(){
        
    }

?>