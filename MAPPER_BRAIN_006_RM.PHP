<?php
header('Access-Control-Allow-Origin: *');
ini_set("error_reporting",E_ALL);
ini_set("display_errors",1);

include_once("/var/www/html/MYSQLI_COMMON_FUNCTIONS.PHP");
date_default_timezone_set('UTC');
include_once("/var/www/html/API/BRAIN_FUNCTIONS.PHP");
include_once("./WS_FUNCTIONS3.PHP");


$aryIO = array();
$aryIO[] = array(
	"INPUT_TYPE"=>array("PRS_ID","MFG_PART_NUM"),
	"OUTPUT_TYPE"=>array("RES_GET")
);
$aryIO[] = array(
	"INPUT_TYPE"=>array("JSON"),
	"OUTPUT_TYPE"=>array("TPI_CLONE","TPI_PUT_ONE")
);

$aryIO[] = array(
	"INPUT_TYPE"=>array("SAP_RTE_ID"),
	"OUTPUT_TYPE"=>array("TPI_DATA_GET_ALL","TPI_DATA_PRI_GET_ALL","TPI_SUMMARY_GET_ALL","TPI_SUMMARY_COMPARE_GET_ALL")
);

$aryIO[] = array(
	"INPUT_TYPE"=>array("TPI_ID"),
	"OUTPUT_TYPE"=>array("TPI_DATA_GET_ONE","TPI_SUMMARY_GET_ONE","TPI_DATA_PRI_GET_ONE")
);

$aryIO[] = array(
	"INPUT_TYPE"=>array("API_LOG_ID"),
	"OUTPUT_TYPE"=>array("API_REPLAY")
);

#disabled for now
#TPI_CHANGE_SIMULATE_FLOW_UP
$aryIO["TPI_CHANGE_SIMULATE"] = array(
	"INPUT_TYPE"=>array("SAP_RTE_ID","TPI_ID"),
	"OUTPUT_TYPE"=>array(
		"TPI_CHANGE_SIMULATE_PRISETUP_UP",
		"TPI_CHANGE_SIMULATE_PRISETUP_DOWN",
		"TPI_CHANGE_SIMULATE_FLOW_DOWN",
		"TPI_CHANGE_SIMULATE_UTPI_UP",
		"TPI_CHANGE_SIMULATE_UTPI_DOWN",
		"TPI_CHANGE_SIMULATE_TTPI_UP",
		"TPI_CHANGE_SIMULATE_TTPI_DOWN",
		"TPI_CHANGE_SIMULATE_INDX_UP",
		"TPI_CHANGE_SIMULATE_INDX_DOWN",
		"TPI_CHANGE_SIMULATE_OEE_UP",
		"TPI_CHANGE_SIMULATE_OEE_DOWN",
		"TPI_CHANGE_SIMULATE_RANDOM"
	)
);


$aryRequest = $_REQUEST;
$bolRawData = false;

if(!isset($aryRequest["INPUT_TYPE"])) {
	$json = file_get_contents('php://input');
	$aryRequest = json_decode($json,true);
	$bolRawData = true;
}


#print_r($aryRequest);
#die();

$bolOK = REQUEST_VALIDATE($aryIO,$aryRequest);


$bolDebug = false;
#$bolDebug = true;
if(isset($_REQUEST["DEBUG"]) && $_REQUEST["DEBUG"] != "") {
	$bolDebug = true;
}



$strInputType = $aryRequest["INPUT_TYPE"];
$strOutputType = $aryRequest["OUTPUT_TYPE"];
$aryInput = $aryRequest["INPUT"];

if($bolDebug === true) {
	echo "DEBUG<BR>";
}


switch (true) {
	case $bolOK !== true:
		$strStatus = "FAILURE";
		$strMessage = "INVALID INPUT_TYPE/OUTPUT_TYPE COMBINATION";
		$aryData = array();
		break;
	case $strOutputType == "API_REPLAY":
		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();

		$iConRLM = Open_ILink("MXHTAFOT01L");

		foreach($aryInput as $intCtr => $intLogID) {
			$strSQL = "
				SELECT
				SERVER_PAGE,
				REQUEST_STRING
				FROM BRAIN.API_LOG
				WHERE API_LOG_ID = '".mysqli_real_escape_string($iConRLM,$intLogID)."';";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			$strURL = null;
			$aryRequestTemp = array();
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strURL = "http://localhost/".$LineMain["SERVER_PAGE"];
				parse_str($LineMain["REQUEST_STRING"],$aryRequestTemp);
			}

			#echo $strURL."<BR>";
			#Print_R2($aryRequestTemp);

			switch (true) {
				case is_null($strURL):
					$strStatus = "FAILURE";
					$strMessage = "INVALID URL";
					break;
				case count(array_diff(array_keys($aryRequestTemp),array("INPUT_TYPE","OUTPUT_TYPE","INPUT"))) > 0:
					$strStatus = "FAILURE";
					$strMessage = "INVALID ARGUMENTS";
					break;
				default:

					$aryResponse = WS_URL_POST_AND_RETRIEVE_V2($strURL,$aryRequestTemp,$bolDebug);
					$aryContent = JSON_Decode($aryResponse["content"],true);
					
					print_r2($aryResponse);

					switch (true) {
						case JSON_LAST_ERROR() != JSON_ERROR_NONE:
							$strStatus = "FAILURE";
							$strMessage = $aryResponse["content"];
							$aryData[$intCtr] = "";

							break;
						case $aryContent["STATUS"] != "SUCCESS":
							$strStatus = $aryContent["STATUS"];
							$strMessage = $aryContent["MESSAGE"];
							$aryData[$intCtr] = "";

							break;
						default:
							
							$strStatus = $aryContent["STATUS"];
							$strMessage = $aryContent["MESSAGE"];
							#2025-07-23 RM this makes a sub-array. be aware
							$aryData[$intCtr] = $aryContent["DATA"];
							break;
					}

					break;
			}
		}

		mysqli_close($iConRLM);	


		break;
	case strpos($strOutputType,"TPI_CHANGE_SIMULATE") !== false:
		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();		

		if($strOutputType == "TPI_CHANGE_SIMULATE_RANDOM") {
			$aryOutputType = array_diff($aryIO["TPI_CHANGE_SIMULATE"]["OUTPUT_TYPE"],array($strOutputType));
			#remove this one from the list of choices
			$intRand = rand(1,count($aryOutputType)-1);
			$strOutputType = $aryOutputType[$intRand];
		}

		$aryRX = array(
			"TPI_CHANGE_SIMULATE_",
			"(PRISETUP|FLOW|UTPI|TTPI|INDX|OEE)",
			"_",
			"(UP|DOWN)",
			"$"
		);
		$strRX = "/".implode("",$aryRX)."/";
		#$strRX = "/(TPI_CHANGE_SIMULATE_)(FLOW|UTPI|TTPI|INDX|OEE|TD_EFF|WFR_IDX_PCT)[_](UP|DOWN)$/";
		
		preg_match_all($strRX,$strOutputType,$aryMatch);
		
		$strField = null;
		$strDirection = null;
		if(isset($aryMatch[0]) && count($aryMatch[0]) > 0) {
			$strField = $aryMatch[1][0];
			$strDirection = $aryMatch[2][0];
		}

		if($bolDebug === true) {
			print_r2($aryMatch);
			echo $strField." / ".$strDirection."<BR>";
		}

		if(!is_null($strField)) {
			$iConRLM = Open_ILink("MXHTAFOT01L");

			foreach($aryInput as $intCtr => $strInput) {
				try {
					#pick a sunday/fyww that is <= 4 weeks after the max existing fyww among instances for this sap_rte_id and apply to this modified instance
					$intRand = rand(1,4);

					#turn everything to SAP_RTE_ID
					$strSRI = null;
					$intTPIIDThat = null;
					$intFYWW = null;
					$strESD = null;

					if($strInputType == "SAP_RTE_ID") {
						$strSRI = $strInput;

						$strSQL = "
							SELECT
							TPI_ID
							FROM BRAIN.TP_INSTANCE_MAIN
							WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
							ORDER BY EFF_START_DT;";
						$RSMain = ExecuteIQuery($strSQL,$iConRLM);
						while ($LineMain = mysqli_fetch_assoc($RSMain)) {
							$intTPIIDThat = $LineMain["TPI_ID"];
						}

						if(is_null($intTPIIDThat)) {
							#no existing instance, so start at current
							$strSQL = "
								SELECT
								MFG_YEAR*100+WEEK_NUMBER FYWW,
								MIN(MFG_DATE) MFG_DATE
								FROM shared.DMCLS_MEMORY DMCLS
								WHERE MFG_YEAR*100+WEEK_NUMBER = 
								(
									SELECT
									MFG_YEAR*100+WEEK_NUMBER
									FROM shared.DMCLS_MEMORY
									WHERE MFG_DATE = DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 4 WEEK),'%Y-%m-%d')
								)
								GROUP BY FYWW;";
						}else{
							$strSQL = "
								SELECT
								MFG_YEAR*100+WEEK_NUMBER FYWW,
								MFG_DATE MFG_DATE
								FROM shared.DMCLS_MEMORY
								WHERE DATE_FORMAT(MFG_DATE,'%w') = 0
								AND MFG_DATE = 
								(
									SELECT
									DATE_ADD(MAX(EFF_START_DT),INTERVAL ".$intRand." WEEK)
									FROM BRAIN.TP_INSTANCE_MAIN
									WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
								);";
						}
					}else{
						$intTPIIDThat = $strInput;
						
						$strSQL = "
							SELECT
							MFG_YEAR*100+WEEK_NUMBER FYWW,
							MFG_DATE MFG_DATE
							FROM shared.DMCLS_MEMORY
							WHERE DATE_FORMAT(MFG_DATE,'%w') = 0
							AND MFG_DATE = 
							(
								SELECT
								DATE_ADD(MAX(EFF_START_DT),INTERVAL ".$intRand." WEEK)
								FROM BRAIN.TP_INSTANCE_MAIN
								WHERE SAP_RTE_ID = 
								(
									SELECT
									SAP_RTE_ID
									FROM BRAIN.TP_INSTANCE_MAIN
									WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIIDThat)."
								)
							);";
					}

					$RSMain = ExecuteIQuery($strSQL,$iConRLM);
					while ($LineMain = mysqli_fetch_assoc($RSMain)) {
						$intFYWW = $LineMain["FYWW"];
						$strESD = $LineMain["MFG_DATE"];
					}

					if(!is_null($intTPIIDThat)) {
						$aryTemp = array(
							"TPI_ID"=>$intTPIIDThat,
							"EFF_START_DT"=>$strESD,
							"FYWW"=>$intFYWW,
							"PROCESS_TYPE"=>$strField."-".$strDirection,
							"NOTES"=>$strOutputType." ".date("Y-m-d H:i:s",time())
						);
					}else{
						$aryTemp = array(
							"SAP_RTE_ID"=>$strSRI,
							"EFF_START_DT"=>$strESD,
							"FYWW"=>$intFYWW,
							"PROCESS_TYPE"=>$strField."-".$strDirection,
							"NOTES"=>$strOutputType." ".date("Y-m-d H:i:s",time())
						);
					}

					$intTPIIDThis = TPI_CLONE($iConRLM,$aryTemp,$bolDebug);

					$aryMain = TPI_FROM_TPIID_GET($iConRLM,array("DATA"),array($intTPIIDThis),$bolDebug);


					$aryMain[$intTPIIDThis]["DATA"] = TPI_DATA_CHANGE($iConRLM,$aryMain[$intTPIIDThis]["DATA"],$strField,$strDirection,$bolDebug);

					$intRows = TPI_PUT($iConRLM,$aryMain[$intTPIIDThis],$intTPIIDThis,$bolDebug);

					$aryData[$intCtr] = $intTPIIDThis;

				} catch (exception $e) {
					$strStatus = "FAILURE";
					$strMessage = $e->getMessage();
					$aryData = array();
				}
			}
		}else{
			$strStatus = "FAILURE";
			$strMessage = "INVALID SCENARIO";
			$aryData = array();
		}

		mysqli_close($iConRLM);
		break;
	case $strOutputType == "TPI_PUT_ONE":
		$iConRLM = Open_ILink("MXHTAFOT01L");

		#2025-07-23 RM need good logging capabilities for debugging
		$intLogID = API_LOG_PUT($iConRLM,$aryRequest,$bolDebug);


		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();		
		
		#this accepts only one, keyed by TPI_ID, not by counter
		try{
			if($bolRawData === true) {
				#only decode if not already getting it from raw data payload
			}else{
				$aryInput = json_decode($aryInput,true);
			}
			
			if(JSON_LAST_ERROR() == JSON_ERROR_NONE) {
				foreach($aryInput as $intTPIID => $aryInputTemp) {
					$intChangeCount = TPI_PUT($iConRLM,$aryInputTemp,$intTPIID,$bolDebug);
					$aryData[$intTPIID] = $intChangeCount;
				}
			}else{
				throw new exception("INVALID JSON");
			}
			
		} catch (exception $e) {
			$strStatus = "FAILURE";
			$strMessage = $e->getMessage();
			$aryData = array();
		}
	
		
		#kind of sloppy, but i dont want to reopen connection, and each output_type might require a different connection, so i cannot assume
		$aryTemp = array(
			"STATUS"=>$strStatus,
			"MESSAGE"=>$strMessage,
			"DATA"=>$aryData
		);
		API_LOG_UPDATE($iConRLM,$intLogID,json_encode($aryTemp),$bolDebug);

	
		mysqli_close($iConRLM);
		break;	
	case $strOutputType == "TPI_DATA_GET_ONE" || $strOutputType == "TPI_DATA_PRI_GET_ONE" || $strOutputType == "TPI_SUMMARY_GET_ONE":
		$iConRLM = Open_ILink("MXHTAFOT01L");

		$strSQL = "call shared.CHECK_DMCLS_MEMORY();";
		ExecuteIQuery($strSQL,$iConRLM);

		switch ($strOutputType) {
			case "TPI_DATA_GET_ONE":
				$aryNeed = array("DATA");
				break;
			case "TPI_DATA_PRI_GET_ONE":
				$aryNeed = array("DATA_PRI");
				break;
			case "TPI_SUMMARY_GET_ONE":
				$aryNeed = array("SUMMARY");
				break;
		}

		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();		
		
		$aryMap = array("TPIID_CTR"=>array());
		foreach($aryInput as $intCtr => $intTPIID) {
			$aryMap["TPIID_CTR"][$intTPIID][] = $intCtr;
		}

		try{
			$aryResponse = TPI_FROM_TPIID_GET($iConRLM,$aryNeed,array_keys($aryMap["TPIID_CTR"]),$bolDebug);
			foreach($aryResponse as $intTPIID => $aryResponseTemp) {
				foreach($aryMap["TPIID_CTR"][$intTPIID] as $intCtr) {
					#2025-07-08 RM this is the only place i am relaxing the rule of OUTPUT key matches INPUT key
					$aryData[$intTPIID] = $aryResponseTemp;
				}
			}
		} catch (exception $e) {
			$strStatus = "FAILURE";
			$strMessage = $e->getMessage();
			$aryData = array();
		}
	
		mysqli_close($iConRLM);
		break;	
	case $strOutputType == "TPI_SUMMARY_GET_ALL" || $strOutputType == "TPI_DATA_GET_ALL" || $strOutputType == "TPI_DATA_PRI_GET_ALL" || $strOutputType == "TPI_SUMMARY_COMPARE_GET_ALL":
		$iConRLM = Open_ILink("MXHTAFOT01L");

		$strSQL = "call shared.CHECK_DMCLS_MEMORY();";
		ExecuteIQuery($strSQL,$iConRLM);

		switch ($strOutputType) {
			case "TPI_SUMMARY_GET_ALL":
				$aryNeed = array("SUMMARY");
				break;
			case "TPI_DATA_GET_ALL":
				$aryNeed = array("DATA");
				break;
			case "TPI_DATA_PRI_GET_ALL":
				$aryNeed = array("DATA_PRI");
				break;
			case "TPI_SUMMARY_COMPARE_GET_ALL":
				$aryNeed = array("SUMMARY","COMPARE");
				break;
		}

		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();		
		foreach($aryInput as $intCtr => $strSRI) {
			try{
				$aryResponse = TPI_FROM_SRI_GET($iConRLM,$aryNeed,$strSRI,$bolDebug);
				$aryData[$intCtr] = $aryResponse;
			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
				$aryData = array();
			}
		}
	

		mysqli_close($iConRLM);
		break;	
	case $strOutputType == "TPI_CLONE":
		$iConRLM = Open_ILink("MXHTAFOT01L");

		#2025-07-23 RM need good logging capabilities for debugging
		$intLogID = API_LOG_PUT($iConRLM,$aryRequest,$bolDebug);

		$strSQL = "call shared.CHECK_DMCLS_MEMORY();";
		ExecuteIQuery($strSQL,$iConRLM);

		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();
		foreach($aryInput as $intCtr => $jsnInput) {
			try{
				$aryInputTemp = json_decode($jsnInput,true);
				
				if(JSON_LAST_ERROR() == JSON_ERROR_NONE) {
					$intTPIID = TPI_CLONE($iConRLM,$aryInputTemp,$bolDebug);
					$aryData[$intCtr] = $intTPIID;
				}else{
					throw new exception("INVALID JSON");
				}
			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
				$aryData = array();
			}
		}	

		#kind of sloppy, but i dont want to reopen connection, and each output_type might require a different connection, so i cannot assume
		$aryTemp = array(
			"STATUS"=>$strStatus,
			"MESSAGE"=>$strMessage,
			"DATA"=>$aryData
		);
		API_LOG_UPDATE($iConRLM,$intLogID,json_encode($aryTemp),$bolDebug);

		mysqli_close($iConRLM);
		break;
	case $strOutputType == "RES_GET":
		$iConRLM = Open_ILink("MXHTAFOT01L");

		$aryData = RES_GET($iConRLM,$aryInput,$bolDebug);

		mysqli_close($iConRLM);

		break;		
}

