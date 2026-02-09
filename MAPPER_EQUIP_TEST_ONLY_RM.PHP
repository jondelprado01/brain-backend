<?PHP
#2020-10-27 RM force no caching
header('Access-Control-Allow-Origin: *'); //FIX FOR CORS ERROR IN AJAX - JON 6/18/2025 (DEV & TESTING ONLY)
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("./EQUIP_FUNCTIONS.PHP");
ini_set("error_reporting",E_ALL);


$aryRequest = $_REQUEST;

$bolDebug = false;
if(isset($aryRequest["DEBUG"]) && $aryRequest["DEBUG"] != "") {
	$bolDebug=true;
}

$bolJSON = true;
if(isset($aryRequest["NO_JSON"]) && $aryRequest["NO_JSON"] != "") {
	$bolJSON = false;
}

$bolTable = false;
if(isset($aryRequest["TABLE"]) && ($aryRequest["TABLE"]) != "") {
	$bolTable = true;
}

$bolRawData = false;
if(isset($aryRequest["INPUT_TYPE"])) {
	$aryRequest = $_REQUEST;
}else{
	$jsnPayload = file_get_contents('php://input');
	$aryRequest = json_decode($jsnPayload,true);
	$bolRawData = true;
}



$aryIO = array();
$aryIO[] = array("INPUT_TYPE"=>array("JSON"),"OUTPUT_TYPE"=>array("SYSTEM_USER_ADD","SYSTEM_USER_DEL"));
$aryIO[] = array("INPUT_TYPE"=>array("JSON"),"OUTPUT_TYPE"=>array("CONFIG","CONFIG_DATA","CONFIG_CREATE","RES_NM","ENG_ATOM"));


$bolOK = REQUEST_VALIDATE($aryIO,$aryRequest);
#Print_R2($aryRequest);

if($bolDebug === true) {
	Print_R2($aryRequest);
}

/*
$aryInput = array();
$aryInput[] = array(
	"SITE_NUM"=>array("SUB-TH1","ADTH"),
	"RESOURCE_TYPE"=>array("TESTER")
);
echo json_encode($aryInput)."<BR>";
echo $aryRequest["INPUT"]."<BR>";
*/


