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
        print_r(json_encode(CHECK_LOGIN_INFO($iConRLM, $_REQUEST['EMPLOYEE_ID'],$bolDebug)));
    }

    mysqli_close($iConRLM);

    function CHECK_LOGIN_INFO($iConRLM, $emp_id,$bolDebug=false){
        // $strSQL = "call personnel.CHECK_V_WORKDAY_MEMORY();";
        // ExecuteIQuery($strSQL,Open_ILink("MXHDAFOT01L"));


        $account_details = array(
            "EMP_ID"=>$emp_id
        );

        $strSQL = "
            SELECT
            IFNULL(PREFERRED_NAME,FIRST_NAME) FIRST_NAME,
            LAST_NAME,
            EMAIL
            FROM PERSONNEL.V_WORKDAY_MEMORY
            WHERE EMPLOYEE_NO = '".mysqli_real_escape_string($iConRLM,$emp_id)."';";
        if($bolDebug === true) {
            echo $strSQL."<BR>";
        }
        $RSMain = ExecuteIQuery($strSQL,$iConRLM);
        while ($LineMain = mysqli_fetch_assoc($RSMain)) {
            $account_details["EMP_EMAIL"] = $LineMain["EMAIL"];
            
            $aryTemp = array(
                $LineMain["FIRST_NAME"],
                $LineMain["LAST_NAME"]
            );

            $account_details["FIRST_NAME"] = $LineMain["FIRST_NAME"];
            $account_details["LAST_NAME"] = $LineMain["LAST_NAME"];
            $account_details["EMP_NAME"] = implode(" ",$aryTemp);
        }
        

        #2025-10-15 RM dont duplicate data if you dont need to
        $get_info = "SELECT UAR.ROLE, GROUP_CONCAT(UAR.USER_GROUP) AS USER_GROUP FROM BRAIN.USER_ACCOUNT UA INNER JOIN BRAIN.USER_ACCOUNT_ROLES UAR ON UA.emp_id = uar.emp_id WHERE UA.emp_id = '".mysqli_real_escape_string($iConRLM,$emp_id)."'";
        if($bolDebug === true) {
            echo $get_info."<BR>";
        }
        $get_info_res = ExecuteIQuery($get_info,$iConRLM);
        while($row = mysqli_fetch_assoc($get_info_res)){
            foreach($row as $key => $value) {
                $account_details[$key] = $value;
            }
        }

        if(isset($account_details["ROLE"]) && isset($account_details["USER_GROUP"])) {
            return $account_details;   
        }else{        
            $details = [];

            #2025-10-15 RM this isnt quite right. you are assuming their direct manager has a inheritable role. what if they report to a person who reports to a person who has inheritable role?
            #dont have time to make this a recursive check, so ill leave it untouched for now
            $get_details = 'SELECT VWM.EMPLOYEE_NO, VWM.PREFERRED_NAME, VWM.LAST_NAME, VWM.EMAIL, UAR.EMP_ID, GROUP_CONCAT(UAR.USER_GROUP) AS USER_GROUP 
                            FROM PERSONNEL.V_WORKDAY_MEMORY VWM RIGHT JOIN BRAIN.USER_ACCOUNT_ROLES UAR ON VWM.SUPERVISOR_NO = UAR.EMP_ID WHERE VWM.EMPLOYEE_NO = '.$emp_id.' AND UAR.ROLE = "inheritable"';
            if($bolDebug === true) {
                echo $get_details."<BR>";
            }
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
                if($bolDebug === true) {
                    echo $insert_user."<BR>";
                }
                $insert_user_res = ExecuteIQuery($insert_user,$iConRLM);
                
                foreach ($user_group as $key => $group) {
                    $insert_role = 'INSERT INTO BRAIN.USER_ACCOUNT_ROLES VALUES ("", '.$emp_id.', "assigned", "'.$group.'")';
                    if($bolDebug === true) {
                        echo $insert_role."<BR>";
                    }
                    $insert_role_res = ExecuteIQuery($insert_role,$iConRLM);
                }

                if ($insert_user_res) {
                    $details = array(
                        'EMP_ID'         => $emp_id,
                        'EMP_NAME'       => $fullname,
                        'EMP_EMAIL'      => $details['EMAIL'],
                        'ROLE'           => "assigned",
                        'USER_GROUP'     => $details['USER_GROUP'],
                        "FIRST_NAME"    => $details["PREFERRED_NAME"],
                        "LAST_NAME"     => $details["LAST_NAME"]
                    );
                }
            }

            return $details;
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
?>