$aryReturn = array(
	"STATUS"=>$strStatus,
	"MESSAGE"=>$strMessage,
	"DATA"=>$aryData
);

if(isset($_REQUEST["NO_JSON"])) {
	print_r2($aryReturn);
}else{
	echo json_encode($aryReturn);
}


function API_LOG_UPDATE($iConRLM,$intLogID,$jsnActualOutput,$bolDebug=false) {
	$strSQL = "
		UPDATE
		BRAIN.API_LOG
		SET ACTUAL_OUTPUT_JSON = '".mysqli_real_escape_string($iConRLM,$jsnActualOutput)."'
		WHERE API_LOG_ID = ".mysqli_real_escape_string($iConRLM,$intLogID).";";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}

	ExecuteIQuery($strSQL,$iConRLM);
}


function API_LOG_PUT($iConRLM,$aryRequest,$bolDebug=false) {
	$intPos = strpos($_SERVER["REQUEST_URI"],"?");
	if($intPos !== false) {
		$strPage = substr($_SERVER["REQUEST_URI"],0,$intPos);
	}else{
		$strPage = $_SERVER["REQUEST_URI"];
	}

	$aryTemp = array(
		"REMOTE_ADDR"=>$_SERVER["REMOTE_ADDR"],
		"SERVER_PAGE"=>$strPage,
		"REQUEST_STRING"=>http_build_query($aryRequest),
		"INPUT_TYPE"=>$aryRequest["INPUT_TYPE"],
		"OUTPUT_TYPE"=>$aryRequest["OUTPUT_TYPE"]		
	);

	foreach($aryTemp as $key => $value) {
		switch (true) {
			case $key == "REQUEST_COMP_SER":
				$aryTemp[$key] = "COMPRESS('".mysqli_real_escape_string($iConRLM,$value)."')";
				break;
			case $value != "":
				$aryTemp[$key] = "'".mysqli_real_escape_string($iConRLM,$value)."'";
				break;
			default:
				$aryTemp[$key] = "NULL";
				break;
		}
	}
	$aryTemp["LOG_DT"] = "NOW()";

	$strSQL = "
		INSERT INTO
		BRAIN.API_LOG
		(REMOTE_ADDR,SERVER_PAGE,REQUEST_STRING,INPUT_TYPE,OUTPUT_TYPE,LOG_DT)
		VALUES
		(".implode(",",$aryTemp).");";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	ExecuteIQuery($strSQL,$iConRLM);

	return mysqli_insert_id($iConRLM);
}


function TPI_PUT($iConRLM,$aryMainTemp,$intTPIID,$bolDebug=false) {
	#2025-07-08 RM now accepting the entire data object with TPI_ID as key
	
	#these fields should always be there regardless of summary/compare/data
	$aryField = array('SAP_RTE_ID','EFF_START_DT','FYWW','INSTANCE','PROCESS_TYPE','NOTES');
	#this one is optional as we are not tracking users yet
	$aryField2 = array("CHANGE_USER");

	$aryTemp = array();
	foreach($aryField as $strField) {
		switch (true) {
			case !isset($aryMainTemp[$strField]):
				throw new exception("MISSING ".$strField);
				break;
			case $strField == "PROCESS_TYPE":
				$aryTemp[$strField] = implode("|",$aryMainTemp[$strField]);
				break;
			default:
				$aryTemp[$strField] = $aryMainTemp[$strField];
				break;
		}		
	}
	
	foreach($aryField2 as $strField) {
		if(isset($aryMainTemp[$strField])) {
			$aryTemp[$strField] = $aryMainTemp[$strField];
		}
	}

	$arySQLTemp = array();
	foreach($aryTemp as $key => $value) {
		$value = trim($value);
		if($value != "") {
			$arySQLTemp[] = $key." = '".mysqli_real_escape_string($iConRLM,$value)."'";
		}else{
			$arySQLTemp[] = $key." = NULL";
		}
	}

	$intRows = 0;


	$strSQL = "
		UPDATE
		BRAIN.TP_INSTANCE_MAIN
		SET ".implode(", ",$arySQLTemp).",CHANGE_DT = NOW()
		WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIID).";";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	ExecuteIQuery($strSQL,$iConRLM);
	$intRows += mysqli_affected_rows($iConRLM);

	#i dont consider generic and mfg_part_num changeable at this time
	
	#must have DATA to put DATA. surprising huh
	if(isset($aryMainTemp["DATA"]["RTE_SEQ_NUM"])) {
		#one-time field build
		$strSQL = "
			SELECT
			*
			FROM BRAIN.TP_INSTANCE_DATA
			ORDER BY TPD_ID
			LIMIT 0,1;";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		#yes i am assuming the table will not be completely empty. sloppy but whatever
		$aryField = array();
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$aryField = array_keys($LineMain);
		}
		$aryFieldInsert = array_diff($aryField,array("TPD_ID"));


		$arySQL = array();

		$arySQL[] = "SET AUTOCOMMIT = 0;";

		$arySQL[] = "BEGIN;";
	
		foreach($arySQL as $strSQL) {
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			ExecuteIQuery($strSQL,$iConRLM);
		}

		if($bolDebug === true) {
			echo __FILE__.".".__FUNCTION__.".".__LINE__."<BR>";
			print_r2($aryMainTemp["DATA"]);
		}
		#insert new rows and retain inserted TPD_ID so we can run updates against them
		#the insert must run BEFORE any updates, if something was deleted we cant very well copy it, can we
		$aryTPDID = array();
		foreach(array_keys($aryMainTemp["DATA"]["RTE_SEQ_NUM"]) as $strSeq) {
			foreach($aryMainTemp["DATA"]["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"] as $intPri => $arySetupTemp) {
				#2025-07-24 RM now allowing for insertion of steps (for dedications, added test steps)
				#PRI must be unique per step!
				switch (true) {
					case isset($arySetupTemp["TPD_ID"]) && trim($arySetupTemp["TPD_ID"]) != "":
						$aryTPDID[] = $arySetupTemp["TPD_ID"];
						break;
					case isset($arySetupTemp["TPD_ID_DONOR"]) && is_null($arySetupTemp["TPD_ID_DONOR"]):
						throw new exception("TPD_ID MISSING, TPD_ID_DONOR MISSING");
						break;
					case isset($arySetupTemp["TPD_ID_DONOR"]):
						$strSQL = "
							INSERT INTO
							BRAIN.TP_INSTANCE_DATA
							(".implode(",",$aryFieldInsert).")
							SELECT
							".implode(",",$aryFieldInsert)."
							FROM BRAIN.TP_INSTANCE_DATA
							WHERE TPD_ID = ".mysqli_real_escape_string($iConRLM,$arySetupTemp["TPD_ID_DONOR"]).";";
						if($bolDebug === true) {
							echo $strSQL."<BR>";
						}
						ExecuteIQuery($strSQL,$iConRLM);
					
						$intTPDID = mysqli_insert_id($iConRLM);
						$arySetupTemp["TPD_ID"] = $intTPDID;

						$aryMainTemp["DATA"]["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri] = $arySetupTemp;

						$aryTPDID[] = $intTPDID;

						break;
				}
			}
		}

		
		if(count($aryTPDID) == 0) {
			throw new exception("NO TPD_ID FOUND");
		}

		$arySQL = array();

		#2025-07-24 RM now run updates

		$aryMap["SEQ_EXCLUSIVE"] = array();

		foreach(array_keys($aryMainTemp["DATA"]["RTE_SEQ_NUM"]) as $strSeq) {
			foreach($aryMainTemp["DATA"]["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"] as $intPri => $arySetupTemp) {
				$intTPDID = $arySetupTemp["TPD_ID"];
				
				$aryTemp = array(
					"RES_PRIO_CD"=>$intPri,
					"TESTER"=>$arySetupTemp["TESTER"],
					"HANDLER"=>$arySetupTemp["HANDLER"],
					"UTPI"=>round($arySetupTemp["UTPI"],0),
					"TTPI"=>round($arySetupTemp["TTPI"],3),
					"INDX"=>round($arySetupTemp["INDX"],3),
					"OEE"=>round($arySetupTemp["OEE"],3),
					"TD_EFF"=>round($arySetupTemp["TD_EFF"],3),
					"WFR_IDX_PCT"=>round($arySetupTemp["WFR_IDX_PCT"],3),
					"UTPH"=>null
				);

				$aryTry = array("IS_DED","DED_IS_PRIMARY","DED_IS_EXCLUSIVE");
				foreach($aryTry as $strTry) {
					if(isset($arySetupTemp[$strTry])) {
						$aryTemp[$strTry] = $arySetupTemp[$strTry];
					}else{
						$aryTemp[$strTry] = null;
					}
				}
					

				switch (true) {
					case !isset($arySetupTemp["DED_IS_EXCLUSIVE"]):
						break;
					case $arySetupTemp["DED_IS_EXCLUSIVE"] != 1:
						break;
					case $intPri != 1:
						throw new exception("TPI_PUT: IS_EXCLUSIVE = 1 BUT SETUP IS NOT PRIMARY");
						break;
					default:
						$aryMap["SEQ_EXCLUSIVE"][$strSeq][] = $intPri;
						break;
				}

				$decUTPH = UTPH_CALC($aryTemp,$bolDebug);
				$aryTemp["UTPH"] = round($decUTPH,3);

				$arySQLTemp = array();
				foreach($aryTemp as $key => $value) {
					switch (true) {
						case $key == "TESTER" || $key == "HANDLER":
							$arySQLTemp[] = $key." = '".mysqli_real_escape_string($iConRLM,$value)."'";
							break;
						case is_null($value) || $value == "":
							$arySQLTemp[] = $key." = NULL";
							break;
						default:
							$arySQLTemp[] = $key." = ".mysqli_real_escape_string($iConRLM,$value);
							break;
					}
				}

				$arySQL[] = "
					UPDATE
					BRAIN.TP_INSTANCE_DATA
					SET ".implode(", ",$arySQLTemp)."
					WHERE TPD_ID = ".mysqli_real_escape_string($iConRLM,$intTPDID).";";
			}
		}

		#2025-07-24 RM IS_exclusive makes all other setups for that step 99+, but preserving original order
		foreach(array_keys($aryMap["SEQ_EXCLUSIVE"]) as $strSeq) {
			if(count($aryMap["SEQ_EXCLUSIVE"][$strSeq]) > 1) {
				throw new exception("TPI_PUT: CAN ONLY HAVE ONE IS_EXCLUSIVE PER STEP");
			}

			$aryThis = $aryMainTemp["DATA"]["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"];
			$aryThat = array();
			ksort($aryThis);

			$intCtrOther = 99;
			#there better be only one
			foreach($aryMap["SEQ_EXCLUSIVE"][$strSeq] as $intPriExclusive) {}

			#this better exist!
			if(isset($aryThis[$intPriExclusive])) {
				$aryThat[$intPriExclusive] = $aryThis[$intPriExclusive];
			}

			foreach(array_keys($aryThis) as $intPriOther) {
				if($intPriOther != $intPriExclusive) {
					$aryThat[$intCtrOther] = $aryThis[$intPriOther];
					++$intCtrOther;
				}
			}

			$aryMainTemp["DATA"]["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"] = $aryThat;
		}
		
		$aryTemp = array();
		foreach($aryTPDID as $intTPDID) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$intTPDID);
		}

		if(count($aryTemp) > 0) {
			$arySQL[] = "
				DELETE FROM
				BRAIN.TP_INSTANCE_DATA
				WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIID)."
				AND TPD_ID NOT IN (".implode(",",$aryTemp).");";
			ExecuteIQuery($strSQL,$iConRLM);
		}
			
		foreach($arySQL as $strSQL) {
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);
			$intRows += mysqli_affected_rows($iConRLM);
		}

		#deliberately not including history records in affected rowcount
		TPI_HIST_PUT($iConRLM,$intTPIID,$bolDebug);

		$arySQL = array();

		$arySQL[] = "COMMIT;";

		$arySQL[] = "SET AUTOCOMMIT = 1;";

		foreach($arySQL as $strSQL) {
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);
		}
	}

	return $intRows;
}