if($bolOK === true) {
	$strOutputType = $aryRequest["OUTPUT_TYPE"];
	$strInputType = $aryRequest["INPUT_TYPE"];



	#2025-09-18 RM applying learnings from MAPPER_BRAIN_006 for raw payload vs url arguments
	if($strInputType == "JSON") {
		try {
			$aryInput = json_decode($aryRequest["INPUT"],true);
			if(JSON_LAST_ERROR() !== JSON_ERROR_NONE) {
				throw new exception("invalid JSON input, ".JSON_LAST_ERROR());
			}
		}catch (exception $e) {
			$strMessage = $e->getMessage();
			$strStatus = "FAILURE";
			$aryData 	= array();
		}
	}else{
		$aryInput = $aryRequest["INPUT"];
	}

	#print_r2($aryInput);
	#die('hello');
	#print_R2($aryRequest);
	
	switch (true) {
		case $strOutputType == "CONFIG_CREATE":
			$iConEquip = Open_ILink("TESTOPS");
			#$iConEquip = Open_ILink("localhost");
			
			#Print_R2($aryInput);
			#die();
			
			$strSQL = "SET AUTOCOMMIT=0;";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConEquip);

			$aryData = array();
			try{
				foreach($aryInput as $intCtr => $aryInputTemp) {
					$aryData[$intCtr] = CONFIG_CREATE($iConEquip,$aryInputTemp,$strOutputType,$bolDebug);
				}

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConEquip);

			break;

		case $strOutputType == "CONFIG" || $strOutputType == "CONFIG_DATA":
			$iConEquip = Open_ILink("TESTOPS");

			$aryData = array();
			try{
				foreach($aryInput as $intCtr => $aryInputTemp) {
					$aryData[$intCtr] = EQUIP_GET_V2($iConEquip,$aryInputTemp,$strOutputType,$bolDebug);
				}

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConEquip);

			break;
		case $strOutputType == "ENG_ATOM" || $strOutputType == "RES_NM":
			#2025-07-01 RM
			$iConEquip = Open_ILink("TESTOPS");

			$aryData = array();
			try{

				$bolRefresh = CONFIGID_TO_SITENUM_RESNM_REQUIRES_REFRESH($iConEquip,$bolDebug);

				if($bolRefresh === true) {
					CONFIGID_TO_SITENUM_RESNM_REFRESH_DO($iConEquip,$bolDebug);
				}

				#allowable filters: SITE_NUM, RESOURCE_TYPE, RES_AREA. thats it
				foreach($aryInput as $intCtr => $aryInputTemp) {
					switch ($strOutputType) {
						case "RES_NM":
							$aryData[$intCtr] = ATOM_GET($iConEquip,$aryInputTemp,$strOutputType,$bolDebug);
							break;
						case "ENG_ATOM":
							$aryData[$intCtr] = ENG_ATOM_GET($iConEquip,$aryInputTemp,$strOutputType,$bolDebug);
							break;
					}
				}

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConEquip);

			break;
		
		#2023-07-10 RM
		case $strOutputType == "SYSTEM_USER_ADD" || $strOutputType == "SYSTEM_USER_DEL":
			$aryServer = array(
				"TESTOPS"=>array("DELINQUENT_SCHEDULE"),
				"TESTWEBSVR"=>array("PNEDITOR")
			);
			$aryServerRMap = array();
			foreach(array_keys($aryServer) as $strServer) {
				foreach($aryServer[$strServer] as $strSystem) {
					$aryServerRMap[$strSystem] = $strServer;
				}
			}

			try {
				$aryInput = json_decode($aryRequest["INPUT"],true);
				if(JSON_LAST_ERROR() !== JSON_ERROR_NONE) {
					throw new exception("invalid JSON input, ".JSON_LAST_ERROR());
				}
			}catch (exception $e) {
				$strMessage = $e->getMessage();
				$strStatus = "FAILURE";
				$aryData 	= array();

			}


			$iConWD = Open_ILink("TESTWEB2");

			$strSQL = "call personnel.CHECK_V_WORKDAY_MEMORY();";
			ExecuteIQuery($strSQL,$iConWD);

			$aryData = array();
			foreach($aryInput as $intCtr => $aryInputTemp) {
				try{
					if(!is_array($aryInputTemp)) {
						throw new exception("INVALID SYSTEM_USER INPUT");
					}

					switch (true) {
						case !isset($aryInputTemp["EMAIL_ADDRESS"]) || trim($aryInputTemp["EMAIL_ADDRESS"]) == "":
							throw new exception("missing EMAIL_ADDRESS");
							break;
						case !isset($aryInputTemp["SYSTEM_NAME"]) || trim($aryInputTemp["SYSTEM_NAME"]) == "":
							throw new exception("missing SYSTEM_NAME");
							break;
						case !isset($aryServerRMap[$aryInputTemp["SYSTEM_NAME"]]):
							throw new exception("SERVER for SYSTEM_NAME ".$aryInputTemp["SYSTEM_NAME"]." undefined");
							break;
					}

					$strEA = $aryInputTemp["EMAIL_ADDRESS"];
					$strSN = $aryInputTemp["SYSTEM_NAME"];

					$iConUser = Open_ILink($aryServerRMap[$aryInputTemp["SYSTEM_NAME"]]);

					SYSTEM_USER_DO($iConUser,$iConWD,$strOutputType,$strSN,$strEA,$bolDebug);

					mysqli_close($iConUser);

					$strStatus = "SUCCESS";
					$strMessage = "";
					$aryData[$intCtr] = array();
				}catch (exception $e) {
					$strMessage 		= $e->getMessage();
					$strStatus 			= "FAILURE";
					$aryData[$intCtr] 	= array();
				}
			}

			mysqli_close($iConWD);


			break;
		
		default:
			$strStatus = "FAILURE";
			$strMessage = "UNDEFINED ACTION";
			$aryData = Array();
	}
}else{
	$strStatus 	= "FAILURE";
	$strMessage = "INVALID REQUEST";
	$aryData 	= array();
}


$aryResponse 			= array();
$aryResponse["STATUS"] 	= $strStatus;
$aryResponse["MESSAGE"] = $strMessage;
$aryResponse["DATA"] 	= $aryData;

#print_r2($aryResponse);
#Print_R2($aryData);


switch (true) {

	case $bolTable === true:
		foreach(array_keys($aryData) as $intData) {
			$aryField = array();


			foreach($aryData[$intData] as $key => $aryDataTemp) {
				$aryField = array_keys($aryDataTemp);
				break;
			}

			echo "<TABLE BORDER=1 CELLSPACING=0 CELLPADDING=1>";
			echo "<THEAD><TR><TH>KEY</TH><TH>".implode("</TH><TH>",$aryField)."</TH>";

			foreach($aryData[$intData] as $key => $aryDataTemp) {
				echo "<TR>";
				echo "<TD>".$key."</TD>";
				foreach($aryField as $strField) {
					if(isset($aryDataTemp[$strField])) {
						echo "<TD>".$aryDataTemp[$strField]."</TD>";
					}
				}
				echo "</TR>";
			}

			echo "</TABLE>";
		}

		break;
	case $bolJSON === true:

		$jsnResponse = json_encode($aryResponse);
		if(json_last_error() == JSON_ERROR_NONE) {
			echo $jsnResponse;
		}else{
			echo json_encode(
				array(
					"STATUS"=>"FAILURE",
					"MESSAGE"=>"INVALID JSON OUTPUT",
					"DATA"=>array()
				)
			);
		}
		break;		
	default:
		Print_R2($aryResponse);
		break;
}


