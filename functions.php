<?php

Session_Start();
Include_Once("../../COMMON_FUNCTIONS.PHP");
// Include_Once("../../JE_ROUTE_BUILDER2/ROUTE_BUILDER_FUNCTIONS.PHP");

Include("../CONNECTION.php");

$_GETVARS = ($_GET > 0) ? $_GET : $_POST;
unset($_GETVARS['_']);


if (!empty($_GETVARS)) {

    if (isset($_GETVARS['action'])) {
        switch (true) {

            case $_GETVARS['action'] == 'REVIEW':
                // print_r($_GETVARS);
                 $response = UPDATE_REQUEST($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'IN-PROCESS', $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
                SEND_MAIL($_GETVARS['rc_id'], $_GETVARS['instance'], 'IN-PROCESS', $_GETVARS['mode'], 'EMAIL_INPROCESS');
               
                if ($response == 1) {
                    WRITE_TRANS_LOG($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'IN-PROCESS', $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
                }
                

                break;

            case $_GETVARS['action'] == 'APPROVE':
                
                #################################################################################	
                #NOTE : CHECK_IF_ALREADY_LINKED this is to check if the ROUTE is already linked in RLM
                #################################################################################
                $checking_result = CHECK_IF_ALREADY_LINKED($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['mode']);

                if($checking_result!='false'){
					$assigned_pdm = ((!empty($_GETVARS['new_pdm']))&&($_GETVARS['new_pdm']!='undefined'))? $_GETVARS['new_pdm'] : $_SESSION['currentlyLoggedUser']['WWID'];
					$approvestatus = ($_GETVARS['mode']=='RSM') ? 'CLOSED' : 'FOR SYSTEM VALIDATION';
					
					
					$response = UPDATE_REQUEST($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], $approvestatus, $assigned_pdm, $_GETVARS['mode']);
					SEND_MAIL($_GETVARS['rc_id'], $_GETVARS['instance'], 'CLOSED', $_GETVARS['mode'], 'EMAIL_CLOSED');
					if ($response == 1) {
						WRITE_TRANS_LOG($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], $approvestatus.' ASSIGNED TO '.$assigned_pdm, $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
					}
                }else{
                    echo $checking_result; //false
                }

                break;


            case $_GETVARS['action'] == 'REJECT':
               
                $assigned_pdm = ((!empty($_GETVARS['new_pdm']))&&($_GETVARS['new_pdm']!='undefined')) ? $_GETVARS['new_pdm'] : $_SESSION['currentlyLoggedUser']['WWID'];
                
                 
                $response = UPDATE_REQUEST($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'REJECTED', $assigned_pdm, $_GETVARS['mode'], $_GETVARS['remarks']);
                SEND_MAIL($_GETVARS['rc_id'], $_GETVARS['instance'], 'REJECTED', $_GETVARS['mode'], 'EMAIL_REJECTED');
                if ($response == 1) {
                    WRITE_TRANS_LOG($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'REJECTED ASSIGNED TO '.$assigned_pdm, $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
                }
                break;

//            case $_GETVARS['action'] == 'COMPLETED':
//                // print_r($_GETVARS);
//                $response = UPDATE_REQUEST($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'COMPLETED', $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
//                if ($response == 1) {
//                    WRITE_TRANS_LOG($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'COMPLETED', $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
//                }
//
//                break;


            case $_GETVARS['action'] == 'SESSION_DESTROY':
                DESTROY_SESSION();
                
                $redirect_page = REDIRECT_PAGE($_GETVARS['redirectpage']);
                echo $redirect_page;

                break;

            case $_GETVARS['action'] == 'CLEAR':
                unset($_SESSION['pdm']);
                break;
            
            case $_GETVARS['action'] == 'RETURN':
               
                $assigned_pdm = ((!empty($_GETVARS['new_pdm']))&&($_GETVARS['new_pdm']!='undefined')) ? $_GETVARS['new_pdm'] : $_SESSION['currentlyLoggedUser']['WWID'];
                
                 
                $response = REMOVE_REQUEST($_GETVARS['rc_id'], $_GETVARS['instance'], 'RETURNED', $assigned_pdm, $_GETVARS['mode'], $_GETVARS['remarks']);
                SEND_MAIL($_GETVARS['rc_id'], $_GETVARS['instance'], 'RETURNED', $_GETVARS['mode'], 'EMAIL_RETURNED');
                if ($response == 1) {
                    WRITE_TRANS_LOG($_GETVARS['rc_id'], $_GETVARS['instance'], $_GETVARS['requestor'], 'RETURNED ASSIGNED TO '.$assigned_pdm, $_SESSION['currentlyLoggedUser']['WWID'], $_GETVARS['mode']);
                }
               
               break;

            default:
                print_r($_GETVARS);
                break;
        }
    } else {
        
    }
}


#####################################################################
#							FUNCTION LISTS							#
#####################################################################

Function ExecuteQuery($varSQL, $Link) {
    $Result = MySQL_Query($varSQL, $Link) OR ErrorHandler("MYSQL ERROR<BR>" . MySQL_Error($Link) . "<BR>" . $varSQL . "<BR>" . SubStr(Print_R(debug_backtrace(), True), 0, 1000));
    Return $Result;
}

Function ErrorHandler($varErrorMessage) {
    Die($varErrorMessage);
}

Function Print_R2($aryArray) {
    Echo Str_Replace("<BR><BR>", "<BR>", Str_Replace("\n", "<BR>", Str_Replace(" ", "&nbsp;", Print_R($aryArray, True))));
}

Function UPDATE_REQUEST($rcId, $RcInstance, $requestor, $CurrentStatus, $pdmUser, $mode, $remarks="") {
    global $LinkRLM;

    $arrayResponse = false;
    $id = "";
    if($mode!='RSM'){$id = "RC_ID"; }else{ $id = "RSM_TICKET_ID"; }

    $varSQL = "SELECT COUNT(*) AS RC_INSTANCE_EXIST FROM PDM_DASHBOARD.PDM_MAIN WHERE `STATUS` != 'RETURNED' AND ".$id." = " . $rcId . " AND INSTANCE = " . $RcInstance;
    $result = ExecuteQuery($varSQL, $LinkRLM);

    $row = mysql_fetch_assoc($result);

    if ($row['RC_INSTANCE_EXIST'] > 0) {
        if(!empty($remarks)){
            $remarkssql = ", A.REMARKS = '" . mysql_real_escape_string($remarks) . "' ";
        }
        $varSQL = "UPDATE PDM_DASHBOARD.PDM_MAIN A 
		SET A.STATUS = '" . $CurrentStatus . "', A.LAST_UPDATE = CONVERT_TZ(NOW(),'SYSTEM','+8:00'), A.UPDATED_BY = '" . $pdmUser . "' ".$remarkssql." 
		WHERE A.STATUS != 'RETURNED' AND A.".$id." = " . $rcId . " AND A.INSTANCE = " . $RcInstance;
        ExecuteQuery($varSQL, $LinkRLM);
        
    } else {
        if($CurrentStatus=='IN-PROCESS'){
          $varSQL = "INSERT INTO PDM_DASHBOARD.PDM_MAIN (REQUESTOR, ".$id.", INSTANCE, STATUS, LAST_UPDATE, UPDATED_BY, ASSIGNED_PDM, REMARKS) 
                    VALUES ('" . $requestor . "'," . $rcId . ", " . $RcInstance . ",'" . $CurrentStatus . "',CONVERT_TZ(NOW(),'SYSTEM','+8:00')," . $pdmUser . "," . $pdmUser . ",'". mysql_real_escape_string($remarks) . "')";
        }else{
         $varSQL = "INSERT INTO PDM_DASHBOARD.PDM_MAIN (REQUESTOR, ".$id.", INSTANCE, STATUS, LAST_UPDATE, UPDATED_BY, REMARKS) 
		VALUES ('" . $requestor . "'," . $rcId . ", " . $RcInstance . ",'" . $CurrentStatus . "',CONVERT_TZ(NOW(),'SYSTEM','+8:00')," . $pdmUser . ",'". mysql_real_escape_string($remarks) . "')";
        }
        

        ExecuteQuery($varSQL, $LinkRLM);
    }

    $arrayResponse = true;


    return $arrayResponse;
}

Function REMOVE_REQUEST($rcId, $RcInstance, $CurrentStatus, $pdmUser, $mode, $remarks=""){
     global $LinkRLM;

    $arrayResponse = false;
    
    $id = "";
    if($mode!='RSM'){$id = "RC_ID"; }else{ $id = "RSM_TICKET_ID"; }

    $varSQL = "SELECT COUNT(*) AS RC_INSTANCE_EXIST FROM PDM_DASHBOARD.PDM_MAIN WHERE `STATUS` != 'RETURNED' AND ".$id." = " . $rcId . " AND INSTANCE = " . $RcInstance;
    $result = ExecuteQuery($varSQL, $LinkRLM);

    $row = mysql_fetch_assoc($result);

    if ($row['RC_INSTANCE_EXIST'] > 0) {
        if(!empty($remarks)){
            $remarkssql = ", A.REMARKS = '" . mysql_real_escape_string($remarks) . "' ";
        }
        $varSQL = "UPDATE PDM_DASHBOARD.PDM_MAIN A 
		SET A.STATUS = '" . $CurrentStatus . "', A.LAST_UPDATE = CONVERT_TZ(NOW(),'SYSTEM','+8:00'), A.UPDATED_BY = '" . $pdmUser . "' ".$remarkssql." 
		WHERE A.STATUS != 'RETURNED' AND A.".$id." = " . $rcId . " AND A.INSTANCE = " . $RcInstance;
        ExecuteQuery($varSQL, $LinkRLM);
        
    }

    $arrayResponse = true;


    return $arrayResponse;
}
Function WRITE_TRANS_LOG($rcId, $RcInstance, $requestor, $CurrentStatus, $pdmUser, $mode="") {
    global $LinkRLM;
    $id =($mode=='RSM') ? 'RSM_TICKET_ID' : 'RC_ID';
    $varSQL = "INSERT INTO PDM_DASHBOARD.PDM_TRANSACTION_LOG (USER, ".$id.", INSTANCE, ACTION, TRANSACTION_TIME) 
	VALUES (" . $pdmUser . "," . $rcId . "," . $RcInstance . ", ' CHANGE STATUS TO " . $CurrentStatus . "', CONVERT_TZ(NOW(),'SYSTEM','+8:00'))";
    ExecuteQuery($varSQL, $LinkRLM);
}

Function REDIRECT_PAGE($redirectpage) {
    global $LinkRLM;

    $varSQL = "SELECT START_PAGE FROM USER.SYSTEMS WHERE NAME = '" . $redirectpage . "'";
    $result = ExecuteQuery($varSQL, $LinkRLM);
    $row = mysql_fetch_assoc($result);

    return $row['START_PAGE'];
}

Function DESTROY_SESSION() {
    session_destroy();
    session_unset();
    return false;
}

FUNCTION SEND_MAIL($requestid, $rciid, $status, $mode, $parameter){
    global $LinkRLM;
    $id_field = "";
//    $rciid += 1;
    switch ($mode) {
        case 'RLM':
            $id_field = 'RC_ID';
            $subject = 'RLM #'.$requestid.' Route Linkage Module Request '.$status;
            
            
            //get ticket details
            //get main details 
            $arrayData = array();
           $sql = "SELECT UPPER(A.REQUESTOR) REQUESTOR, A.".$id_field.", (A.INSTANCE + 1) INSTANCE, A.`STATUS`, UPPER(B.NAME) ASSIGNED_PDM, REMARKS REASON  from pdm_dashboard.pdm_main A LEFT JOIN 
                                pdm_dashboard.pdm_users B ON A.ASSIGNED_PDM = B.WWID 
                                 WHERE A.".$id_field." ='".$requestid."' AND A.INSTANCE ='".$rciid."'";
            $result = ExecuteQuery($sql, $LinkRLM);
            while ($row = mysql_fetch_assoc($result)) {
                $arrayData = $row;
            }

            //get other details
             $sql_details = "SELECT B.RC_ID, B.FIELD_NAME, B.FIELD_VALUE FROM pdm_dashboard.submit_data_history B 
                                    WHERE B.FIELD_NAME IN ('MATNR', 'MFRPN', 'BISMT', 'DIE_TYPE', 'CORE_DIE', 'HANDLER', 'TESTER', 'FLOW_COMETS', 'FLOW') 
                                    AND B.".$id_field." ='".$requestid."'";
            $result_details = ExecuteQuery($sql_details, $LinkRLM);
            $arrayData_details = array();
            while ($row_details = mysql_fetch_assoc($result_details)) {
                $arrayData_details[$row_details['FIELD_NAME']] = $row_details['FIELD_VALUE'];
            } 
        
           if(empty($arrayData_details)){
                $sql_details = "SELECT B.RC_ID, B.FIELD_NAME, B.FIELD_VALUE FROM route_builder.route_collection_profile B 
                                       WHERE B.FIELD_NAME IN ('MATNR', 'MFRPN', 'BISMT', 'DIE_TYPE', 'CORE_DIE', 'HANDLER', 'TESTER', 'FLOW_COMETS', 'FLOW') 
                                       AND B.".$id_field." ='".$requestid."'";
               $result_details = ExecuteQuery($sql_details, $LinkRLM);
               $arrayData_details = array();
               while ($row_details = mysql_fetch_assoc($result_details)) {
                   $arrayData_details[$row_details['FIELD_NAME']] = $row_details['FIELD_VALUE'];
               } 
           }
            
             $creationdate_sql = "SELECT MIN(LE_DATETIME) CREATION_DATE FROM sus_route_sap.srs_master WHERE 
                                     RC_ID ='".$requestid."' AND RCI_ID ='".$rciid."' ";
            $result_creationdate = ExecuteQuery($creationdate_sql, $LinkRLM);
            while ($rowdetails_date = mysql_fetch_assoc($result_creationdate)) {
                $arrayData['CREATION_DATE'] = $rowdetails_date['CREATION_DATE'];
            }
            break;
       case 'RSM':
           $id_field = 'RSM_TICKET_ID';
           
           $subject = 'RSM #'.$requestid.' Route Selection Module Request '.$status;
           
           
            //get ticket details
            //get main details 
              $sql = "SELECT UPPER(A.REQUESTOR) REQUESTOR, A.".$id_field.", (A.INSTANCE + 1) INSTANCE, A.`STATUS`, UPPER(B.NAME) ASSIGNED_PDM, REMARKS REASON from pdm_dashboard.pdm_main A LEFT JOIN 
                                pdm_dashboard.pdm_users B ON A.ASSIGNED_PDM = B.WWID 
                                 WHERE A.".$id_field." ='".$requestid."' ";
            $result = ExecuteQuery($sql, $LinkRLM);
            $arrayData = array();
            while ($row = mysql_fetch_assoc($result)) {
                $arrayData = $row;
            }

            //get other details
             $sql_details = "SELECT B.RSM_TICKET_ID, B.FIELD_NAME, B.FIELD_VALUE FROM pdm_dashboard.submit_data_history B 
                                    WHERE B.FIELD_NAME IN ('MATNR', 'MFRPN', 'BISMT', 'DIE_TYPE', 'CORE_DIE', 'HANDLER', 'TESTER', 'FLOW_COMETS', 'FLOW') 
                                    AND B.".$id_field." ='".$requestid."'";
            $result_details = ExecuteQuery($sql_details, $LinkRLM);
            $arrayData_details = array();
            while ($row_details = mysql_fetch_assoc($result_details)) {
                $arrayData_details[$row_details['FIELD_NAME']] = $row_details['FIELD_VALUE'];
            }         
           
             $creationdate_sql = "SELECT CREATION_DATETIME CREATION_DATE FROM rsm.rsm_ticket_main WHERE 
                                     RSM_TICKET_ID='".$requestid."'";
            $result_creationdate = ExecuteQuery($creationdate_sql, $LinkRLM);
            while ($rowdetails_date = mysql_fetch_assoc($result_creationdate)) {
                $arrayData['CREATION_DATE'] = $rowdetails_date['CREATION_DATE'];
            }
            break;
        default:
            break;
    }
    
//print_r($arrayData);

    $message = "";
    $message.='<p style="font-family: Trebuchet MS,Arial,san-serif; font-size:12;">Details has been updated successfully!<br/><br/></p>';
    $message.= "<TABLE class='table table-striped table-bordered'>";
    $requestorname = "";

    $excemptions = array('REASON', 'STATUS');
    foreach($arrayData as $keys =>$values){
        
        if($keys=='STATUS'){
            if(empty($arrayData['REASON'])){
               $rows.="<TR>";
              $rows.="<TD>STATUS</TD><TD> : ".$arrayData['STATUS']."</TD>";
              $rows.="</TR>";
            }else{
              $rows.="<TR>";
              $rows.="<TD>STATUS</TD><TD> : ".$arrayData['STATUS']."</TD>";
                $rows.="</TR>";
              $rows.="</TR><TD>REMARKS</TD><TD> : ".stripslashes($arrayData['REASON'])."</TD>";
              $rows.="</TR>";
            }

        }
        
        if(!in_array($keys, $excemptions)){
            if($keys=='REQUESTOR'){
                $requestorname = $values;
                $rows.="<TR>";
                $rows.="<TD>".$keys."</TD><TD> : ".$values."</TD>";
                $rows.="</TR>";
            }elseif($keys=='ASSIGNED_PDM'){
                 $assignedpdm_email = WORKDAY_GET_NAME_FROM_NUM($LinkRLM,$values);
                $rows.="<TR>";
                $rows.="<TD>".$keys."</TD><TD> : ".$values."</TD>";
                $rows.="</TR>";
            }else{
                $rows.="<TR>";
                $rows.="<TD>".$keys."</TD><TD> : ".$values."</TD>";
                $rows.="</TR>";
            }
        }

    }

    $message.=$rows;
    
    foreach($arrayData_details as $key =>$value){
        $message.="<TR>";
        $message.="<TD>".$key."</TD><TD> : ".$value."</TD>";
        $message.="</TR>";
    }

    $message.="</TABLE>";
    
    ini_set("SMTP", "testwebsvr.maxim-ic.internal");
    ini_set("smtp_port", 25);
   
    
   $requestor_email = WORKDAY_GET_NAME_FROM_NUM($LinkRLM,$requestorname);
            
     $recipients = GET_PARAM_VALUES($parameter);
    $cc = array();
    $to = array();
    foreach($recipients as $key => $value){
        switch ($key) {
            case 'CC':
                    $cc[] = $value;
                break;
            case 'TO':
                    $to[] = $value;
                    $to[] = $requestor_email;
                    $to[] = $assignedpdm_email;
                break;
            default:
                break;
        }
    }
    $final_to = ( implode(',', array_unique($to)));

    $final_cc = ( implode(',', array_unique($cc)));
    
    $headers = "From: do_not_reply@maximintegrated.com \r\n";
    $headers .= 'Cc: '. $final_cc. "\r\n";
 
    $headers .= "Content-Type: text/html; charset=utf-8; MIME-Version: 1.0\r\n";
   
//    print_r($message);
    
    mail($final_to, $subject, $message, $headers);
}

Function GET_PARAM_VALUES($paramName) {
    global $LinkRLM;
    $varSQL = "SELECT PARAM_VALUE, PARAM_DESC FROM pdm_dashboard.pdm_param WHERE PARAM_NAME = '" . $paramName . "'";
    $result_creationdate = ExecuteQuery($varSQL, $LinkRLM);
    $arrayData = array();
    while ($rowdetails_date = mysql_fetch_assoc($result_creationdate)) {
        $arrayData[$rowdetails_date['PARAM_DESC']] = $rowdetails_date['PARAM_VALUE'];
    }

    return $arrayData;
}


Function WORKDAY_GET_NAME_FROM_NUM($LinkRB,$varName) {
    $varSQL = "
		SELECT
		DISTINCT EMAIL 
		FROM PERSONNEL.V_WORKDAY
		WHERE UPPER(CONCAT_WS(
			' ',
			PREFERRED_NAME,
			LAST_NAME
		))  = '" . $varName . "' LIMIT 1";
    $RSMain = ExecuteQuery($varSQL, $LinkRB);
    While ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
        Return $LineMain["EMAIL"];
    }

}
Function CHECK_IF_ALREADY_LINKED($rcid, $instance, $mode) {
    global $LinkRLM;
    
    $result = array();
    $allowtobeclose = GET_PARAM_VALUES('STATUS_CHECK');
    if ($mode == 'RLM') {
                $sql = "SELECT RC_ID, RCI_ID, SRS_STATUS FROM sus_route_sap.srs_master 
                WHERE RC_ID='".$rcid."' AND RCI_ID ='".$instance."' 
                AND SRS_STATUS IN ('" . Implode("','", $allowtobeclose) . "') 
                GROUP BY RC_ID, RCI_ID ";
        $RSMain = ExecuteQuery($sql, $LinkRLM);
        While ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
            $result = $LineMain;
        }
    }else if ($mode == 'RSM') {
		$result =  'true';
	}

    if((empty($result))||($result=="")){
        return 'false';
    }else{
        return 'true';
    }
    
}
?>