function TPI_HIST_PUT($iConRLM,$intTPIID,$bolDebug=false) {
	$strSQL = "
		SELECT
		*
		FROM BRAIN.TP_INSTANCE_DATA
		ORDER BY TPD_ID
		LIMIT 0,1;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryField = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryField = array_keys($LineMain);
	}

	if(count($aryField) == 0) {
		throw new exception("NO FIELD STRUCTURE FOR TPI_INSTANCE_DATA");
	}

	$strSQL = "
		INSERT INTO
		BRAIN.TP_INSTANCE_HIST_MASTER
		(TPI_ID,HIST_DT)
		VALUES
		(
			".mysqli_real_escape_string($iConRLM,$intTPIID).",
			NOW()
		);";
	ExecuteIQuery($strSQL,$iConRLM);
	$intTPHMID = mysqli_insert_id($iConRLM);

	$strSQL = "
		INSERT INTO
		BRAIN.TP_INSTANCE_HIST
		(TPH_MASTER_ID,".implode(",",$aryField).")
		SELECT
		".mysqli_real_escape_string($iConRLM,$intTPHMID).",
		".implode(",",$aryField)."
		FROM BRAIN.TP_INSTANCE_DATA
		WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIID).";";
	ExecuteIQuery($strSQL,$iConRLM);
}


function UTPH_CALC($aryInput,$bolDebug=false) {

	$decUTPH = $aryInput["OEE"] * $aryInput["TD_EFF"] * $aryInput["WFR_IDX_PCT"] * ($aryInput["UTPI"] * 3600) / ($aryInput["TTPI"] + $aryInput["INDX"]);

	return $decUTPH;
}


function TPI_DATA_CHANGE($iConRLM,$aryData,$strField,$strDirection,$bolDebug=false) {
	#print_r2($aryData);
	#die();

	#2025-07-07 RM for now, only examine primary setups
	$aryMap["HASHSETUP_SEQ_PRI"] = array();
	$aryMap["PNP_HASHSETUP_SEQ"] = array();
	
	#$aryMap["TESTER_HASHSETUP"] = array();
	foreach(array_keys($aryData["RTE_SEQ_NUM"]) as $strSeq) {
		foreach($aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"] as $intPri => $arySetupTemp) {
			$strTester = $arySetupTemp["TESTER"];

			$aryTemp = array(
				"TESTER"=>$strTester,
				"HANDLER"=>$arySetupTemp["HANDLER"],
				"UTPI"=>$arySetupTemp["UTPI"]
			);

			$strHashSetup = sha1(serialize($aryTemp));
			$aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup][$strSeq][] = $intPri;

			if($intPri == 1) {
				$strPNP = "PRI";
			}else{
				$strPNP = "NON-PRI";
			}
			$aryMap["PNP_HASHSETUP_SEQ"][$strPNP][$strHashSetup][] = $strSeq;
			#$aryMap["TESTER_HASHSETUP"][$strTester][] = $strHashSetup;
		}				
	}
	$aryCount = array();
	foreach(array_keys($aryMap["HASHSETUP_SEQ_PRI"]) as $strHashSetup) {
		#must be primary somewhere just so we can see it in the compare
		if(isset($aryMap["PNP_HASHSETUP_SEQ"]["PRI"][$strHashSetup])) {
			$aryCount[$strHashSetup] = count(array_keys($aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup]));
		}
	}
	arsort($aryCount);


	switch (true) {
		case $strField == "PRISETUP" && $strDirection == "UP":
			#UP randomly selects a non-primary setup to be the primary, DOWN shifts the primary down one spot (switches with position 2)
			$strPNP = "NON-PRI";
			if(isset($aryMap["PNP_HASHSETUP_SEQ"][$strPNP])) {
				$aryTemp = array();
				foreach($aryMap["PNP_HASHSETUP_SEQ"][$strPNP] as $strHashSetup => $aryMapTemp) {
					if(count($aryMapTemp) >= count($aryData["RTE_SEQ_NUM"]) / 2) {
						$aryTemp[] = $strHashSetup;
					}
				}

				$intRand = rand(0,count($aryTemp)-1);
				$strHashSetup = $aryTemp[$intRand];

				foreach($aryMap["PNP_HASHSETUP_SEQ"][$strPNP][$strHashSetup] as $strSeq) {
					#move it to pri for this step
					$aryThis = $aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"];
					$aryThat = array();

					$aryUsed = array();
					$intPriNew = 1;
					foreach($aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup][$strSeq] as $intPri) {
						$aryThat[$intPriNew] = $aryThis[$intPri];
						++$intPriNew;
						$aryUsed[] = $intPri;
					}

					foreach(array_keys($aryThis) as $intPri) {
						if(!in_array($intPri,$aryUsed)) {
							$aryThat[$intPriNew] = $aryThis[$intPri];
							++$intPriNew;
						}
					}

					$aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"] = $aryThat;
				}
			}

			break;
		case $strField == "PRISETUP" && $strDirection == "DOWN":
			#UP selects a non-primary setup to be the primary, DOWN shifts the primary down all the way
			$strPNP = "PRI";
			if(isset($aryMap["PNP_HASHSETUP_SEQ"][$strPNP])) {
				$aryTemp = array();
				foreach($aryMap["PNP_HASHSETUP_SEQ"][$strPNP] as $strHashSetup => $aryMapTemp) {
					#has to appear in at least half the steps
					if(count($aryMapTemp) >= count($aryData["RTE_SEQ_NUM"]) / 2) {
						$aryTemp[] = $strHashSetup;
					}
				}

				$intRand = rand(0,count($aryTemp)-1);
				$strHashSetup = $aryTemp[$intRand];

				foreach($aryMap["PNP_HASHSETUP_SEQ"][$strPNP][$strHashSetup] as $strSeq) {
					#move it to pri for this step
					$aryThis = $aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"];
					$aryThat = array();

					$aryUsed = array();
					$intPriNew = 1;
					foreach(array_keys($aryThis) as $intPri) {
						if(!in_array($intPri,$aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup])) {
							$aryThat[$intPriNew] = $aryThis[$intPri];
							++$intPriNew;
							$aryUsed[] = $intPri;
						}
					}

					foreach(array_keys($aryThis) as $intPri) {
						if(!in_array($intPri,$aryUsed)) {
							$aryThat[$intPriNew] = $aryThis[$intPri];
							++$intPriNew;
						}
					}

					$aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"] = $aryThat;
				}
			}

			break;
		case $strField == "FLOW":
			#2025-07-07 RM pick step with lowest HPU (TEST_PERC/UTPH)
			#i considered lowest/fastest based on down/up respectively but this is probably more realistic
			foreach(array_keys($aryCount) as $strHashSetup) {
				$aryMap["SEQ_HPU"] = array();
				
				foreach(array_keys($aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup]) as $strSeq) {
					$arySeqTemp = $aryData["RTE_SEQ_NUM"][$strSeq];
					$decTP = $arySeqTemp["TEST_PERC"];

					foreach($aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup][$strSeq] as $intPri) {
						$arySetupTemp = $aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri];

						$decHPU = $decTP / $arySetupTemp["UTPH"];

						switch (true) {
							case !isset($aryMap["SEQ_HPU"][$strSeq]):
								$aryMap["SEQ_HPU"][$strSeq] = $decHPU;
								break;
							case $decHPU < $aryMap["SEQ_HPU"][$strSeq]:
								$aryMap["SEQ_HPU"][$strSeq] = $decHPU;
								break;
						}
						break;
					}
				}

				asort($aryMap["SEQ_HPU"]);
				foreach(array_keys($aryMap["SEQ_HPU"]) as $strSeqDelete) {
					#fastest step has lowest HPU
					break;
				}

				$aryThis = $aryData["RTE_SEQ_NUM"];
				$aryThat = array();

				if($strDirection == "UP") {
					#clone a step!?

				}else{
					foreach($aryThis as $strSeq => $aryThisTemp) {
						if($strSeq != $strSeqDelete) {
							$aryThat[$strSeq] = $aryThisTemp;
						}
					}
				}


				if($bolDebug === true) {
					echo "BEFORE<BR>";
					print_r2($aryData["RTE_SEQ_NUM"]);
				}

				#2025-07-08 RM obviously cannot remove a step if that would result in zero steps
				if(count($aryThat) > 0) {
					$aryData["RTE_SEQ_NUM"] = $aryThat;
				}

				if($bolDebug === true) {
					echo "AFTER<BR>";
					print_r2($aryData["RTE_SEQ_NUM"]);
				}

				#only one setup to be changed
				break;
			}

			break;
		case $strField == "UTPI" || $strField == "TTPI" || $strField == "INDX" || $strField == "OEE":
			#determine the random percentage by which values will be changed. one percentage so its proportional
			#UTPI is multiples, typically powers of 2. 1,2 equates to 1/2, 1/4 if down, x2, x4 if up. allow up to 2
			#anything else is +/- 30%

			$intPower = rand(1,2);
			switch (true) {
				case $strField == "UTPI" && $strDirection == "UP":
					$decPerc = pow(2,$intPower);
					break;
				case $strField == "UTPI":
					$decPerc = 1 / pow(2,$intPower);
					break;
				case $strDirection == "UP":
					$decPerc = 1 + (rand(1,30) / 100);
					break;
				default:
					$decPerc = 1 - (rand(1,30) / 100);
					break;
			}

			#echo $intPower." / ".$decPerc."<BR>";
			#die();

			foreach(array_keys($aryCount) as $strHashSetup) {
				foreach(array_keys($aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup]) as $strSeq) {
					foreach($aryMap["HASHSETUP_SEQ_PRI"][$strHashSetup][$strSeq] as $intPri) {
						$arySetupTemp = $aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri];

						$decOld = $arySetupTemp[$strField];

						if($strField == "UTPI") {
							$decNew = (int) ($arySetupTemp[$strField] * $decPerc);
						}else{
							$decNew = round($arySetupTemp[$strField] * $decPerc,3);
						}

						if($bolDebug === true) {
							echo $strSeq." / ".$intPri." / ".$arySetupTemp["TESTER"]." / ".$arySetupTemp["HANDLER"]." / ".$arySetupTemp["UTPI"]." / ".$strField." / ".$strDirection." / ".$intPower." / ".$decPerc." / FROM: ".$decOld." TO: ".$decNew."<BR>";
						}

						$arySetupTemp[$strField] = $decNew;						
						$aryData["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri] = $arySetupTemp;

						break;
					}
				}
				#only change one setup at a time
				break;
			}

			break;
	}

	return $aryData;
}


function TPI_FROM_TPIID_GET($iConRLM,$aryNeed,$aryTPIID,$bolDebug=false) {
	$aryTPIID = array_unique($aryTPIID);
	$aryTemp = array();
	foreach($aryTPIID as $intTPIID) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intTPIID);
	}
	#this one is reusable. the one by SAP_RTE_ID is not really, at least not without making it weird
	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			TPI_ID,
			SAP_RTE_ID,
			INSTANCE,
			FYWW,
			EFF_START_DT,
			PROCESS_TYPE,
			NOTES
			FROM BRAIN.TP_INSTANCE_MAIN
			WHERE TPI_ID IN (".implode(",",$aryTemp).");";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$aryTemp = TPI_BASE_GET();
			$aryTemp["SAP_RTE_ID"] = $LineMain["SAP_RTE_ID"];
			$aryTemp["INSTANCE"] = $LineMain["INSTANCE"];
			$aryTemp["FYWW"] = $LineMain["FYWW"];
			$aryTemp["EFF_START_DT"] = $LineMain["EFF_START_DT"];
			$aryTemp["PROCESS_TYPE"] = explode("|",$LineMain["PROCESS_TYPE"]);
			$aryTemp["NOTES"] = $LineMain["NOTES"];

			$intTPIID = $LineMain["TPI_ID"];
			$aryReturn[$intTPIID] = $aryTemp;
		}
	}

	foreach(array_keys($aryReturn) as $intTPIID) {
		$aryResponse = TPI_DATA_GET_V2($iConRLM,$aryNeed,"TPI_ID",$intTPIID,$bolDebug);

		if(in_array("DATA",$aryNeed) || in_array("DATA_PRI",$aryNeed)) {
			$aryReturn[$intTPIID]["DATA"] = $aryResponse;
		}

		if(in_array("SUMMARY",$aryNeed)) {
			$aryReturn[$intTPIID]["SUMMARY"] = TPI_SUMMARIZE($aryResponse,$bolDebug);
		}
	}

	return $aryReturn;
}


