<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("ADI_FUNCTIONS.PHP");
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

    if ($process == "GET_LIST") {
        print_r(json_encode(GET_LIST($iConRLM)));
    }
    elseif ($process == "ASSIGN_ATOM") {
        print_r(json_encode(ASSIGN_ATOM($iConRLM, $_POST['id'], $_POST['atom'], $_POST['res_area'], $_POST['user_details'])));
    }

    mysqli_close($iConRLM);

    function GET_LIST($iConRLM){
        $result = [];
        $get_list = 'SELECT * FROM BRAIN.DEFAULT_ATOM';
        $get_list_res = ExecuteIQuery($get_list,$iConRLM);
        while($row = mysqli_fetch_assoc($get_list_res)){
            array_push($result, $row);
        }
        return $result;
    }

    function ASSIGN_ATOM($iConRLM, $id, $atom, $res_area, $user_details){
        $current_date = date('Y-m-d H:i:s');
        $assign_atom = 'UPDATE BRAIN.DEFAULT_ATOM SET ATOM_NAME = "'.$atom.'", RES_AREA = "'.$res_area.'", CHANGED_BY = "'.$user_details['emp_name'].'", CHANGED_DT = "'.$current_date.'" WHERE DA_ID = '.$id.'';
        $assign_atom_res = ExecuteIQuery($assign_atom,$iConRLM);
        return $assign_atom_res;
    }

?>