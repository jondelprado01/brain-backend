<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("ADI_FUNCTIONS.PHP");
    #2025-10-02 RM 
    include_once("./WS_FUNCTIONS3.PHP");

    ini_set("error_reporting",E_ALL);
    ini_set("display_errors",1);

    $aryRequest = $_REQUEST;
    $requestMedthod = $_SERVER['REQUEST_METHOD'];
    date_default_timezone_set('Asia/Manila');
    #2025-10-02 RM why is this pointing to DEV?
    #$iConRLM = Open_ILink("localhost");
    $iConRLM = Open_ILink("MXHTAFOT01L");

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

    $aryCap = array();
    $aryRT = array("TESTER","HANDLER");
    
    $strURL = "http://afotcosj004.maxim-ic.com/API/MAPPER_EQUIP_TEST_ONLY_JON.PHP";
    $strInputType = "JSON";
    $strOutputType = "SITENUM_RESAREA_RESNM";
    foreach($aryRT as $strRT) {
        $aryInput = json_encode(array(array("RESOURCE_TYPE"=>array($strRT))));

        #print_r($aryInput);
        #die();
        $varResponse = WS_DO_V2($strURL,$strInputType,$strOutputType,$aryInput);
        foreach(array_keys($varResponse) as $intCtr) {
            foreach(array_keys($varResponse[$intCtr]) as $strSN) {
                foreach(array_keys($varResponse[$intCtr][$strSN]) as $strRA) {
                    foreach($varResponse[$intCtr][$strSN][$strRA] as $strRN => $aryTemp) {
                        $aryCap[$strRT][$strSN][$strRA][$strRN] = $aryTemp["CONFIG_PCNT"];
                    }
                }
            }
        }
    }

    if ($process == "GET_LIST") {
        print_r(json_encode(GET_LIST_V2($iConRLM,$aryCap)));
    }
    elseif ($process == "ASSIGN_ATOM") {
        print_r(json_encode(ASSIGN_ATOM($iConRLM, $_POST['id'], $_POST['atom'], $_POST['res_area'], $_POST['user_details'])));
    }



    
    mysqli_close($iConRLM);


    function GET_LIST_V2($iConBRAIN,$aryCap){
        $aryMain = array();

        $strSQL = "
            SELECT
            DA_ID,
            RESOURCE_TYPE,
            SITE_NUM,
            RES_AREA,
            ENG_NAME,
            ATOM_NAME,
            CHANGED_BY,
            CHANGED_DT
            FROM BRAIN.DEFAULT_ATOM
            ORDER BY RESOURCE_TYPE, SITE_NUM, RES_AREA, ENG_NAME;";
        $RSMain = ExecuteIQuery($strSQL,$iConBRAIN);
        $aryFirst = array();
        $arySecond = array();

        while ($LineMain = mysqli_fetch_assoc($RSMain)) {
            $strRT = $LineMain["RESOURCE_TYPE"];
            $strSN = $LineMain["SITE_NUM"];
            $strRA = $LineMain["RES_AREA"];
            $strRN = $LineMain["ATOM_NAME"];

            $aryTemp = $LineMain;
            $aryTemp["ZERO_CAP"] = 0;

            #2025-10-02 RM not sure how res_area is ever null....?
            switch (true) {
                case is_null($strRA):
                    $arySecond[] = $aryTemp;
                    break;
                case isset($aryCap[$strRT][$strSN][$strRA][$strRN]):
                    $arySecond[] = $aryTemp;
                    break;
                default:
                    $aryTemp["ZERO_CAP"] = 1;
                    $aryFirst[] = $aryTemp;
            }
        }

        $aryReturn = array();
        foreach($aryFirst as $aryTemp) {
            $aryReturn[] = $aryTemp;
        }
        foreach($arySecond as $aryTemp) {
            $aryReturn[] = $aryTemp;
        }

        return $aryReturn;
    }


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