function TPI_BASE_GET() {
	return array(
		"SAP_RTE_ID"=>null,
		"INSTANCE"=>0,
		"FYWW"=>null,
		"EFF_START_DT"=>null,
		"PROCESS_TYPE"=>array(),
		"NOTES"=>"",
		"DATA"=>array(),
		"SUMMARY"=>array(),
		"COMPARE"=>array()
	);
}


function TPI_FROM_SRI_GET($iConRLM,$aryNeed,$strSRI,$bolDebug=false) {
	$aryReturn = array();
	$aryTemp = TPI_BASE_GET();
	$aryTemp["SAP_RTE_ID"] = $strSRI;
	$aryTemp["PROCESS_TYPE"] = "CURRENT DATA";
	$aryReturn[0] = $aryTemp;

	$strSQL = "
		SELECT DISTINCT
		TPI_ID,
		SAP_RTE_ID,
		INSTANCE,
		FYWW,
		EFF_START_DT,
		PROCESS_TYPE,
		NOTES
		FROM BRAIN.TP_INSTANCE_MAIN
		WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
		ORDER BY FYWW;";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryTemp = TPI_BASE_GET();
		$aryTemp["SAP_RTE_ID"] = $LineMain["SAP_RTE_ID"];
		$aryTemp["INSTANCE"] = $LineMain["INSTANCE"];
		$aryTemp["FYWW"] = $LineMain["FYWW"];
		$aryTemp["EFF_START_DT"] = $LineMain["EFF_START_DT"];
		$aryTemp["PROCESS_TYPE"] = explode("|",$LineMain["PROCESS_TYPE"]);
		$aryTemp["NOTES"] = $LineMain["NOTES"];

		$intTPIID = $LineMain["TPI_ID"];
		$aryReturn[$intTPIID] = $aryTemp;
	}

	#print_r2($aryReturn);
	#die();
	#$bolDebug = true;
	
	#2025-07-02 RM if DATA is required then return all, DATA_PRI is only res_prio_cd = 1
	
	$aryCompareData = array();
	foreach($aryReturn as $intTPIID => $aryReturnTemp) {
		if($intTPIID == 0) {
			$aryResponse = TPI_DATA_GET_V2($iConRLM,$aryNeed,"SAP_RTE_ID",$strSRI,$bolDebug);
		}else{
			$aryResponse = TPI_DATA_GET_V2($iConRLM,$aryNeed,"TPI_ID",$intTPIID,$bolDebug);
		}

		#DATA, DATA_PRI are dumped directly into DATA
		#COMPARE/SUMMARY use the same list of fields, everything needed for COMPARE/SUMMARY is also in DATA
		#so if DATA or DATA_PRI are there, all fields needed for COMPARE/SUMMARY are there as well
		if(in_array("DATA",$aryNeed) || in_array("DATA_PRI",$aryNeed)) {
			$aryReturnTemp["DATA"] = $aryResponse;
		}

		#print_r2($aryResponse);

		if(in_array("SUMMARY",$aryNeed)) {
			$aryReturnTemp["SUMMARY"] = TPI_SUMMARIZE($aryResponse,$bolDebug);			
		}


		$aryReturn[$intTPIID] = $aryReturnTemp;

		if(in_array("COMPARE",$aryNeed)) {
			$aryCompareData[$intTPIID] = $aryResponse;
		}
	}

	if(in_array("COMPARE",$aryNeed)) {
		$intTPIIDThat = null;
		foreach(array_keys($aryReturn) as $intTPIIDThis) {
			if(!is_null($intTPIIDThat)) {
				if(!isset($aryCompareData[$intTPIIDThat])) {
					throw new exception("EXPECTED TPI_ID IN COMPAREDATA");
				}

				$aryCDTemp = array(
					"THIS"=>$aryCompareData[$intTPIIDThis],
					"THAT"=>$aryCompareData[$intTPIIDThat]
				);
				$arySummaryTemp = array(
					"THIS"=>$aryReturn[$intTPIIDThis]["SUMMARY"],
					"THAT"=>$aryReturn[$intTPIIDThat]["SUMMARY"]
				);

				$aryReturn[$intTPIIDThis]["COMPARE"] = TPI_COMPARE_V2($aryCDTemp,$arySummaryTemp,$bolDebug);
			}

			$intTPIIDThat = $intTPIIDThis;
		}
	}

	#die();
	return $aryReturn;
}


