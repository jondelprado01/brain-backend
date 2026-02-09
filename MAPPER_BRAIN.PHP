<?php
ini_set("error_reporting",E_ALL);
ini_set("display_errors",1);

include_once("/var/www/html/MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("/var/www/html/API/BRAIN_FUNCTIONS.PHP");


$aryIO = array();
$aryIO[] = array(
	"INPUT_TYPE"=>array("PRS_ID","MFG_PART_NUM"),
	"OUTPUT_TYPE"=>array("RES_GET")
);
$aryIO[] = array(
	"INPUT_TYPE"=>array("JSON"),
	"OUTPUT_TYPE"=>array("INSTANCE_CLONE")
);

$bolDebug = false;
#$bolDebug = true;
if($bolDebug === true) {
	print_r2($_REQUEST);
}

$strInputType = $_REQUEST["INPUT_TYPE"];
$strOutputType = $_REQUEST["OUTPUT_TYPE"];
$aryInput = $_REQUEST["INPUT"];

switch (true) {
	case $strOutputType == "TP_INSTANCE_CLONE":
		$iConRLM = Open_ILink("localhost");

		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();		
		foreach($aryInput as $intCtr => $jsnInput) {
			try{
				$aryInputTemp = json_decode($jsnInput,true);
				
				if(JSON_LAST_ERROR() == JSON_ERROR_NONE) {
					$intTPIID = TP_INSTANCE_CLONE($iConRLM,$aryInputTemp,$bolDebug);
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
	

		mysqli_close($iConRLM);
		break;
	case $strOutputType == "RES_GET":
		$iConRLM = Open_ILink("localhost");

		$aryData = RES_GET($iConRLM,$aryInput,$bolDebug);

		mysqli_close($iConRLM);

		break;		
}

$aryReturn = array(
	"STATUS"=>$strStatus,
	"MESSAGE"=>$strMessage,
	"DATA"=>$aryData
);
echo json_encode($aryReturn);



function TP_INSTANCE_CLONE($iConRLM,$aryInputTemp,$bolDebug=false) {
	#2025-06-26 RM two possible ways: TPI_ID or SAP_RTE_ID. TPI_ID will clone an existing instance, SAP_RTE_ID will clone the EFF_START_DT = NULL entry from adi_alldb_all

	#basic error checking
	switch (true) {
		case !isset($aryInputTemp["EFF_START_DT"]):
			throw new exception("MISSING EFF_START_DT");
			break;
		case !isset($aryInputTemp["TPI_ID"]) && !isset($aryInputTemp["SAP_RTE_ID"]):
			throw new exception("MISSING TPI_ID OR SAP_RTE_ID");
			break;
	}
	
	#print_r2($aryInputTemp);
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
			MFG_YEAR*100+WEEK_NUMBER
			FROM shared.DMCLS_MEMORY
			WHERE MFG_DATE = STR_TO_DATE(NOW(),'%Y-%m-%d')
		)
		AND FYWW <= 
		(
			SELECT
			MFG_YEAR*100+WEEK_NUMBER
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

	#now process
	switch (true) {
		case isset($aryInputTemp["TPI_ID"]):
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
			$intTPIIDThis = TP_INSTANCE_CREATE($iConRLM,$strSRI,$strESD,$aryInputTemp["PROCESS_TYPE"],$aryInputTemp["NOTES"],$bolDebug);

			#continue. using almost all fields for now. we can trim later
			$strSQL = "
				INSERT INTO
				BRAIN.TP_INSTANCE_DATA
				(TPI_ID,MFG_PART_NUM,GENERIC,SAP_RTE_ID,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,TESTTIME,INDEXTIME,TESTER_PROCEFF,HANDLER_PROCEFF,PTIME,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,ATOM_MASTER_ID,ATTR_SET_ID)
				SELECT
				".mysqli_real_escape_string($iConRLM,$intTPIIDThis)." AS TPI_ID,
				MFG_PART_NUM,GENERIC,SAP_RTE_ID,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,TESTTIME,INDEXTIME,TESTER_PROCEFF,HANDLER_PROCEFF,PTIME,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,ATOM_MASTER_ID,ATTR_SET_ID
				FROM BRAIN.TP_INSTANCE_DATA
				WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$intTPIIDThat).";";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);

			return $intTPIIDThis;

			break;
		case isset($aryInputTemp["SAP_RTE_ID"]):
			#check it exists
			$strSQL = "
				SELECT
				SAP_RTE_ID
				FROM BODS_JDA_STAGE.ADI_ALLDB_ALL
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$aryInputTemp["SAP_RTE_ID"])."'
				LIMIT 0,1;";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			$strSRI = null;
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strSRI = $LineMain["SAP_RTE_ID"];
			}

			if(is_null($strSRI)) {
				throw new exception("INVALID SAP_RTE_ID");
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
			$intTPIIDThis = TP_INSTANCE_CREATE($iConRLM,$strSRI,$strESD,$aryInputTemp["PROCESS_TYPE"],$aryInputTemp["NOTES"],$bolDebug);

			#continue. using almost all fields for now. we can trim later
			$strSQL = "
				INSERT INTO
				BRAIN.TP_INSTANCE_DATA
				(TPI_ID,MFG_PART_NUM,GENERIC,SAP_RTE_ID,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,TESTTIME,INDEXTIME,TESTER_PROCEFF,HANDLER_PROCEFF,PTIME,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,ATOM_MASTER_ID,ATTR_SET_ID)
				SELECT
				".mysqli_real_escape_string($iConRLM,$intTPIIDThis)." AS TPI_ID,
				MFG_PART_NUM,GENERIC,SAP_RTE_ID,RTE_SEQ_NUM,STEP_NM,SITE_NUM,RES_AREA,TEMP_C,TEMP_CLASS,RES_SET_ID,RES_PRIO_CD,TESTER,HANDLER,HW_SET_ID,UTPI,TESTTIME,INDEXTIME,TESTER_PROCEFF,HANDLER_PROCEFF,PTIME,YLD,TEST_PERC,REF_DOC_ID,REF_STEP_ID,REF_SETUP_ID,TD_EFF,WFR_IDX_PCT,GROSS_DPW,TTPI,INDX,OEE,UTPH,ATOM_MASTER_ID,ATTR_SET_ID
				FROM BODS_JDA_STAGE.ADI_ALLDB_ALL
				WHERE EFF_START_DT IS NULL
				AND SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."';";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);

			return $intTPIIDThis;

			break;
	}
}


function TP_INSTANCE_CREATE($iConRLM,$strSRI,$strESD,$strPT,$strNotes,$bolDebug=false) {
	$aryTemp = array(
		"SAP_RTE_ID"=>$strSRI,
		"EFF_START_DT"=>$strESD,
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
		(SAP_RTE_ID,EFF_START_DT,PROCESS_TYPE,NOTES)
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

function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}