function CONFIGID_TO_SITENUM_RESNM_REFRESH_DO($iConEquip,$bolDebug=false) {
	$strSQL = "
		SELECT
		DATE_ADD(
			GREATEST(
				MAX(CL.RECORD_TIMESTAMP),
				MAX(RXM.RECORD_TIMESTAMP)
			),
			INTERVAL 1 SECOND
		) MAX_TIMESTAMP_PLUS_ONE
		FROM EQUIP.CONFIG_LIST CL,
		EQUIP.RX_MASTER RXM;";
	$RSMain = ExecuteIQuery($strSQL,$iConEquip);
	$strMTP1 = null;
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strMTP1 = $LineMain["MAX_TIMESTAMP_PLUS_ONE"];
	}

	$arySQL = array();

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		EQUIP.CONFIGID_TO_SITENUM_RESNM_TEMP;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		EQUIP.CONFIGID_TO_SITENUM_RESNM_TEMP
		LIKE
		EQUIP.CONFIGID_TO_SITENUM_RESNM;";

	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		ExecuteIQuery($strSQL,$iConEquip);
	}


	$arySQL = array();

	
	$arySQL[] = "
		SELECT DISTINCT
		CL.CONFIG_ID,
		CL.RESOURCE_TYPE,
		CL.LOC,
		REPLACE(REPLACE(CL.CONFIG,'HNLR-',''),'TSTR-','') CONFIG,
		RX_LOC.SITE_NUM AS SITE_NUM_LOC,
		RX_CONFIG.SITE_NUM AS SITE_NUM_CONFIG,
		NULL AS SITE_NUM_LL,
		RX_CONFIG.REGEXP_STRING RX_CONFIG,
		GREATEST(CL.RECORD_TIMESTAMP,RX_LOC.RECORD_TIMESTAMP,RX_CONFIG.RECORD_TIMESTAMP) RECORD_DATETIME
		FROM EQUIP.CONFIG_LIST CL
		INNER JOIN
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM,
			RXM.RECORD_TIMESTAMP
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.LOC REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'LOC' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_LOC ON CL.CONFIG_ID = RX_LOC.CONFIG_ID
		INNER JOIN 
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM,
			RXM.REGEXP_STRING,
			RXM.RECORD_TIMESTAMP
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.CONFIG REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'CONFIG' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_CONFIG ON CL.CONFIG_ID = RX_CONFIG.CONFIG_ID;";
	
	$arySQL[] = "
		SELECT DISTINCT
		CL.CONFIG_ID,
		CL.RESOURCE_TYPE,
		CL.LOC,
		REPLACE(REPLACE(CL.CONFIG,'HNLR-',''),'TSTR-','') CONFIG,
		RX_LOC.SITE_NUM AS SITE_NUM_LOC,
		NULL AS SITE_NUM_CONFIG,
		NULL AS SITE_NUM_LL,
		GREATEST(CL.RECORD_TIMESTAMP,RX_LOC.RECORD_TIMESTAMP) RECORD_DATETIME
		FROM EQUIP.CONFIG_LIST CL
		INNER JOIN
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM,
			RXM.RECORD_TIMESTAMP
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.LOC REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'LOC' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_LOC ON CL.CONFIG_ID = RX_LOC.CONFIG_ID
		LEFT JOIN 
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.CONFIG REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'CONFIG' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_CONFIG ON CL.CONFIG_ID = RX_CONFIG.CONFIG_ID
		WHERE RX_CONFIG.CONFIG_ID IS NULL;";

	
	$arySQL[] = "
		SELECT DISTINCT
		CL.CONFIG_ID,
		CL.RESOURCE_TYPE,
		CL.LOC,
		REPLACE(REPLACE(CL.CONFIG,'HNLR-',''),'TSTR-','') CONFIG,
		NULL AS SITE_NUM_LOC,
		RX_CONFIG.SITE_NUM AS SITE_NUM_CONFIG,
		NULL AS SITE_NUM_LL,
		RX_CONFIG.REGEXP_STRING RX_CONFIG,
		GREATEST(CL.RECORD_TIMESTAMP,RX_CONFIG.RECORD_TIMESTAMP) RECORD_DATETIME
		FROM EQUIP.CONFIG_LIST CL
		LEFT JOIN
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.LOC REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'LOC' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_LOC ON CL.CONFIG_ID = RX_LOC.CONFIG_ID
		INNER JOIN 
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM,
			RXM.REGEXP_STRING,
			RXM.RECORD_TIMESTAMP
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.CONFIG REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'CONFIG' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_CONFIG ON CL.CONFIG_ID = RX_CONFIG.CONFIG_ID
		WHERE RX_LOC.CONFIG_ID IS NULL;";
	

	$arySQL[] = "
		SELECT DISTINCT
		CL.CONFIG_ID,
		CL.RESOURCE_TYPE,
		CL.LOC,
		REPLACE(REPLACE(CL.CONFIG,'HNLR-',''),'TSTR-','') CONFIG,
		NULL AS SITE_NUM_LOC,
		NULL AS SITE_NUM_CONFIG,
		LL.ADI_LOC AS SITE_NUM_LL,
		CL.RECORD_TIMESTAMP RECORD_DATETIME
		FROM EQUIP.CONFIG_LIST CL
		INNER JOIN EQUIP.LOC_LOOKUP LL ON CL.LOC = LL.EQUIP_LOC
		LEFT JOIN
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.LOC REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'LOC' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_LOC ON CL.CONFIG_ID = RX_LOC.CONFIG_ID
		LEFT JOIN 
		(
			SELECT
			CL.CONFIG_ID,
			RXM.OUTPUT_VALUE SITE_NUM,
			RXM.REGEXP_STRING,
			RXM.RECORD_TIMESTAMP
			FROM EQUIP.CONFIG_LIST CL
			INNER JOIN EQUIP.RX_MASTER RXM ON CL.CONFIG REGEXP RXM.REGEXP_STRING AND RXM.INPUT_FIELD = 'CONFIG' AND RXM.OUTPUT_FIELD = 'SITE_NUM'
		) RX_CONFIG ON CL.CONFIG_ID = RX_CONFIG.CONFIG_ID
		WHERE RX_LOC.CONFIG_ID IS NULL
		AND RX_CONFIG.CONFIG_ID IS NULL
		HAVING CONFIG IS NOT NULL;";


	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConEquip);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strRN = $LineMain["CONFIG"];
			$strSN = null;

			switch (true) {
				case !is_null($LineMain["SITE_NUM_LOC"]) && !is_null($LineMain["SITE_NUM_CONFIG"]):
					$strRX = "/".$LineMain["RX_CONFIG"]."/";

					preg_match_all(
						$strRX,
						$strRN,
						$aryMatch
					);

					$strRN = preg_replace($strRX,'',$strRN);

					$arySN = array(
						"LOC"=>explode("|",$LineMain["SITE_NUM_LOC"]),
						"CONFIG"=>explode("|",$LineMain["SITE_NUM_CONFIG"])
					);
					$arySN["MATCH"] = array_intersect($arySN["LOC"],$arySN["CONFIG"]);

					if(count($arySN["MATCH"]) != 1) {
						if($bolDebug === true) {
							print_r2($LineMain);
							echo $strRN."<BR>";
							print_r2($arySN);
						}

						#2025-07-01 RM this is all over the place. so dont throw an exception. but dont write anything either.  TFB
						#throw new exception("MUST BE EXACTLY ONE SITE_NUM FOR CONFIG_ID:".$LineMain["CONFIG_ID"]." (".$LineMain["SITE_NUM_LOC"]." vs ".$LineMain["SITE_NUM_CONFIG"].")");
					}

					foreach($arySN["MATCH"] as $strSN) {
						break;
					}

					break;
				case !is_null($LineMain["SITE_NUM_LOC"]):
					$arySN = array(
						"LOC"=>explode("|",$LineMain["SITE_NUM_LOC"])
					);
					if(count($arySN["LOC"]) != 1) {
						if($bolDebug === true) {
							print_r2($LineMain);
							echo $strRN."<BR>";
							print_r2($arySN);
						}

						#2025-07-01 RM use the first
						#throw new exception("MUST BE EXACTLY ONE SITE_NUM FOR CONFIG_ID:".$LineMain["CONFIG_ID"]." (".$LineMain["SITE_NUM_LOC"].");");

					}
					foreach($arySN["LOC"] as $strSN) {
						break;
					}

					break;
				case !is_null($LineMain["SITE_NUM_CONFIG"]):
					$strRX = "/".$LineMain["RX_CONFIG"]."/";

					preg_match_all(
						$strRX,
						$strRN,
						$aryMatch
					);

					$strRN = preg_replace($strRX,'',$strRN);

					$arySN = array(
						"CONFIG"=>explode("|",$LineMain["SITE_NUM_CONFIG"])
					);

					if(count($arySN["CONFIG"]) != 1) {
						if($bolDebug === true) {
							print_r2($LineMain);
							echo $strRN."<BR>";
							print_r2($arySN);
						}

						throw new exception("MUST BE EXACTLY ONE SITE_NUM FOR CONFIG_ID:".$LineMain["CONFIG_ID"]." (".$LineMain["SITE_NUM_CONFIG"].")");
					}

					foreach($arySN["CONFIG"] as $strSN) {}

					break;
				default:
					#no match on RX (LOC, CONFIG). fall back to loc_lookup
					$arySN = array(
						"LL"=>explode("|",$LineMain["SITE_NUM_LL"])
					);

					if(count($arySN["LL"]) != 1) {
						if($bolDebug === true) {
							print_r2($LineMain);
							echo $strRN."<BR>";
							print_r2($arySN);
						}

						throw new exception("MUST BE EXACTLY ONE SITE_NUM FOR CONFIG_ID:".$LineMain["CONFIG_ID"]." (".$LineMain["SITE_NUM_LL"].")");
					}

					foreach($arySN["LL"] as $strSN) {}

					break;

			}

			if(!is_null($strSN)) {
				$aryTemp = array(
					"CONFIG_ID"=>$LineMain["CONFIG_ID"],
					"SITE_NUM"=>$strSN,
					"RES_NM"=>$strRN,
					"RECORD_DATETIME"=>$LineMain["RECORD_DATETIME"]
				);

				foreach($aryTemp as $key => $value) {
					if(trim($value) != "") {
						$aryTemp[$key] = "'".mysqli_real_escape_string($iConEquip,$value)."'";
					}else{
						$aryTemp[$key] = "NULL";
					}
				}

				$strSQL = "
					INSERT INTO
					EQUIP.CONFIGID_TO_SITENUM_RESNM_TEMP
					(CONFIG_ID,SITE_NUM,RES_NM,RECORD_DATETIME)
					VALUES
					(".implode(",",$aryTemp).");";
				if($bolDebug === true) {
					#echo $strSQL."<BR>";
				}
				ExecuteIQuery($strSQL,$iConEquip);
			}

			#break;
		}

		#break;
	}


	$arySQL = array();

	$arySQL[] = "
		INSERT INTO
		EQUIP.CONFIGID_TO_SITENUM_RESNM
		(CONFIG_ID)
		SELECT DISTINCT
		TEMP.CONFIG_ID
		FROM EQUIP.CONFIGID_TO_SITENUM_RESNM_TEMP TEMP
		LEFT JOIN EQUIP.CONFIGID_TO_SITENUM_RESNM MAIN ON TEMP.CONFIG_ID = MAIN.CONFIG_ID
		WHERE MAIN.CONFIG_ID IS NULL;";

	$arySQL[] = "
		UPDATE
		EQUIP.CONFIGID_TO_SITENUM_RESNM_TEMP TEMP
		INNER JOIN EQUIP.CONFIGID_TO_SITENUM_RESNM MAIN ON TEMP.CONFIG_ID = MAIN.CONFIG_ID
		SET MAIN.SITE_NUM = TEMP.SITE_NUM,
		MAIN.RES_NM = TEMP.RES_NM,
		MAIN.RECORD_DATETIME = TEMP.RECORD_DATETIME;";

	$arySQL[] = "
		DELETE FROM
		EQUIP.CONFIGID_TO_SITENUM_RESNM
		WHERE CONFIG_ID = 0;";

	#2025-07-01 RM insert a dummy record with max(timestamp) + 1 second from both config_list and rx_master
	#this indicates that this run ran to completion. if that record does not get the +1 second datetime, then it is incomplete and will be forced to refresh next check
	$aryTemp = array(
		"CONFIG_ID"=>"0",
		"SITE_NUM"=>"COMPLETION_CONFIRMATION_DUMMY",
		"RES_NM"=>"COMPLETION_CONFIRMATION_DUMMY",
		"RECORD_DATETIME"=>$strMTP1
	);

	foreach($aryTemp as $key => $value) {
		$aryTemp[$key] = "'".mysqli_real_escape_string($iConEquip,$value)."'";
	}

	$arySQL[] = "
		INSERT INTO
		EQUIP.CONFIGID_TO_SITENUM_RESNM
		(".implode(",",array_keys($aryTemp)).")
		VALUES
		(".implode(",",$aryTemp).");";

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		EQUIP.CONFIGID_TO_SITENUM_RESNM_TEMP;";

	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		ExecuteIQuery($strSQL,$iConEquip);
	}


}