function TPI_SUMMARIZE($aryMain,$bolDebug=false) {
	$aryMDBase = array(
		"FLOW"=>0,
		"TEMP_FLOW"=>array(),
		"UTPI"=>array(),
		"TTPI"=>array(),
		"INDX"=>array(),
		"OEE"=>array(),
		"HPTU"=>array(),
		"UTPH"=>array()
	);

	$aryMap = array(
		"HASHSETUP_SEQ"=>array()
	);


	#print_r2($aryMain);

	#top-level and collection
	$aryMDTemp = $aryMDBase;
	$aryStepName = array();
	foreach($aryMain["RTE_SEQ_NUM"] as $strSeq => $arySeqTemp) {
		$strTempClass = $arySeqTemp["TEMP_CLASS"];
		$aryStepName[] = $arySeqTemp["STEP_NM"];
		#2025-07-02 RM remember the sequence key is a string, not a decimal

		$aryMDTemp["FLOW"] += $arySeqTemp["TEST_PERC"];
		if(!isset($aryMDTemp["TEMP_FLOW"][$strTempClass])) {
			$aryMDTemp["TEMP_FLOW"][$strTempClass] = 0;
		}
		$aryMDTemp["TEMP_FLOW"][$strTempClass] += $arySeqTemp["TEST_PERC"];
	
		if(isset($arySeqTemp["RES_PRIO_CD"][1])) {
			$arySetupTemp = $arySeqTemp["RES_PRIO_CD"][1];

			$aryTemp = array(
				"TESTER"=>$arySetupTemp["TESTER"],
				"HANDLER"=>$arySetupTemp["HANDLER"],
				"UTPI"=>$arySetupTemp["UTPI"]
			);

			$strHashSetup = sha1(serialize($aryTemp));

			$aryMap["HASHSETUP_SEQ"][$strHashSetup][] = $strSeq;

			$aryMDTemp["UTPI"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["UTPI"];
			$aryMDTemp["TTPI"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["TTPI"];
			$aryMDTemp["INDX"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["INDX"];
			$aryMDTemp["OEE"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["OEE"];
			$aryMDTemp["UTPH"][] = $arySetupTemp["UTPH"];
			$aryMDTemp["HPTU"][] = 1 / $arySetupTemp["UTPH"];
		}
	}

	$decUTPH = 1 / array_sum($aryMDTemp["HPTU"]);

	$aryTemp = array();
	foreach($aryMDTemp["TEMP_FLOW"] as $strTempClass => $decTestPerc) {
		$aryTemp[$strTempClass] = round($decTestPerc,3);
	}

	$aryMD = array(
		"FLOW"=>round($aryMDTemp["FLOW"],3),
		"TEMP_FLOW"=>$aryTemp,
		"STEP_NM"=>$aryStepName,
		"UTPI"=>round(array_sum($aryMDTemp["UTPI"]) / $aryMDTemp["FLOW"],3),
		"TTPI"=>round(array_sum($aryMDTemp["TTPI"]) / $aryMDTemp["FLOW"],3),
		"INDX"=>round(array_sum($aryMDTemp["INDX"]) / $aryMDTemp["FLOW"],3),
		"OEE"=>round(array_sum($aryMDTemp["OEE"]) / $aryMDTemp["FLOW"],3),
		"UTPH"=>round($decUTPH,3)
	);

	#print_r2($aryMD);
	#die();

	#DO NOT SORT IN ANY WAY!
	$aryPriSetup = array();
	foreach(array_keys($aryMap["HASHSETUP_SEQ"]) as $strHashSetup) {
		$aryMDTemp = $aryMDBase;
		$aryStepName = array();

		foreach($aryMap["HASHSETUP_SEQ"][$strHashSetup] as $strSeq) {
			$arySeqTemp = $aryMain["RTE_SEQ_NUM"][$strSeq];
			$aryStepName[] = $arySeqTemp["STEP_NM"];

			$aryMDTemp["FLOW"] += $arySeqTemp["TEST_PERC"];
			$strTempClass = $arySeqTemp["TEMP_CLASS"];
			if(!isset($aryMDTemp["TEMP_FLOW"][$strTempClass])) {
				$aryMDTemp["TEMP_FLOW"][$strTempClass] = 0;
			}
			$aryMDTemp["TEMP_FLOW"][$strTempClass] += $arySeqTemp["TEST_PERC"];

			$arySetupTemp = $aryMain["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][1];

			$aryMDTemp["UTPI"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["UTPI"];
			$aryMDTemp["TTPI"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["TTPI"];
			$aryMDTemp["INDX"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["INDX"];
			$aryMDTemp["OEE"][] = $arySeqTemp["TEST_PERC"] * $arySetupTemp["OEE"];
			$aryMDTemp["UTPH"][] = $arySetupTemp["UTPH"];
			$aryMDTemp["HPTU"][] = 1 / $arySetupTemp["UTPH"];
		}

		$decUTPH = 1 / array_sum($aryMDTemp["HPTU"]);
		$aryTemp = array();
		foreach($aryMDTemp["TEMP_FLOW"] as $strTempClass => $decTestPerc) {
			$aryTemp[$strTempClass] = round($decTestPerc,3);
		}

		$aryPriSetup[] = array(
			"TESTER"=>$arySetupTemp["TESTER"],
			"HANDLER"=>$arySetupTemp["HANDLER"],
			"UTPI"=>$arySetupTemp["UTPI"],
			"METADATA"=>array(
				"FLOW"=>round($aryMDTemp["FLOW"],3),
				"TEMP_FLOW"=>$aryTemp,
				"STEP_NM"=>$aryStepName,
				"UTPI"=>round(array_sum($aryMDTemp["UTPI"]) / $aryMDTemp["FLOW"],3),
				"TTPI"=>round(array_sum($aryMDTemp["TTPI"]) / $aryMDTemp["FLOW"],3),
				"INDX"=>round(array_sum($aryMDTemp["INDX"]) / $aryMDTemp["FLOW"],3),
				"OEE"=>round(array_sum($aryMDTemp["OEE"]) / $aryMDTemp["FLOW"],3),
				"UTPH"=>round($decUTPH,3)
			)
		);
	}	

	$aryReturn = array(
		"METADATA"=>$aryMD,
		"PRIMARY_SETUP"=>$aryPriSetup
	);

	return $aryReturn;
}


function TPI_COMPARE_V2($aryMain,$arySummary,$bolDebug=false) {
	$aryReturn = array();

	#compare flow, both in terms of step_nm and numerical representation
	#pull flow from summary and re-use. do not calculate anything other than differences
	$aryFlow = array(
		"FLOW"=>array(
			"THIS"=>$arySummary["THIS"]["METADATA"]["FLOW"],
			"THAT"=>$arySummary["THAT"]["METADATA"]["FLOW"]
		),
		"STEP_NM"=>array(
			"THIS"=>$arySummary["THIS"]["METADATA"]["STEP_NM"],
			"THAT"=>$arySummary["THAT"]["METADATA"]["STEP_NM"]
		)
	);

	switch (true) {
		case $aryFlow["FLOW"]["THIS"] > $aryFlow["FLOW"]["THAT"]:
			$aryReturn["FLOW_NUMERIC"]["MORE"] = array(
				"THIS"=>round($aryFlow["FLOW"]["THIS"],3),
				"THAT"=>round($aryFlow["FLOW"]["THAT"],3),
				"DIFF"=>round($aryFlow["FLOW"]["THIS"] - $aryFlow["FLOW"]["THAT"],3)
			);
			break;
		case $aryFlow["FLOW"]["THIS"] < $aryFlow["FLOW"]["THAT"]:
			$aryReturn["FLOW_NUMERIC"]["LESS"] = array(
				"THIS"=>round($aryFlow["FLOW"]["THIS"],3),
				"THAT"=>round($aryFlow["FLOW"]["THAT"],3),
				"DIFF"=>round($aryFlow["FLOW"]["THAT"] - $aryFlow["FLOW"]["THIS"],3)
			);
			break;
	}


	$aryDiff1 = array_diff($aryFlow["STEP_NM"]["THIS"],$aryFlow["STEP_NM"]["THAT"]);
	$aryDiff2 = array_diff($aryFlow["STEP_NM"]["THAT"],$aryFlow["STEP_NM"]["THIS"]);

	if(count($aryDiff1) > 0) {
		$aryReturn["FLOW"]["MORE"][] = array(
			"THIS"=>implode(",",$aryFlow["STEP_NM"]["THIS"]),
			"THAT"=>implode(",",$aryFlow["STEP_NM"]["THAT"]),
			"DIFF"=>$aryDiff1
		);	
	}

	if(count($aryDiff2) > 0) {
		$aryReturn["FLOW"]["LESS"][] = array(
			"THIS"=>implode(",",$aryFlow["STEP_NM"]["THIS"]),
			"THAT"=>implode(",",$aryFlow["STEP_NM"]["THAT"]),
			"DIFF"=>$aryDiff2
		);
	}

	#end flow. everything below is only for when steps line up
	
	#2025-07-03 RM only doing comparison on primary setup. comparing runrates on primary setup for same step
	$aryMap["STEPNM_TT_SEQ"] = array();
	$aryMap["STEPNM_TT_HASHSETUP"] = array();
	foreach(array_keys($aryMain["THIS"]["RTE_SEQ_NUM"]) as $strSeqThis) {
		$strStepName = $aryMain["THIS"]["RTE_SEQ_NUM"][$strSeqThis]["STEP_NM"];

		$bolFound = false;
		foreach(array_keys($aryMain["THAT"]["RTE_SEQ_NUM"]) as $strSeqThat) {
			if($strStepName == $aryMain["THAT"]["RTE_SEQ_NUM"][$strSeqThat]["STEP_NM"]) {
				$bolFound = true;
				break;
			}
		}

		#only proceed if you found at least one step from THAT which has identical STEP_NM as THIS. and yes, i said at least one. 
		if($bolFound === true) {
			$aryMap["STEPNM_TT_SEQ"][$strStepName] = array(
				"THIS"=>$strSeqThis,
				"THAT"=>$strSeqThat
			);

			foreach(array_keys($aryMap["STEPNM_TT_SEQ"][$strStepName]) as $strTT) {
				$strSeq = $aryMap["STEPNM_TT_SEQ"][$strStepName][$strTT];				
				$arySetupTemp = $aryMain[$strTT]["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][1];

				$aryTemp = array(
					"TESTER"=>$arySetupTemp["TESTER"],
					"HANDLER"=>$arySetupTemp["HANDLER"]
				);
				$strHashSetup = sha1(serialize($aryTemp));

				$aryMap["STEPNM_TT_HASHSETUP"][$strStepName][$strTT] = $strHashSetup;
			}
		}
	}


	#compare primary setup for matching step names
	$aryDiff = array();
	foreach($aryMap["STEPNM_TT_HASHSETUP"] as $strStepName => $aryMapTemp) {
		$strHashSetupThis = $aryMapTemp["THIS"];
		$strHashSetupThat = $aryMapTemp["THAT"];

		if($strHashSetupThis != $strHashSetupThat) {
			$strSeqThis = $aryMap["STEPNM_TT_SEQ"][$strStepName]["THIS"];
			$strSeqThat = $aryMap["STEPNM_TT_SEQ"][$strStepName]["THAT"];

			$aryPriSetupThis = $aryMain["THIS"]["RTE_SEQ_NUM"][$strSeqThis]["RES_PRIO_CD"][1];
			$aryPriSetupThat = $aryMain["THAT"]["RTE_SEQ_NUM"][$strSeqThat]["RES_PRIO_CD"][1];

			switch (true) {
				case $aryPriSetupThis["TESTER"] != $aryPriSetupThat["TESTER"] && $aryPriSetupThis["HANDLER"] != $aryPriSetupThat["HANDLER"]:
					$aryDiffTemp = array($aryPriSetupThis["TESTER"],$aryPriSetupThis["HANDLER"]);
					break;
				case $aryPriSetupThis["TESTER"] != $aryPriSetupThat["TESTER"]:
					$aryDiffTemp = array($aryPriSetupThis["TESTER"]);
					break;
				case $aryPriSetupThis["HANDLER"] != $aryPriSetupThat["HANDLER"]:
					$aryDiffTemp = array($aryPriSetupThis["HANDLER"]);
					break;
				default:
					#how would this happen, unless someone is carelessly adding fields to compare?
					$aryDiffTemp = array();
					break;
			}
		
			$aryTemp = array(
				"THIS"=>$aryPriSetupThis["TESTER"]." + ".$aryPriSetupThis["HANDLER"],
				"THAT"=>$aryPriSetupThat["TESTER"]." + ".$aryPriSetupThat["HANDLER"],
				"DIFF"=>implode(", ",$aryDiffTemp)
			);
			$strHashDiff = sha1(serialize($aryTemp));

			if(!isset($aryDiff[$strHashDiff])) {
				$aryTemp["STEP_NM"] = array();
				$aryDiff[$strHashDiff] = $aryTemp;
			}
			$aryDiff[$strHashDiff]["STEP_NM"][] = $strStepName;
		}
	}

	foreach($aryDiff as $strHashDiff => $aryDiffTemp) {
		$aryReturn["PRIMARY_SETUP"]["NEUTRAL"][] = $aryDiffTemp;
	}

	#now compare runrates
	$aryField = array("UTPI","TTPI","INDX","OEE","TD_EFF","WFR_IDX_PCT","UTPH");
	$aryDiff = array();
	foreach(array_keys($aryMap["STEPNM_TT_HASHSETUP"]) as $strStepName) {
		#until i did something wrong, both THIS and THAT will exist as keys
		
		$strSeqThis = $aryMap["STEPNM_TT_SEQ"][$strStepName]["THIS"];
		$strSeqThat = $aryMap["STEPNM_TT_SEQ"][$strStepName]["THAT"];

		$arySetupThis = $aryMain["THIS"]["RTE_SEQ_NUM"][$strSeqThis]["RES_PRIO_CD"][1];
		$arySetupThat = $aryMain["THAT"]["RTE_SEQ_NUM"][$strSeqThat]["RES_PRIO_CD"][1];

		#compare runrate between the primary setups, regardless of whether or not the setup is the same
		foreach($aryField as $strField) {
			if($strField == "UTPH" && $arySetupThat[$strField] == 1) {

			}else{
				$decDiff = $arySetupThis[$strField] - $arySetupThat[$strField];
				$pctDiff = abs($decDiff / $arySetupThat[$strField]);

				if($pctDiff > 0.01) {
					$intKDiff = (int) ($pctDiff * 1000);
					#echo $decDiff." / ".$pctDiff." / ".$intKDiff."<BR>";
					if($arySetupThis[$strField] > $arySetupThat[$strField]) {
						$strML = "MORE";
					}else{
						$strML = "LESS";
					}

					if(!isset($aryDiff[$strField][$strML][$intKDiff])) {
						$aryDiff[$strField][$strML][$intKDiff] = array(
							"THIS"=>$arySetupThis[$strField],
							"THAT"=>$arySetupThat[$strField],
							"DIFF"=>abs(round($decDiff,3)),
							"DIFF_PERC"=>round($pctDiff,3),
							"TESTER_THIS"=>$arySetupThis["TESTER"],
							"HANDLER_THIS"=>$arySetupThis["HANDLER"],
							"STEP_NM"=>array()
						);
					}
					$aryDiff[$strField][$strML][$intKDiff]["STEP_NM"][] = $strStepName;
				}
			}
		}
	}

	#print_r2($aryDiff);
	#die();

	foreach(array_keys($aryDiff) as $strField) {
		#per field, we are only interested in the biggeset MORE/LESS across ALL steps (matching) and ALL setups (matching or not)
		#isolate largest diff within this field and hold onto that one. 
		#report differences in relation to that largest diff, but fold in ALL the steps that had the same MORE/LESS for that field 
		#if you dont like this, you can capture them all separately. but the returned data size may be huge. not really a summary anymore
		foreach(array_keys($aryDiff[$strField]) as $strML) {
			#all step_names for this MORE/LESS regardless of magnitude

			$aryStepName = array();
			foreach($aryDiff[$strField][$strML] as $intKDiff => $aryDiffTemp) {
				foreach($aryDiffTemp["STEP_NM"] as $strStepName) {
					$aryStepName[] = $strStepName;
				}
			}

			krsort($aryDiff[$strField][$strML]);
			foreach($aryDiff[$strField][$strML] as $intKDiff => $aryDiffTemp) {
				$aryReturn[$strField][$strML][] = array(
					"THIS"=>$aryDiffTemp["THIS"],
					"THAT"=>$aryDiffTemp["THAT"],
					"DIFF"=>$aryDiffTemp["DIFF"],
					"DIFF_PERC"=>$aryDiffTemp["DIFF_PERC"],
					"INFO"=>array(
						"TESTER_THIS"=>$aryDiffTemp["TESTER_THIS"],
						"HANDLER_THIS"=>$aryDiffTemp["HANDLER_THIS"],
						"STEP_NM"=>$aryStepName
					)
				);

				#keep only the biggest per field+ML combination
				break;
			}
		}
	}

	#print_r2($aryReturn);
	#die();
	return $aryReturn;
}


function TPI_DATA_GET_V2($iConRLM,$aryNeed,$strInputType,$strInput,$bolDebug=false) {
	if($bolDebug === true) {
		echo "(".implode(",",$aryNeed).") / ".$strInputType." / ".$strInput."<BR>";
	}

	$aryFilter = array();

	#only two types of need_type, DATA or COMPARE. 
	#COMPARE is needed to compute summary, or to compare two TPI_ID against each other


	#2025-07-24 RM added dedication fields to DATA, not to COMPARE 
	$aryMap["NEED_FIELD"] = array(
		"DATA"=>array('MFG_PART_NUM','GENERIC','RTE_SEQ_NUM','STEP_NM','SITE_NUM','RES_AREA','TEMP_C','TEMP_CLASS','RES_SET_ID','RES_PRIO_CD','TESTER','HANDLER','HW_SET_ID','UTPI','TEST_PERC','REF_DOC_ID','REF_STEP_ID','REF_SETUP_ID','TD_EFF','WFR_IDX_PCT','GROSS_DPW','TTPI','INDX','OEE','TD_EFF','WFR_IDX_PCT','UTPH','ATTR_SET_ID','REF_DOC_ID','TESTER_ENG','HANDLER_ENG','IS_DED','DED_IS_PRIMARY','DED_IS_EXCLUSIVE'),
		"COMPARE"=>array('RTE_SEQ_NUM','STEP_NM','SITE_NUM','TEMP_CLASS','TEST_PERC','RES_PRIO_CD','TESTER','HANDLER','UTPI','TTPI','INDX','OEE','TD_EFF','WFR_IDX_PCT','UTPH','REF_DOC_ID')
	);

	$strNeed = "SOME";
	switch (true) {
		case in_array("DATA",$aryNeed):
			$aryField = $aryMap["NEED_FIELD"]["DATA"];
			$strNeed = "ALL";
			break;
		case in_array("DATA_PRI",$aryNeed):
			$aryField = $aryMap["NEED_FIELD"]["DATA"];
			$strNeed = "ALL";
			$aryFilter["RES_PRIO_CD"] = array(1);
			break;
		case in_array("COMPARE",$aryNeed) || in_array("SUMMARY",$aryNeed):
			$aryField = $aryMap["NEED_FIELD"]["COMPARE"];
			$aryFilter["RES_PRIO_CD"] = array(1);
			break;
		default:
			throw new exception("UNEXPECTED NEED_TYPE");
			break;
	}

	
	$aryFilterSQL = array();
	foreach($aryFilter as $strField => $aryValue) {
		$aryTemp = array();
		foreach($aryValue as $strValue) {
			if(is_numeric($strValue)) {
				$aryTemp[] = mysqli_real_escape_string($iConRLM,$strValue);
			}else{
				$aryTemp[] = "'".mysqli_real_escape_string($iConRLM,$strValue)."'";
			}
		}
		if(count($aryTemp) > 0) {
			$aryFilterSQL[] = $strField." IN (".implode(",",$aryTemp).")";
		}
	}

	if(count($aryFilterSQL) == 0) {
		$aryFilterSQL[] = "1 = 1";
	}


	switch ($strInputType) {
		case "SAP_RTE_ID":
			$strSQL = "
				SELECT
				".implode(",",$aryField)."
				FROM BRAIN.V_ADI_AAA
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strInput)."'
				AND ".implode(" AND ",$aryFilterSQL)."
				ORDER BY RTE_SEQ_NUM, RES_PRIO_CD;";
			break;
		case "TPI_ID":
			#2027-07-07 RM added TPD_ID
			$strSQL = "
				SELECT
				".implode(",",$aryField).",TPD_ID
				FROM BRAIN.V_ADI_TPI
				WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$strInput)."
				AND ".implode(" AND ",$aryFilterSQL)."
				ORDER BY RTE_SEQ_NUM, RES_PRIO_CD;";
			break;
		default:
			throw new exception("INVALID INPUT_TYPE FOR ".__FUNCTION__);
			break;
	}

	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		#2025-07-02 RM forcing sequence to string. not sure what happens if someone rounds something then the original value still referred to as key
		$strSeq = (string) number_format($LineMain["RTE_SEQ_NUM"],3);
		$intPri = $LineMain["RES_PRIO_CD"];

		if(!isset($aryReturn["RTE_SEQ_NUM"])) {			
			if($strNeed == "ALL") {
				$aryAdd = array('MFG_PART_NUM','GENERIC');
				if(count(array_diff($aryAdd,$aryField)) > 0) {
					throw new exception("UNDEFINED FIELD SPECIFIED");
				}
				foreach($aryAdd as $strField) {
					$aryReturn[$strField] = $LineMain[$strField];
				}
			}
		}

		if(!isset($aryReturn["RTE_SEQ_NUM"][$strSeq])) {
			$aryReturn["RTE_SEQ_NUM"][$strSeq] = array(
				"STEP_NM"=>$LineMain["STEP_NM"],
				"TEMP_CLASS"=>$LineMain["TEMP_CLASS"],
				"TEST_PERC"=>($LineMain["TEST_PERC"] > 0 && $LineMain["TEST_PERC"] <= 1 ? $LineMain["TEST_PERC"] : 1),
				"RES_PRIO_CD"=>array()
			);

			if($strNeed == "ALL") {
				$aryAdd = array('RES_AREA','TEMP_C');
				if(count(array_diff($aryAdd,$aryField)) > 0) {
					throw new exception("UNDEFINED FIELD SPECIFIED");
				}

				foreach($aryAdd as $strField) {
					$aryReturn["RTE_SEQ_NUM"][$strSeq][$strField] = $LineMain[$strField];
				}
			}
		}

		$aryReturn["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri] = array(
			"TESTER"=>$LineMain["TESTER"],
			"HANDLER"=>$LineMain["HANDLER"],
			"UTPI"=>($LineMain["UTPI"] > 0 ? $LineMain["UTPI"] : 1),
			"TTPI"=>($LineMain["TTPI"] > 0 ? $LineMain["TTPI"] : 5.55),
			"INDX"=>($LineMain["INDX"] > 0 ? $LineMain["INDX"] : 1),
			"OEE"=>($LineMain["OEE"] > 0 && $LineMain["OEE"] <= 1 ? $LineMain["OEE"] : 1),
			"TD_EFF"=>($LineMain["TD_EFF"] > 0 && $LineMain["TD_EFF"] <= 1 ? $LineMain["TD_EFF"] : 1),
			"WFR_IDX_PCT"=>($LineMain["WFR_IDX_PCT"] > 0 && $LineMain["WFR_IDX_PCT"] <= 1 ? $LineMain["WFR_IDX_PCT"] : 1),
			"UTPH"=>($LineMain["UTPH"] > 0 ? $LineMain["UTPH"] : 1)
		);

		if($strNeed == "ALL") {
			$aryAdd = array('RES_SET_ID','HW_SET_ID','REF_STEP_ID','REF_SETUP_ID','GROSS_DPW','ATTR_SET_ID','TESTER_ENG','HANDLER_ENG','IS_DED','DED_IS_PRIMARY','DED_IS_EXCLUSIVE');
			if(count(array_diff($aryAdd,$aryField)) > 0) {
				throw new exception("UNDEFINED FIELD SPECIFIED");
			}

			foreach($aryAdd as $strField) {
				$aryReturn["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri][$strField] = $LineMain[$strField];
			}
		}

		if(isset($LineMain["TPD_ID"])) {
			$aryReturn["RTE_SEQ_NUM"][$strSeq]["RES_PRIO_CD"][$intPri]["TPD_ID"] = $LineMain["TPD_ID"];
		}
	}

	#Print_R2($aryReturn);
	#die();
	return $aryReturn;
}


function TPI_CLONE($iConRLM,$aryInputTemp,$bolDebug=false) {
	#2025-06-26 RM two possible ways: TPI_ID or SAP_RTE_ID. TPI_ID will clone an existing instance, SAP_RTE_ID will clone the EFF_START_DT = NULL entry from adi_alldb_all

	#basic error checking
	switch (true) {
		case !isset($aryInputTemp["EFF_START_DT"]) && !isset($aryInputTemp["FYWW"]):
			throw new exception("MUST HAVE EITHER EFF_START_DT OR FYWW");
			break;
		case (!isset($aryInputTemp["TPI_ID"]) || trim($aryInputTemp["TPI_ID"]) == "") && (!isset($aryInputTemp["SAP_RTE_ID"]) || trim($aryInputTemp["SAP_RTE_ID"]) == ""):
			throw new exception("MUST HAVE EITHER TPI_ID OR SAP_RTE_ID");
			break;
	}
	
	#print_r2($aryInputTemp);
	if(isset($aryInputTemp["EFF_START_DT"])) {
		$strESD = $aryInputTemp["EFF_START_DT"];
		#EFF_START_DT must translate to a SUNDAY, for an FYWW > current week, NOT EQUAL TO and <= 65 weeks away
		$strSQL = "
			SELECT
			MFG_YEAR*100+WEEK_NUMBER FYWW
			FROM shared.DMCLS_MEMORY
			WHERE MFG_DATE = STR_TO_DATE('".mysqli_real_escape_string($iConRLM,$strESD)."','%Y-%m-%d')
			AND DATE_FORMAT(MFG_DATE,'%w') = 0
			HAVING FYWW > 
			(
				SELECT
				MIN(MFG_YEAR*100+WEEK_NUMBER)
				FROM shared.DMCLS_MEMORY
				WHERE MFG_DATE = STR_TO_DATE(NOW(),'%Y-%m-%d')
			)
			AND FYWW <= 
			(
				SELECT
				MIN(MFG_YEAR*100+WEEK_NUMBER)
				FROM shared.DMCLS_MEMORY
				WHERE MFG_DATE = DATE_ADD(STR_TO_DATE(NOW(),'%Y-%m-%d'),INTERVAL 65 WEEK)
			);";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$intFYWW = null;
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intFYWW = $LineMain["FYWW"];
		}
		if(is_null($intFYWW)) {
			throw new exception("EFF_START_DT MUST BE A SUNDAY OF > CURRENT WEEK AND <= 65 WEEKS IN FUTURE");
		}
	}else{
		#guaranteed above to have either EFF_START_DT or FYWW
		$strSQL = "
			SELECT
			MIN(MFG_DATE) MFG_DATE
			FROM shared.DMCLS_MEMORY
			WHERE MFG_YEAR*100+WEEK_NUMBER = '".mysqli_real_escape_string($iConRLM,$aryInputTemp["FYWW"])."'
			AND MFG_YEAR*100+WEEK_NUMBER > 
			(
				SELECT
				MIN(MFG_YEAR*100+WEEK_NUMBER)
				FROM shared.DMCLS_MEMORY
				WHERE MFG_DATE = STR_TO_DATE(NOW(),'%Y-%m-%d')
			)
			AND MFG_YEAR*100+WEEK_NUMBER <= 
			(
				SELECT
				MIN(MFG_YEAR*100+WEEK_NUMBER)
				FROM shared.DMCLS_MEMORY
				WHERE MFG_DATE = DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 65 WEEK),'%Y-%m-%d')
			);";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$strESD = null;
		while  ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strESD = $LineMain["MFG_DATE"];
		}
		if(is_null($strESD)) {
			throw new exception("FYWW MUST BE > CURRENT WEEK AND <= 65 WEEKS IN FUTURE");
		}
	}

	#now process
	switch (true) {
		case isset($aryInputTemp["TPI_ID"]) && trim($aryInputTemp["TPI_ID"]) != "":
			#make sure it exists in both MAIN and DATA
			$strSQL = "
				SELECT DISTINCT
				TPI_ID,
				SAP_RTE_ID
				FROM BRAIN.TP_INSTANCE_MAIN
				WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$aryInputTemp["TPI_ID"]).";";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			$intTPIIDThat = null;
			$arySRI = array();
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intTPIIDThat = $LineMain["TPI_ID"];
				$strSRI = $LineMain["SAP_RTE_ID"];;
				$arySRI[] = $strSRI;
			}

			if(is_null($intTPIIDThat)) {
				throw new exception("INVALID TPI_ID");
			}

			$arySRI = array_unique($arySRI);
			if(count($arySRI) != 1) {
				throw new exception("SAP_RTE_ID COUNT MUST BE EXACTLY 1");
			}

			#make sure there is nothing with this SAP_RTE_ID which also has the same FYWW

			#having SAP_RTE_ID in TP_INSTANCE_MAIN is unnecessary but i guess it comes in handy sometimes
			$strSQL = "
				SELECT DISTINCT
				TPI_ID
				FROM BRAIN.TP_INSTANCE_MAIN
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
				AND EFF_START_DT = '".mysqli_real_escape_string($iConRLM,$strESD)."'
				AND TPI_ID != ".mysqli_real_escape_string($iConRLM,$intTPIIDThat).";";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				throw new exception("TPI_ID ALREADY EXISTS FOR THIS SAP_RTE_ID AND EFF_START_DT");
			}

			#insert new TPI_ID
			$intTPIIDThis = TPI_CREATE($iConRLM,$strSRI,$strESD,$intFYWW,$aryInputTemp["PROCESS_TYPE"],$aryInputTemp["NOTES"],$bolDebug);

			#continue. using almost all fields for now. we can trim later
			$strSQL = "
				INSERT INTO
				BRAIN.TP_INSTANCE_DATA
				(TPI_ID,MFG_PART_NUM,GENERIC,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,ATTR_SET_ID,IS_DED,DED_IS_PRIMARY,DED_IS_EXCLUSIVE)
				SELECT
				".mysqli_real_escape_string($iConRLM,$intTPIIDThis)." AS TPI_ID,
				MFG_PART_NUM,GENERIC,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,ATTR_SET_ID,IS_DED,DED_IS_PRIMARY,DED_IS_EXCLUSIVE
				FROM BRAIN.V_ADI_TPI
				WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIIDThat).";";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);

			#2025-07-24 RM added history system
			TPI_HIST_PUT($iConRLM,$intTPIIDThis,$bolDebug);

			return $intTPIIDThis;

			break;
		case isset($aryInputTemp["SAP_RTE_ID"]):
			#check it exists
			$strSQL = "
				SELECT
				SAP_RTE_ID,
				MFG_PART_NUM
				FROM BRAIN.V_ADI_AAA
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$aryInputTemp["SAP_RTE_ID"])."'
				LIMIT 0,1;";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			$strSRI = null;
			$strMFRPN = null;
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strSRI = $LineMain["SAP_RTE_ID"];
				$strMFRPN = $LineMain["MFG_PART_NUM"];
			}

			if(is_null($strSRI)) {
				throw new exception("INVALID SAP_RTE_ID");
			}

			if(is_null($strMFRPN)) {
				throw new exception("INVALID MFG_PART_NUM");
			}

			#check for already existing instance
			$strSQL = "
				SELECT
				TPI_ID
				FROM BRAIN.TP_INSTANCE_MAIN
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
				AND EFF_START_DT = '".mysqli_real_escape_string($iConRLM,$strESD)."'
				LIMIT 0,1;";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			$intTPIIDThat = null;
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intTPIIDThat = $LineMain["TPI_ID"];
			}

			if(!is_null($intTPIIDThat)) {
				throw new exception("TPI_ID ALREADY EXISTS FOR THIS SAP_RTE_ID + EFF_START_DT");
			}

			#insert new TPI_ID
			$intTPIIDThis = TPI_CREATE($iConRLM,$strSRI,$strESD,$intFYWW,$aryInputTemp["PROCESS_TYPE"],$aryInputTemp["NOTES"],$bolDebug);

			#continue. using almost all fields for now. we can trim later
			#2025-07-09 RM apply overrides before saving to tp_instance_data

			#$bolDebug = true;

			$strSchema = "BRAIN";
			$strTable = "TP_INSTANCE_DATA_TEMPORARY";

			$arySQL = array();

			$arySQL[] = "
				CREATE TEMPORARY TABLE
				".$strSchema.".".$strTable."
				LIKE
				BRAIN.TP_INSTANCE_DATA;";

			$arySQL[] = "
				INSERT INTO
				".$strSchema.".".$strTable."
				(TPI_ID,MFG_PART_NUM,GENERIC,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,TESTER_ENG,HANDLER_ENG,ATTR_SET_ID)
				SELECT
				".mysqli_real_escape_string($iConRLM,$intTPIIDThis)." AS TPI_ID,
				MFG_PART_NUM,GENERIC,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,TESTER_ENG,HANDLER_ENG,ATTR_SET_ID
				FROM BRAIN.V_ADI_AAA
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."';";

			foreach($arySQL as $strSQL) {
				if($bolDebug === true) {
					echo $strSQL."<BR>";
				}
				ExecuteIQuery($strSQL,$iConRLM);
			}

			TPI_DATA_OVERRIDE_APPLY($iConRLM,$strSchema,$strTable,$strMFRPN,$strSRI,$bolDebug);

			TPI_DATA_DEDICATION_APPLY($iConRLM,$strSchema,$strTable,$strMFRPN,$strSRI,$bolDebug);

			$arySQL = array();

			$arySQL[] = "
				INSERT INTO
				BRAIN.TP_INSTANCE_DATA
				(TPI_ID,MFG_PART_NUM,GENERIC,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,TESTER_ENG,HANDLER_ENG,IS_DED,DED_IS_PRIMARY,DED_IS_EXCLUSIVE,ATTR_SET_ID)
				SELECT
				TPI_ID,MFG_PART_NUM,GENERIC,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,TESTER_ENG,HANDLER_ENG,IS_DED,DED_IS_PRIMARY,DED_IS_EXCLUSIVE,ATTR_SET_ID
				FROM ".$strSchema.".".$strTable.";";

			$arySQL[] = "
				DROP TEMPORARY TABLE IF EXISTS
				".$strSchema.".".$strTable.";";

			foreach($arySQL as $strSQL) {
				if($bolDebug === true) {
					echo $strSQL."<BR>";
				}
				ExecuteIQuery($strSQL,$iConRLM);
			}

			#2025-07-24 RM added history system
			TPI_HIST_PUT($iConRLM,$intTPIIDThis,$bolDebug);

			return $intTPIIDThis;

			break;
	}
}


function TPI_DATA_DEDICATION_APPLY($iConRLM,$strSchema,$strTable,$strMFRPN,$strSRI,$bolDebug=false) {

	#need structure first
	$strSQL= "SELECT * FROM ".$strSchema.".".$strTable." LIMIT 0,1;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryField = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryField = array_keys($LineMain);
	}
	if(count($aryField) == 0) {
		throw new exception("NO FIELDS FOR ".$strSchema.".".$strTable);
	}
	#remove TPD_ID ahead of time
	$aryField = array_diff($aryField,array("TPD_ID"));


	$strSQL = "
		SELECT
		APS.SITE_NUM,
		APS.STEP_NM,
		APS.TESTER TESTER,
		APS.HANDLER HANDLER,
		APS.IS_PRIMARY,
		AD.ENGR_TESTER TESTER_ENG,
		AD.ENGR_HANDLER HANDLER_ENG,
		AD.UTPI,
		ROUND(AD.TEST_TIME * AD.UTPI,3) TTPI,
		ROUND(AD.INDEX_TIME * AD.UTPI,3) INDX,
		AD.OEE,
		AD.UPH UTPH,
		AD.PRIO_CD RES_PRIO_CD,
		AD.EXCLUSIVE AS IS_EXCLUSIVE
		FROM TEST.ADI_PRIMARY_SETUP APS
		INNER JOIN TEST.ADI_DEDICATION AD ON APS.DED_ID = AD.DED_ID
		WHERE APS.STATUS = 'COMPLETED'
		#2025-09-19 RM removed this, what does this column indicate?
		#AND (AD.UNPLANNABLE IS NULL OR AD.UNPLANNABLE = '')
		AND APS.MFG_PART_NUM = '".mysqli_real_escape_string($iConRLM,$strMFRPN)."';";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}

	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryRaw = array();
	$aryMap["SITENUM_STEPNM_TE_HE_CTR"] = array();
	$aryMap["SITENUM_STEPNM_PRI_CTR"] = array();
	$aryMap["SITENUM_STEPNM_EXCLUSIVE_CTR"] = array();

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryRaw[] = $LineMain;
		$intCtr = count($aryRaw) - 1;

		$strSiteNum = $LineMain["SITE_NUM"];
		$strStepName = $LineMain["STEP_NM"];
		$strTE = $LineMain["TESTER_ENG"];
		$strHE = $LineMain["HANDLER_ENG"];

		$aryMap["SITENUM_STEPNM_TE_HE_CTR"][$strSiteNum][$strStepName][$strTE][$strHE][] = $intCtr;

		if($LineMain["IS_PRIMARY"] == 1) {
			$aryMap["SITENUM_STEPNM_PRI_CTR"][$strSiteNum][$strStepName][] = $intCtr;
		}

		if($LineMain["IS_EXCLUSIVE"] == 1) {
			$aryMap["SITENUM_STEPNM_EXCLUSIVE_CTR"][$strSiteNum][$strStepName][] = $intCtr;
		}
	}

	#may not have more than one dedication per mfg_part_num + site_num + step_nm + tester_eng + handler_eng
	foreach(array_keys($aryMap["SITENUM_STEPNM_TE_HE_CTR"]) as $strSiteNum) {
		foreach(array_keys($aryMap["SITENUM_STEPNM_TE_HE_CTR"][$strSiteNum]) as $strStepName) {
			#cannot have two dedications both set to primary for the same site_num + step
			if(count($aryMap["SITENUM_STEPNM_PRI_CTR"][$strSiteNum][$strStepName]) > 1) {
				throw new exception("DEDICATION: MORE THAN ONE IS_PRIMARY FOR THE SAME SITE_NUM + STEP_NM");
			}

			
			if(isset($aryMap["SITENUM_STEPNM_EXCLUSIVE_CTR"][$strSiteNum][$strStepName])) {
				if(count($aryMap["SITENUM_STEPNM_EXCLUSIVE_CTR"][$strSiteNum][$strStepName]) > 1) {
					throw new exception("DEDICATION: MORE THAN ONE IS_EXCLUSIVE FOR THE SAME SITE_NUM + STEP_NM");
				}
			}


			$intTPDIDPri = null;
			foreach(array_keys($aryMap["SITENUM_STEPNM_TE_HE_CTR"][$strSiteNum][$strStepName]) as $strTE) {
				foreach($aryMap["SITENUM_STEPNM_TE_HE_CTR"][$strSiteNum][$strStepName][$strTE] as $strHE => $aryMapTemp) {
					foreach($aryMapTemp as $intCtr) {}

					$aryRawTemp = $aryRaw[$intCtr];

					switch (true) {
						case count($aryMapTemp) > 1:
							throw new exception("DEDICATION: MORE THAN ONE DEDICATION EXISTS FOR THE SAME SITE_NUM + STEP_NM + TESTER_ENG + HANDLER_ENG");
							break;
						case $aryRawTemp["IS_PRIMARY"] != 1 && $aryRawTemp["IS_EXCLUSIVE"] == 1:
							throw new exception("DEDICATION: IS_EXCLUSIVE = 1 BUT IS_PRIMARY != 1");
							break;
						case $aryRawTemp["IS_PRIMARY"] == 1 && $aryRawTemp["RES_PRIO_CD"] != 1:
							#2025-07-10 RM actually i dont care because is_primary wins anyway
							break;
					}

					$strTester = $aryRawTemp["TESTER"];
					$strHandler = $aryRawTemp["HANDLER"];

					$strSQL = "
						SELECT
						TPD_ID,
						RES_PRIO_CD,
						TESTER,
						HANDLER
						FROM ".$strSchema.".".$strTable."
						WHERE SITE_NUM = '".mysqli_real_escape_string($iConRLM,$strSiteNum)."'
						AND STEP_NM = '".mysqli_real_escape_string($iConRLM,$strStepName)."'
						AND TESTER_ENG = '".mysqli_real_escape_string($iConRLM,$strTE)."'
						AND HANDLER_ENG = '".mysqli_real_escape_string($iConRLM,$strHE)."'
						ORDER BY RES_PRIO_CD;";
					if($bolDebug === true) {
						echo $strSQL."<BR>";
					}
					$RSMain = ExecuteIQuery($strSQL,$iConRLM);
					$aryTPDID = array();
					$intTPDIDAlreadyDone = null;
					while ($LineMain = mysqli_fetch_assoc($RSMain)) {
						$intTPDID = $LineMain["TPD_ID"];

						$aryTPDID[$intTPDID] = array(
							"TESTER"=>$LineMain["TESTER"],
							"HANDLER"=>$LineMain["HANDLER"]
						);

						switch (true) {
							case !is_null($intTPDIDAlreadyDone):
								#already found, dont check
								break;
							case $LineMain["TESTER"] != $strTester || $LineMain["HANDLER"] != $strHandler:
								break;
							default:
								$intTPDIDAlreadyDone = $intTPDID;
								break;
						}
					}

					if($intTPDIDAlreadyDone !== null) {
						#2025-07-10 RM tester_atom + handler_atom already exists, set the FIRST match to PRI = 1 if appropriate
					
						$intTPDIDThis = $intTPDIDAlreadyDone;
					} else {
						#2025-07-10 RM if tester_atom and handler_atom does not already exist, insert it
						#remember you cannot insert from a temporary table into itself
						#already sorted by RES_PRIO_CD, so use the first found and duplicate that one

						foreach(array_keys($aryTPDID) as $intTPDIDThat) {
							break;
						}
					
						$arySQL = array();

						$arySQL[] = "
							DROP TEMPORARY TABLE IF EXISTS
							TEST.SUPPLEMENT_TEMPORARY;";

						$arySQL[] = "
							CREATE TEMPORARY TABLE
							TEST.SUPPLEMENT_TEMPORARY
							LIKE ".$strSchema.".".$strTable.";";

						$arySQL[] = "
							INSERT INTO
							TEST.SUPPLEMENT_TEMPORARY
							(".implode(",",$aryField).")
							SELECT
							".implode(",",$aryField)."
							FROM ".$strSchema.".".$strTable."
							WHERE TPD_ID = ".mysqli_real_escape_string($iConRLM,$intTPDIDThat).";";
						
						$arySQL[] = "
							UPDATE
							TEST.SUPPLEMENT_TEMPORARY
							SET TESTER = '".mysqli_real_escape_string($iConRLM,$strTester)."',
							HANDLER = '".mysqli_real_escape_string($iConRLM,$strHandler)."';";

						$arySQL[] = "
							INSERT INTO
							".$strSchema.".".$strTable."
							(".implode(",",$aryField).")
							SELECT
							".implode(",",$aryField)."
							FROM TEST.SUPPLEMENT_TEMPORARY;";
						
						$arySQL[] = "
							DROP TEMPORARY TABLE IF EXISTS
							TEST.SUPPLEMENT_TEMPORARY;";

						foreach($arySQL as $strSQL) {
							if($bolDebug === true) {
								echo $strSQL."<BR>";
							}
							ExecuteIQuery($strSQL,$iConRLM);
						}

						#guaranteed one insert only. add the new inserted TPD_ID into the list
						$strSQL = "
							SELECT MAX(TPD_ID) TPD_ID
							FROM ".$strSchema.".".$strTable.";";
						$RSMain = ExecuteIQuery($strSQL,$iConRLM);
						while ($LineMain = mysqli_fetch_assoc($RSMain)) {
							$intTPDIDThis = $LineMain["TPD_ID"];
						}

						if($bolDebug === true) {
							echo "INSERTED: ".$intTPDIDThis."<BR>";
						}
						#this is not actually necessary but it helps for debugging to see everything within this tester_eng + handler_eng in this site_num + step_nm
						$aryTPDID[$intTPDIDThis] = array(
							"TESTER"=>$strTester,
							"HANDLER"=>$strHandler
						);
					}

					if($aryRawTemp["IS_PRIMARY"] == 1) {
						$intTPDIDPri = $intTPDIDThis;
					}


					#update this record (existing or just inserted) to have data exactly equal to saved dedication data
					$aryUpdate = array(
						"UTPI = ".mysqli_real_escape_string($iConRLM,$aryRawTemp["UTPI"]),
						"TTPI = ".mysqli_real_escape_string($iConRLM,$aryRawTemp["TTPI"]),
						"INDX = ".mysqli_real_escape_string($iConRLM,$aryRawTemp["INDX"]),
						"OEE = ".mysqli_real_escape_string($iConRLM,$aryRawTemp["OEE"]),
						"UTPH = ".mysqli_real_escape_string($iConRLM,$aryRawTemp["UTPH"]),
						"IS_DED = 1"
					);

					if($aryRawTemp["IS_PRIMARY"] == 1) {
						$aryUpdate[] = "DED_IS_PRIMARY = 1";
					}

					if($aryRawTemp["IS_EXCLUSIVE"] == 1) {
						$aryUpdate[] = "DED_IS_EXCLUSIVE = 1";
					}

					$strSQL = "
						UPDATE ".$strSchema.".".$strTable."
						SET ".implode(", ",$aryUpdate)."						
						WHERE TPD_ID = ".mysqli_real_escape_string($iConRLM,$intTPDIDThis).";";
					if($bolDebug === true) {
						echo $strSQL."<BR>";
					}
					ExecuteIQuery($strSQL,$iConRLM);
				}
			}

			#now re-calculate PRI across ALL setups for this site_num + step_nm
			#i only respect ONE primary, everything else gets re-sorted according to original sequence
			#collect what needs to be changed and update only those
			$aryPri = array();

			$intCtrOld = 1;
			$intCtrNew = 1;
			if(!is_null($intTPDIDPri)) {
				$aryPri[$intTPDIDPri]["NEW"] = $intCtrNew;
				++$intCtrNew;
			}

			$strSQL = "
				SELECT
				TPD_ID
				FROM ".$strSchema.".".$strTable."
				WHERE SITE_NUM = '".mysqli_real_escape_string($iConRLM,$strSiteNum)."'
				AND STEP_NM = '".mysqli_real_escape_string($iConRLM,$strStepName)."'
				ORDER BY RES_PRIO_CD;";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intTPDID = $LineMain["TPD_ID"];
				$aryPri[$intTPDID]["OLD"] = $intCtrOld;
				++$intCtrOld;

				if(!isset($aryPri[$intTPDID]["NEW"])) {
					$aryPri[$intTPDID]["NEW"] = $intCtrNew;
					++$intCtrNew;
				}
			}

			#check if we even need to update anything
			$aryDiff = array();
			foreach($aryPri as $intTPDID => $aryPriTemp) {
				switch (true) {
					case !isset($aryPriTemp["OLD"]):
						$aryDiff[] = $intTPDID;
						break;
					case $aryPriTemp["NEW"] != $aryPriTemp["OLD"]:
						$aryDiff[] = $intTPDID;
						break;
				}
			}

			foreach($aryDiff as $intTPDID) {
				$strSQL = "
					UPDATE
					".$strSchema.".".$strTable."
					SET RES_PRIO_CD = ".mysqli_real_escape_string($iConRLM,$aryPri[$intTPDID]["NEW"])."
					WHERE TPD_ID = ".mysqli_real_escape_string($iConRLM,$intTPDID).";";
				if($bolDebug === true) {
					echo $strSQL."<BR>";
				}
				ExecuteIQuery($strSQL,$iConRLM);
			}
		}
	}


}