function CONFIGID_TO_SITENUM_RESNM_REQUIRES_REFRESH($iConEquip,$bolDebug=false) {
	#2025-07-01 RM the only way for the +1 second to get in there is if the final insert/update was successful in the previous run
	#if it was incomplete for any reason, force the update

	$strSQL = "
		SELECT
		'THIS' THIS_THAT,
		MAX(RECORD_DATETIME) RECORD_DATETIME
		FROM EQUIP.CONFIGID_TO_SITENUM_RESNM
		UNION
		SELECT 
		'THAT' THIS_THAT,
		DATE_ADD(
			GREATEST(
				MAX(CL.RECORD_TIMESTAMP),
				MAX(RXM.RECORD_TIMESTAMP)
			),
			INTERVAL 1 SECOND
		)
		FROM EQUIP.CONFIG_LIST CL,
		EQUIP.RX_MASTER RXM;";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConEquip);
	$aryMain = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strTT = $LineMain["THIS_THAT"];
		$aryMain[$strTT]["RECORD_DATETIME"] = $LineMain["RECORD_DATETIME"];
	}

	if(!isset($aryMain["THIS"]) || !isset($aryMain["THAT"])) {
		throw new exception("MISSING RECORDS IN ".__FUNCTION__);
	}
	
	if($aryMain["THIS"]["RECORD_DATETIME"] != $aryMain["THAT"]["RECORD_DATETIME"]) {
		return true;
	}

	return false;
}