function TPI_DATA_OVERRIDE_APPLY($iConRLM,$strSchema,$strTable,$strMFRPN,$strSRI,$bolDebug=false) {
	$aryReturn = array();

	#2025-07-10 RM unplannable setups
	$strSQL = "
		SELECT
		STEP_NM,
		RES_SET_ID
		FROM TEST.ADI_UNPLANNABLE_SETUP
		WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."';";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$arySQL = array();
	#2025-07-11 RM NOT ALLOWED to set primary setup to unplannable
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$arySQL[] = "
			UPDATE
			".$strSchema.".".$strTable."
			SET RES_PRIO_CD = 99
			WHERE RES_PRIO_CD > 1
			AND RES_SET_ID = ".mysqli_real_escape_string($iConRLM,$LineMain["RES_SET_ID"]).";";
	}

	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		ExecuteIQuery($strSQL,$iConRLM);
	}

	
	#2025-07-10 RM now OEE overrides
	$strSQL = "
		SELECT
		SITE_NUM,
		TESTER,
		HANDLER,
		TEMP_CLASS,
		TPD_ID
		FROM ".$strSchema.".".$strTable."
		ORDER BY SITE_NUM;";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMain = array();
	$aryChecked = array();
	$aryUpdate = array();

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strSN = $LineMain["SITE_NUM"];
		$strTester = $LineMain["TESTER"];
		$strHandler = $LineMain["HANDLER"];
		$strTC = $LineMain["TEMP_CLASS"];
		$intTPDID = $LineMain["TPD_ID"];

		$aryMain[$strSN]["TESTER"][$strTester]["HANDLER"][$strHandler]["TEMP_CLASS"][$strTC]["TPD_ID"][] = $intTPDID;
		$aryMain[$strSN]["TESTER"][$strTester]["HANDLER"][$strHandler]["TPD_ID"][] = $intTPDID;
		$aryMain[$strSN]["TESTER"][$strTester]["TEMP_CLASS"][$strTC]["TPD_ID"][] = $intTPDID;
		$aryMain[$strSN]["TESTER"][$strTester]["TPD_ID"][] = $intTPDID;
		$aryMain[$strSN]["HANDLER"][$strHandler]["TEMP_CLASS"][$strTC]["TPD_ID"][] = $intTPDID;
		$aryMain[$strSN]["HANDLER"][$strHandler]["TPD_ID"][] = $intTPDID;
	}

	$aryTempSN = array();
	foreach(array_keys($aryMain) as $strSN) {
		$aryTempSN[] = mysqli_real_escape_string($iConRLM,$strSN);
	}

	$arySQL = array();		
	if(count($aryTempSN) > 0) {
		$arySQL[] = "
			SELECT
			SITE_NUM,
			TESTER,
			HANDLER,
			TEMP_CLASS,
			OEE_VAL
			FROM BRAIN.OEE_OVERRIDE_MAIN OOM
			INNER JOIN BRAIN.OEE_OVERRIDE_PART OOP ON OOM.OEEO_ID = OOP.OEEO_ID
			WHERE SITE_NUM IN ('".implode("','",$aryTempSN)."')
			AND MFG_PART_NUM = '".mysqli_real_escape_string($iConRLM,$strMFRPN)."';";

		$arySQL[] = "
			SELECT
			SITE_NUM,
			TESTER,
			HANDLER,
			TEMP_CLASS,
			OEE_VAL
			FROM BRAIN.OEE_OVERRIDE_MAIN OOM
			LEFT JOIN BRAIN.OEE_OVERRIDE_PART OOP ON OOM.OEEO_ID = OOP.OEEO_ID
			WHERE OOP.OEEO_ID IS NULL
			AND SITE_NUM IN ('".implode("','",$aryTempSN)."');";
	}

	if($bolDebug === true) {
		print_r2($aryMain);
	}
	$aryUpdate = array();
	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);

		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSN = trim($LineMain["SITE_NUM"]);
			$strTester = trim($LineMain["TESTER"]);
			$strHandler = trim($LineMain["HANDLER"]);
			$strTC = trim($LineMain["TEMP_CLASS"]);
			$decOEE = $LineMain["OEE_VAL"];

			if($bolDebug === true) {
				echo $strSN." / ".$strTester." / ".$strHandler." / ".$strTC." / ".$decOEE."<BR>";
			}
			switch (true) {
				case is_null($decOEE) || trim($decOEE) == "" || $decOEE <= 0 || $decOEE > 1:
					throw new exception("OEE OVERRIDE: INVALID OEE_VAL");
					break;
				case $strSN == "":
					throw new exception("OEE OVERRIDE: SITE_NUM CANNOT BE NULL");
					break;
				case $strTester != "" && $strHandler != "" && $strTC != "":
					if(isset($aryMain[$strSN]["TESTER"][$strTester]["HANDLER"][$strHandler]["TEMP_CLASS"][$strTC])) {
						foreach($aryMain[$strSN]["TESTER"][$strTester]["HANDLER"][$strHandler]["TEMP_CLASS"][$strTC]["TPD_ID"] as $intTPDID) {
							if(!isset($aryUpdate[$intTPDID])) {
								$aryUpdate[$intTPDID] = $decOEE;
							}
						}
					}
					break;
				case $strTester != "" && $strHandler != "":
					if(isset($aryMain[$strSN]["TESTER"][$strTester]["HANDLER"][$strHandler])) {
						foreach($aryMain[$strSN]["TESTER"][$strTester]["HANDLER"][$strHandler]["TPD_ID"] as $intTPDID) {
							if(!isset($aryUpdate[$intTPDID])) {
								$aryUpdate[$intTPDID] = $decOEE;
							}
						}
					}
					break;
				case $strTester != "" && $strTC != "":
					if(isset($aryMain[$strSN]["TESTER"][$strTester]["TEMP_CLASS"][$strTC])) {
						foreach($aryMain[$strSN]["TESTER"][$strTester]["TEMP_CLASS"][$strTC]["TPD_ID"] as $intTPDID) {
							if(!isset($aryUpdate[$intTPDID])) {
								$aryUpdate[$intTPDID] = $decOEE;
							}
						}
					}
					break;
				case $strTester != "":
					if(isset($aryMain[$strSN]["TESTER"][$strTester])) {
						foreach($aryMain[$strSN]["TESTER"][$strTester]["TPD_ID"] as $intTPDID) {
							if(!isset($aryUpdate[$intTPDID])) {
								$aryUpdate[$intTPDID] = $decOEE;
							}
						}
					}
					break;
				case $strHandler != "" && $strTC != "":
					if(isset($aryMain[$strSN]["HANDLER"][$strHandler]["TEMP_CLASS"][$strTC])) {
						foreach($aryMain[$strSN]["HANDLER"][$strHandler]["TEMP_CLASS"][$strTC]["TPD_ID"] as $intTPDID) {
							if(!isset($aryUpdate[$intTPDID])) {
								$aryUpdate[$intTPDID] = $decOEE;
							}
						}
					}
					break;
				case $strHandler != "":
					if(isset($aryMain[$strSN]["HANDLER"][$strHandler]["TPD_ID"])) {
						foreach($aryMain[$strSN]["HANDLER"][$strHandler]["TPD_ID"] as $intTPDID) {
							if(!isset($aryUpdate[$intTPDID])) {
								$aryUpdate[$intTPDID] = $decOEE;
							}
						}
					}
					break;
			}
		}
	}



	$aryMap["OEE_TPDID"] = array();
	foreach($aryUpdate as $intTPDID => $decOEE) {
		$strOEE = (string) round($decOEE,3);
		$aryMap["OEE_TPDID"][$strOEE][] = $intTPDID;
	}

	foreach(array_keys($aryMap["OEE_TPDID"]) as $strOEE) {
		$aryTemp = array();
		foreach($aryMap["OEE_TPDID"][$strOEE] as $intTPDID) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$intTPDID);
		}
		if(count($aryTemp) > 0) {
			$strSQL = "
				UPDATE
				".$strSchema.".".$strTable."
				SET OEE = ".mysqli_real_escape_string($iConRLM,$strOEE)."
				WHERE TPD_ID IN (".implode(",",$aryTemp).");";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);
		}
	}	
}


function TPI_CREATE($iConRLM,$strSRI,$strESD,$intFYWW,$strPT,$strNotes,$bolDebug=false) {
	$aryTemp = array(
		"SAP_RTE_ID"=>$strSRI,
		"EFF_START_DT"=>$strESD,
		"FYWW"=>$intFYWW,
		"PROCESS_TYPE"=>$strPT,
		"NOTES"=>$strNotes
	);
	foreach($aryTemp as $key => $value) {
		if(trim($value) != "") {
			$aryTemp[$key] = "'".mysqli_real_escape_string($iConRLM,$value)."'";
		}else{
			$aryTemp[$key] = "NULL";
		}
	}

	$strSQL = "
		INSERT INTO
		BRAIN.TP_INSTANCE_MAIN
		(SAP_RTE_ID,EFF_START_DT,FYWW,PROCESS_TYPE,NOTES)
		VALUES
		(".implode(",",$aryTemp).");";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	ExecuteIQuery($strSQL,$iConRLM);

	$intTPIID = mysqli_insert_id($iConRLM);

	$strSQL = "
		SELECT IFNULL(MAX(INSTANCE),0) + 1 INSTANCE
		FROM BRAIN.TP_INSTANCE_MAIN
		WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
		AND TPI_ID != ".mysqli_real_escape_string($iConRLM,$intTPIID).";";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$intInstance = null;
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intInstance = $LineMain["INSTANCE"];
	}

	$strSQL = "
		UPDATE
		BRAIN.TP_INSTANCE_MAIN
		SET INSTANCE = ".mysqli_real_escape_string($iConRLM,$intInstance)."
		WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIID).";";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	ExecuteIQuery($strSQL,$iConRLM);

	return $intTPIID;
}


function RES_GET($iConRLM,$strInputType,$aryInput,$bolDebug=false) {
	$aryMap = array(
		"INPUT_PRSID"=>array(),
		"RSID_RAID"=>array(),
		"ASID_RAID"=>array(),
		"HWSID_RSID"=>array()
	);
	
	$aryPRS = array();
	$aryRA = array();
	$aryBOR = array();	
	$aryAS = array();
	$aryHWS = array();

	#break down to PRS_ID for a common starting point(if not already starting from there)
	if($strInputType != "PRS_ID") {
		while ($strInput = array_pop($aryInput)) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$strInput);
			if(count($aryTemp) >= 100 || count($aryInput) == 0) {
				$strSQL = "
					SELECT
					PRS_ID,
					MFG_PART_NUM,
					SAP_RTE_ID,
					EFF_START_DT,
					EFF_END_DT,
					RTE_SEQ_NUM,
					TEMP_C
					FROM BRAIN.PROD_RES_RELATION PRR
					INNER JOIN BRAIN.RES_ALTERNATES RA ON PRR.PRS_ID = RA.PRS_ID
					WHERE PRR.".$strInputType." IN ('".implode("','",$aryTemp)."';";
				$RSMain = ExecuteIQuery($strSQL,$iConRLM);
				while ($LineMain = mysqli_fetch_assoc($RSMain)) {
					$strInput = $LineMain[$strInputType];
					$intPRSID = $LineMain["PRS_ID"];
					$aryTemp = $LineMain;
					unset($aryTemp["PRS_ID"]);
					$aryPRS[$intPRSID] = $aryTemp;

					$aryMap["INPUT_PRSID"][$strInput][] = $intPRSID;
				}
			}
		}
	}

	$aryPRSID = array_keys($aryPRS);
	$aryTemp = array();
	while ($intPRSID = array_pop($aryPRSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intPRSID);

		if(count($aryTemp) >= 100 || count($aryInput) == 0) {
			$strSQL = "
				SELECT
				RA_ID,
				PRS_ID,
				RES_SET_ID,
				PRIO_CD
				FROM BRAIN.RES_ALTERNATES
				WHERE PRS_ID IN (".implode(",",$aryTemp).")
				ORDER BY PRS_ID, PRIO_CD, RA_ID;";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intRAID = $LineMain["RA_ID"];
				$intRSID = $LineMain["RES_SET_ID"];
				$intASID = $LineMain["ATTR_SET_ID"];

				#dont forget to map it back to input
				$aryRA[$intRAID] = array(
					"RES_SET_ID"=>$LineMain["RES_SET_ID"],
					"ATTR_SET_ID"=>$LineMain["ATTR_SET_ID"],
					"PRIO_CD"=>$LineMain["PRIO_CD"]
				);

				$aryMap["RSID_RAID"][$intRSID][] = $intRAID;
				$aryMap["ASID_RAID"][$intASID][] = $intRAID;
			}
		}
	}


	$aryField = array("UTPI","TTPI","INDX","TD_EFF","WFR_IDX_PCT","GROSS_DPW","OEE","SPRINT_UTPH","UTPH");

	$aryASID = array();
	$aryTemp = array();
	while ($intASID = array_pop($aryASID)) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intASID);
		if(count($aryTemp) > 100 || count($aryASID) == 0) {
			$strSQL = "
				SELECT
				ATTR_SET_ID,
				ATTR_NM,
				ATTR_VAL_NUM,
				ATTR_VAL_CHAR
				FROM BRAIN.RESOURCE_ATTR
				WHERE ATTR_SET_ID IN (".implode(",",$aryTemp).");";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intASID = $LineMain["ATTR_SET_ID"];
				$strField = $LineMain["ATTR_NM"];

				switch (true) {
					case !in_array($strField,$aryField):
						break;
					case !is_null($LineMain["ATTR_VAL_NUM"]):
						$aryAS[$intASID][$strField] = $LineMain["ATTR_VAL_NUM"];
						break;
					case !is_null($LineMain["ATTR_VAL_CHAR"]):
						$aryAS[$intASID][$strFIeld] = $LineMain["ATTR_VAL_CHAR"];
						break;
				}
			}
		}
	}


	$aryRSID = array_keys($aryMap["RSID_RAID"]);
	$aryTemp = array();
	while ($intRSID = array_pop($aryRSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intRSID);

		if(count($aryTemp) > 100 || count($aryRSID) == 0) {
			$strSQL = "
				SELECT
				RES_SET_ID,
				RES_TYPE,
				RES_NM,
				QUANTITY_REQUIRED
				FROM BRAIN.BILL_OF_RESOURCES
				WHERE RES_SET_ID IN (".implode(",",$aryTemp).")
				ORDER BY RES_SET_ID, RES_TYPE, RES_NM;";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intRSID = $LineMain["RES_RES_SET_ID"];
				$strType = $LineMain["RES_TYPE"];
				$strName = $LineMain["RES_NM"];

				$aryBOR[$intRSID][$strType][$strName] = array("QUANTITY_REQUIRED"=>$LineMain["QUANTITY_REQUIRED"]);

				if($strType == "HARDWARE_SET") {
					$aryMap["HWSID_RSID"][$intHWSID][] = $intRSID;
				}
			}
		}
	}

	$aryHWSID = array_keys($aryMap["HWSID_RSID"]);
	$aryTemp = array();
	while ($intHWSID = array_pop($aryHWSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intHWSID);

		if(count($aryTemp) > 100 || count($aryHWSID) == 0) {
			$strSQL = "
				SELECT
				HW_SET_ID,
				HW_TYPE,
				HW_NM,
				QUANTITY_REQUIRED,
				1 IS_PRIMARY
				FROM BRAIN.HARDWARE_SET
				WHERE HW_SET_ID IN (".implode(",",$aryTemp).")
				
				UNION
				
				SELECT
				HW_SET_ID,
				HW_TYPE,
				HW_NM,
				QUANTITY_REQUIRED,
				0 IS_PRIMARY
				FROM BRAIN.HARDWARE_ALTERNATES
				WHERE HW_SET_ID IN (".implode(",",$aryTemp).");";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intHWSID = $LineMain["HW_SET_ID"];
				$strType = $LineMain["HW_TYPE"];
				$strName = $LineMain["HW_NM"];

				$aryHWS[$intHWSID][$strType][$strName] = array(
					"QUANTITY_REQUIRED"=>$LineMain["QUANTITY_REQUIRED"],
					"IS_PRIMARY"=>$LineMain["IS_PRIMARY"]
				);
			}			
		}
	}

	return $aryData;
}


function REQUEST_VALIDATE($aryIO,$aryRequest) {
	switch (true) {
		case !isset($aryRequest["INPUT_TYPE"]) || $aryRequest["INPUT_TYPE"] == "":
			break;
		case !isset($aryRequest["OUTPUT_TYPE"]) || $aryRequest["OUTPUT_TYPE"] == "":
			break;
		case !isset($aryRequest["INPUT"]) || $aryRequest["INPUT"] == "":
			break;
		default:
			$strInputType 	= $aryRequest["INPUT_TYPE"];
			$strOutputType 	= $aryRequest["OUTPUT_TYPE"];
			$strInput 		= $aryRequest["INPUT"];

			foreach(array_keys($aryIO) as $keyCtrIO) {
				$aryInputType = $aryIO[$keyCtrIO]["INPUT_TYPE"];
				$aryOutputType = $aryIO[$keyCtrIO]["OUTPUT_TYPE"];

				switch (true) {
					case !in_array($strInputType,$aryInputType):
						break;
					case !in_array($strOutputType,$aryOutputType):
						break;
					default:
						return true;
						break;
				}
			}
			break;
	}

	return false;
}


function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}