function ATOM_GET($iConEquip,$aryInputTemp,$strOutputType,$bolDebug=false) {
	if($bolDebug === true) {
		Print_R2($aryInputTemp);
	}
	#allowable passed filters
	$aryMap = array(
		"TABLE_FIELD"=>array(
			"CONFIG_LIST"=>array("RESOURCE_TYPE","WS_FT"),
			"CONFIGID_TO_SITENUM_RESNM"=>array("SITE_NUM","RES_NM")
		)
	);

	foreach($aryMap["TABLE_FIELD"] as $strTable => $aryField) {
		foreach($aryField as $strField) {
			$aryMap["FIELD_TABLE"][$strField] = $strTable;
		}
	}	

	$aryDiff = array_diff(array_keys($aryInputTemp),array_keys($aryMap["FIELD_TABLE"]));
	if(count($aryDiff) > 0) {
		throw new exception("unrecognized filter/s (".implode(",",$aryDiff).")");
	}


	$arySQL = array();
	foreach($aryInputTemp as $strField => $aryValue) {
		$strTable = $aryMap["FIELD_TABLE"][$strField];
		$aryTemp = array();
		foreach($aryValue as $strValue) {
			$aryTemp[] = mysqli_real_escape_string($iConEquip,$strValue);
		}

		$arySQL[$strTable]["WHERE"][] = $strField." IN ('".implode("','",$aryTemp)."')";
	}


	foreach($arySQL as $strTable => $arySQLTemp) {
		switch (true) {
			case $strTable == "CONFIG_LIST":
				$strSQL = "
					SELECT DISTINCT
					CM.MASTER_ID
					FROM EQUIP.CONFIG_MASTER CM
					INNER JOIN EQUIP.CONFIG_LIST CL ON CM.CONFIG_ID = CL.CONFIG_ID
					WHERE ".implode(" AND ",$arySQLTemp["WHERE"]).";";
				break;
			case $strTable == "CONFIGID_TO_SITENUM_RESNM":
				#2025-05-29 RM added this because apparently its too hard

				$strSQL = "
					SELECT DISTINCT
					CONFIG_ID
					FROM EQUIP.".$strTable."
					WHERE ".implode(" AND ",$arySQLTemp["WHERE"]).";";
				$RSMain = ExecuteIQuery($strSQL,$iConEquip);
				$aryCID = array();
				while ($LineMain = mysqli_fetch_assoc($RSMain)) {
					$aryCID[] = $LineMain["CONFIG_ID"];
				}

				$aryTemp = array();
				while ($intCID = array_pop($aryCID)) {
					$aryTemp[] = mysqli_real_escape_string($iConEquip,$intCID);
				}

				if(count($aryTemp) > 0) {
					$strSQL = "
						SELECT DISTINCT
						CM.MASTER_ID
						FROM EQUIP.CONFIG_MASTER CM
						WHERE CONFIG_ID IN (".implode(",",$aryTemp).");";
				}else{
					$strSQL = "SELECT MASTER_ID FROM EQUIP.MASTER WHERE MASTER_ID = 0;";
				}

				break;
			default:
				$strSQL = "
					SELECT DISTINCT
					MASTER_ID
					FROM EQUIP.".$strTable."
					WHERE ".implode(" AND ",$arySQLTemp["WHERE"]).";";
				break;
		}

		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConEquip);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intMID = $LineMain["MASTER_ID"];
			$aryMap["MASTERID_TABLE"][$intMID][] = $strTable;
		}
	}


	$aryMID = array();
	foreach(array_keys($aryMap["MASTERID_TABLE"]) as $intMID) {
		#every table queried returned this MASTER_ID
		$aryDiff = array_diff(array_keys($arySQL),$aryMap["MASTERID_TABLE"][$intMID]);
		if(count($aryDiff) == 0) {
			$aryMID[] = $intMID;
		}
	}
	

	if($strOutputType == "MASTER_ID") {
		return $aryMID;
	}



	$aryReturn = array();
	$aryTemp = array();
	foreach($aryMID as $intMID) {
		$aryTemp[] = mysqli_real_escape_string($iConEquip,$intMID);
	}
	

	switch (true) {
		case count($aryTemp) == 0:
			return array();
			break;
		case $strOutputType == "RES_NM":
			$strSQL = "
				SELECT
				CSR.RES_NM,
				COUNT(DISTINCT CM.MASTER_ID) MID_COUNT,
				SUM(CM.CONFIG_PCNT) CONFIG_PCNT
				FROM EQUIP.CONFIG_MASTER CM
				INNER JOIN EQUIP.CONFIG_LIST CL ON CM.CONFIG_ID = CL.CONFIG_ID
				INNER JOIN EQUIP.CONFIGID_TO_SITENUM_RESNM CSR ON CM.CONFIG_ID = CSR.CONFIG_ID
				WHERE ISNULL(CM.EFF_DATE)
				AND CM.MASTER_ID IN (".implode(",",$aryTemp).")";
			if(isset($arySQL["CONFIGID_TO_SITENUM_RESNM"]["WHERE"])) {
				$strSQL .= " AND ".implode(" AND ",$arySQL["CONFIGID_TO_SITENUM_RESNM"]["WHERE"]);
			}
				
			$strSQL .= " GROUP BY CSR.RES_NM ORDER BY RES_NM;";

			$RSMain = ExecuteIQuery($strSQL,$iConEquip);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strRN = $LineMain["RES_NM"];
				$aryReturn[$strRN] = array(
					"COUNT"=>$LineMain["MID_COUNT"],
					"CONFIG_PCNT"=>$LineMain["CONFIG_PCNT"]
				);
			}

			break;	
	}

	return $aryReturn;
}


function ENG_ATOM_GET($iConEquip,$aryInputTemp,$strOutputType,$bolDebug=false) {
	if($bolDebug === true) {
		Print_R2($aryInputTemp);
	}
	#allowable passed filters
	$aryMap = array(
		"TABLE_FIELD"=>array(
			"CONFIG_LIST"=>array("RESOURCE_TYPE","WS_FT"),
			"CONFIGID_TO_SITENUM_RESNM"=>array("SITE_NUM","RES_NM"),
			"V_UDF_ENG_CONFIG"=>array("ENG_CONFIG")
		)
	);

	foreach($aryMap["TABLE_FIELD"] as $strTable => $aryField) {
		foreach($aryField as $strField) {
			$aryMap["FIELD_TABLE"][$strField] = $strTable;
		}
	}

	$aryDiff = array_diff(array_keys($aryInputTemp),array_keys($aryMap["FIELD_TABLE"]));
	if(count($aryDiff) > 0) {
		throw new exception("unrecognized filter/s (".implode(",",$aryDiff).")");
	}


	$arySQL = array();
	foreach($aryInputTemp as $strField => $aryValue) {
		$strTable = $aryMap["FIELD_TABLE"][$strField];
		$aryTemp = array();
		foreach($aryValue as $strValue) {
			$aryTemp[] = mysqli_real_escape_string($iConEquip,$strValue);
		}

		$arySQL[$strTable]["WHERE"][] = $strField." IN ('".implode("','",$aryTemp)."')";
	}


	foreach($arySQL as $strTable => $arySQLTemp) {
		switch (true) {
			case $strTable == "CONFIG_LIST":
				$strSQL = "
					SELECT DISTINCT
					CM.MASTER_ID
					FROM EQUIP.CONFIG_MASTER CM
					INNER JOIN EQUIP.CONFIG_LIST CL ON CM.CONFIG_ID = CL.CONFIG_ID
					WHERE ".implode(" AND ",$arySQLTemp["WHERE"]).";";
				break;
			case $strTable == "CONFIGID_TO_SITENUM_RESNM":
				$strSQL = "
					SELECT DISTINCT
					CONFIG_ID
					FROM EQUIP.".$strTable."
					WHERE ".implode(" AND ",$arySQLTemp["WHERE"]).";";
				$RSMain = ExecuteIQuery($strSQL,$iConEquip);
				$aryCID = array();
				while ($LineMain = mysqli_fetch_assoc($RSMain)) {
					$aryCID[] = $LineMain["CONFIG_ID"];
				}

				$aryTemp = array();
				while ($intCID = array_pop($aryCID)) {
					$aryTemp[] = mysqli_real_escape_string($iConEquip,$intCID);
				}

				if(count($aryTemp) > 0) {
					$strSQL = "
						SELECT DISTINCT
						CM.MASTER_ID
						FROM EQUIP.CONFIG_MASTER CM
						WHERE CONFIG_ID IN (".implode(",",$aryTemp).");";
				}else{
					$strSQL = "SELECT MASTER_ID FROM EQUIP.MASTER WHERE MASTER_ID = 0;";
				}

				break;
			default:
				#dont need special treatment for eng_config, as it works with built-in definitions
				$strSQL = "
					SELECT DISTINCT
					MASTER_ID
					FROM EQUIP.".$strTable."
					WHERE ".implode(" AND ",$arySQLTemp["WHERE"]).";";
				break;
		}

		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConEquip);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intMID = $LineMain["MASTER_ID"];
			$aryMap["MASTERID_TABLE"][$intMID][] = $strTable;
		}
	}


	$aryMID = array();
	foreach(array_keys($aryMap["MASTERID_TABLE"]) as $intMID) {
		#every table queried returned this MASTER_ID
		$aryDiff = array_diff(array_keys($arySQL),$aryMap["MASTERID_TABLE"][$intMID]);
		if(count($aryDiff) == 0) {
			$aryMID[] = $intMID;
		}
	}
	

	if($strOutputType == "MASTER_ID") {
		return $aryMID;
	}



	$aryReturn = array();
	$aryTemp = array();
	foreach($aryMID as $intMID) {
		$aryTemp[] = mysqli_real_escape_string($iConEquip,$intMID);
	}
	

	switch (true) {
		case count($aryTemp) == 0:
			return array();
			break;
		case $strOutputType == "RES_NM":
			$strSQL = "
				SELECT
				CSR.RES_NM,
				COUNT(DISTINCT CM.MASTER_ID) MID_COUNT,
				SUM(CM.CONFIG_PCNT) CONFIG_PCNT
				FROM EQUIP.CONFIG_MASTER CM
				INNER JOIN EQUIP.CONFIG_LIST CL ON CM.CONFIG_ID = CL.CONFIG_ID
				INNER JOIN EQUIP.CONFIGID_TO_SITENUM_RESNM CSR ON CM.CONFIG_ID = CSR.CONFIG_ID
				WHERE ISNULL(CM.EFF_DATE)
				AND CM.MASTER_ID IN (".implode(",",$aryTemp).")";
			if(isset($arySQL["CONFIGID_TO_SITENUM_RESNM"]["WHERE"])) {
				$strSQL .= " AND ".implode(" AND ",$arySQL["CONFIGID_TO_SITENUM_RESNM"]["WHERE"]);
			}
				
			$strSQL .= " GROUP BY CSR.RES_NM ORDER BY RES_NM;";

			$RSMain = ExecuteIQuery($strSQL,$iConEquip);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strRN = $LineMain["RES_NM"];
				$aryReturn[$strRN] = array(
					"COUNT"=>$LineMain["MID_COUNT"],
					"CONFIG_PCNT"=>$LineMain["CONFIG_PCNT"]
				);
			}

			break;
		case $strOutputType == "ENG_ATOM":
			$strSQL = "				
				SELECT
				VUDFEC.ENG_CONFIG,
				CSR.RES_NM,
				COUNT(DISTINCT CM.MASTER_ID) MID_COUNT,
				SUM(CONFIG_PCNT) CONFIG_PCNT
				FROM EQUIP.CONFIG_MASTER CM
				INNER JOIN EQUIP.CONFIG_LIST CL ON CM.CONFIG_ID = CL.CONFIG_ID
				INNER JOIN EQUIP.CONFIGID_TO_SITENUM_RESNM CSR ON CL.CONFIG_ID = CSR.CONFIG_ID
				INNER JOIN EQUIP.V_UDF_ENG_CONFIG VUDFEC ON CM.MASTER_ID = VUDFEC.MASTER_ID
				WHERE ISNULL(CM.EFF_DATE)
				AND CM.MASTER_ID IN (".implode(",",$aryTemp).")";
			if(isset($arySQL["CONFIGID_TO_SITENUM_RESNM"]["WHERE"])) {
				$strSQL .= " AND ".implode(" AND ",$arySQL["CONFIGID_TO_SITENUM_RESNM"]["WHERE"]);
			}
				
			$strSQL .= " GROUP BY ENG_CONFIG, RES_NM;";

			$RSMain = ExecuteIQuery($strSQL,$iConEquip);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strEC = $LineMain["ENG_CONFIG"];
				$strRN = $LineMain["RES_NM"];

				$aryReturn[$strEC][$strRN] = array(
					"COUNT"=>$LineMain["MID_COUNT"],
					"CONFIG_PCNT"=>$LineMain["CONFIG_PCNT"]
				);
			}

			break;	
	}

	return $aryReturn;
}
