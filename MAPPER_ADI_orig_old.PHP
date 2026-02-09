<?PHP
#2020-10-27 RM force no caching
header('Access-Control-Allow-Origin: *');
header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("ADI_FUNCTIONS.PHP");
ini_set("error_reporting",E_ALL);
ini_set("display_errors",1);

$aryRequest = $_REQUEST;

$bolDebug = false;
if(isset($aryRequest["DEBUG"]) && $aryRequest["DEBUG"] != "") {
	$bolDebug=true;
}

$bolJSON = true;
if(isset($aryRequest["NO_JSON"]) && $aryRequest["NO_JSON"] != "") {
	$bolJSON = false;
}

/*
$aryInput = array(
	array(
		"EMAIL_ADDRESS"=>"reyn.mito@analog.com",
		"SYSTEM_NAME"=>"DELINQUENT_SCHEDULE"
	)
);
$aryRequest = array(
	"INPUT_TYPE"=>"JSON",
	"OUTPUT_TYPE"=>"SYSTEM_USER_ADD",
	"INPUT"=>JSON_encode($aryInput)
);
$aryInput = array("1/2064-0BCT-T0JZBZ");
$strInputType = "MFG_PART_NUM";
$strOutputType = "BODS_JDA_ADI";

$aryRequest = array(
	"INPUT_TYPE"=>$strInputType,
	"OUTPUT_TYPE"=>$strOutputType,
	"INPUT"=>$aryInput
);
*/

$aryIO = array();
$aryIO[] = array("INPUT_TYPE"=>array("MFG_PART_NUM","SAP_RTE_ID"),"OUTPUT_TYPE"=>array("PRODUCT_DATA_ADI","PRODUCT_DATA_ADI_DEV","ADI_ROUTE","ADI_ROUTE_PARAM"));
$aryIO[] = array("INPUT_TYPE"=>array("MFG_PART_NUM"),"OUTPUT_TYPE"=>array("BODS_JDA_ADI","BODS_JDA_ADI_ALL","BODS_JDA_ADI_DEV","BODS_JDA_ADI_ALL_DEV"));
$aryIO[] = array("INPUT_TYPE"=>array("SEARCH"),"OUTPUT_TYPE"=>array("MFG_PART_NUM_ADI","MFG_PART_NUM_ADI_DEV"));
$aryIO[] = array("INPUT_TYPE"=>array("JSON"),"OUTPUT_TYPE"=>array("ATOM_NAME","ATOM_CHANGE","ATOM_CHANGE_HTML"));

// $bolOK = REQUEST_VALIDATE($aryIO,$aryRequest);
$bolOK = true;

#Print_R2($_REQUEST);
#Print_R2($aryRequest);


if($bolOK === true) {
	if($bolDebug === true) {
		Print_R2($aryRequest);
	}
	$strOutputType = $aryRequest["OUTPUT_TYPE"];
	$strInputType = $aryRequest["INPUT_TYPE"];

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

	#Print_R2($aryInput);
	#die();

	switch (true) {
		case $strOutputType == "ATOM_CHANGE" || $strOutputType == "ATOM_CHANGE_HTML":
			$iConRLM = Open_ILink("localhost");

			$aryLogic = array();

			try{
				$aryTry = array("MFG_PART_NUM","STEP_NM","TESTER","HANDLER");
			
				foreach($aryTry as $strTry) {
					if(!isset($aryInput[$strTry])) {
						throw new exception("MISSING INPUT: ".$strTry);
					}
				}
				$aryData = ATOM_CHANGE_GET($iConRLM,$aryInput,$bolDebug);

				if($strOutputType == "ATOM_CHANGE_HTML") {
					$aryHTML = array();
					$aryHTML[] = "<TABLE CLASS=B1 BORDER=1 CELLSPACING=0 CELLPADDING=1 WIDTH=800>";
					$aryHTML[] = "<COL WIDTH=60><COL WIDTH=150><COL WIDTH=150><COL WIDTH=400>";
					$aryHTML[] = "<THEAD><TR BGCOLOR=SILVER><TH>RES_TYPE</TH><TH>FROM</TH><TH>TO</TH><TH>LOGIC_STRING</TH></TR></THEAD>";
					$aryHTML[] = "<TBODY CLASS=B1>";
					foreach(array_keys($aryData) as $intAMID) {
						foreach(array_keys($aryData[$intAMID]) as $strRT) {
							foreach($aryData[$intAMID][$strRT] as $aryDataTemp) {
								$aryHTML[] = "<TR><TD>".$strRT."</TD><TD>".implode("</TD><TD>",$aryDataTemp)."</TD></TR>";
							}
						}
					}
					$aryHTML[] = "</TBODY></TABLE>";

					$aryData = implode("\n",$aryHTML);
					#echo $aryData;
					#die();
				}
			

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;
		case $strOutputType == "ADI_ROUTE":
			$iConRLM = Open_ILink("localhost");

			try{
				if($strInputType == "MFG_PART_NUM") {
					$aryMap = array(
						"SRI_MFRPN"=>ADI_SRI_MFRPN_GET($iConRLM,$aryInput,$bolDebug)
					);
					
					$aryThis = ADI_ROUTE_GET($iConRLM,array_keys($aryMap["SRI_MFRPN"]),$bolDebug);
					$aryThat = array();
					foreach(array_keys($aryThis) as $strSRI) {
						$strMFRPN = $aryMap["SRI_MFRPN"][$strSRI];
						$aryThat[$strMFRPN] = $aryThis[$strSRI];
					}
					$aryData = $aryThat;
				}else{
					$aryData = ADI_ROUTE_GET($iConRLM,$aryInput,$bolDebug);

				}
				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;
		case $strOutputType == "ADI_ROUTE_PARAM":
			$iConRLM = Open_ILink("localhost");

			try{
				if($strInputType == "MFG_PART_NUM") {
					$aryMap = array(
						"SRI_MFRPN"=>ADI_SRI_MFRPN_GET($iConRLM,$aryInput)
					);
					
					$aryThis = ADI_ROUTE_PARAM_GET($iConRLM,array_keys($aryMap["SRI_MFRPN"]),$bolDebug);
					$aryThat = array();
					foreach(array_keys($aryThis) as $strSRI) {
						$strMFRPN = $aryMap["SRI_MFRPN"][$strSRI];
						$aryThat[$strMFRPN] = $aryThis[$strSRI];
					}
					$aryData = $aryThat;
				}else{
					$aryData = ADI_ROUTE_PARAM_GET($iConRLM,$aryInput,$bolDebug);
				}
				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;

		case $strOutputType == "PRODUCT_DATA_ADI" || $strOutputType == "PRODUCT_DATA_ADI_DEV":
			if($strOutputType == "PRODUCT_DATA_ADI") {
				$iConRLM = Open_ILink("localhost");
			}else{
				$iConRLM = Open_ILink("mxhdafot01l");
			}
			

			try{
				if($strInputType == "SAP_RTE_ID") {
					$aryMap = array(
						"MFRPN_SRI"=>ADI_MFRPN_SRI_GET($iConRLM,$aryInput,$bolDebug)
					);
					
					$aryThis = PRODUCT_DATA_ADI_GET($iConRLM,array_keys($aryMap["MFRPN_SRI"]),$bolDebug);
					$aryThat = array();
					foreach(array_keys($aryThis) as $strMFRPN) {
						$strSRI = $aryMap["MFRPN_SRI"][$strMFRPN];
						$aryThat[$strSRI] = $aryThis[$strMFRPN];
					}
					$aryData = $aryThat;
				}else{
					$aryData = PRODUCT_DATA_ADI_GET($iConRLM,$aryInput,$bolDebug, $aryRequest);
				}

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;

		case $strOutputType == "MFG_PART_NUM_ADI" || $strOutputType == "MFG_PART_NUM_ADI_DEV":
			if($strOutputType == "MFG_PART_NUM_ADI") {
				$iConRLM = Open_ILink("localhost");
			}else{
				$iConRLM = Open_ILink("mxhdafot01l");
			}

			try{
				$aryData = MFG_PART_NUM_ADI_GET($iConRLM,$aryInput,$bolDebug);

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;
		case $strOutputType == "BODS_JDA_ADI_ALL":
			$iConRLM = Open_ILink("localhost");

			try{
				$aryData = BODS_JDA_ADI_ALL_GET($iConRLM,$aryInput,$bolDebug);

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;
		case $strOutputType == "BODS_JDA_ADI_ALL_DEV":
			$iConRLM = Open_ILink("mxhdafot01l");

			try{
				$aryData = BODS_JDA_ADI_ALL_GET($iConRLM,$aryInput,$bolDebug);

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;		
		case $strOutputType == "BODS_JDA_ADI":
			$iConRLM = Open_ILink("localhost");

			try{
				#2024-06-01 RM deployed OPC (SUS)
				$aryData = BODS_JDA_ADI_SUS_GET($iConRLM,$aryInput,$bolDebug);
				#$aryData = BODS_JDA_ADI_GET($iConRLM,$aryInput,$bolDebug);

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}

			mysqli_close($iConRLM);

			break;		
		case $strOutputType == "BODS_JDA_ADI_DEV":
			$iConRLM = Open_ILink("mxhdafot01l");

			try{
				$aryData = BODS_JDA_ADI_SUS_GET($iConRLM,$aryInput,$bolDebug);

				$strStatus = "SUCCESS";
				$strMessage = "";

			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
			}
			case $strOutputType == "SEARCH_V2_ADI": //NEW SEARCH API - SEARCH_V2 (JCDP)
				$iConRLM = Open_ILink("localhost");
	
				try{
					$aryData = SEARCH_V2_ADI_GET($iConRLM,$aryInput,$bolDebug);
	
					$strStatus = "SUCCESS";
					$strMessage = "";
	
				} catch (exception $e) {
					$strStatus = "FAILURE";
					$strMessage = $e->getMessage();
				}

			mysqli_close($iConRLM);

			break;
			
			case $strOutputType == "GET_DETAILS": //NEW SEARCH API - SEARCH_V2 (JCDP)
				$iConRLM = Open_ILink("localhost");
	
				try{
					$aryData = GET_DETAILS($iConRLM,$aryInput,$bolDebug);
	
					$strStatus = "SUCCESS";
					$strMessage = "";
	
				} catch (exception $e) {
					$strStatus = "FAILURE";
					$strMessage = $e->getMessage();
				}

			mysqli_close($iConRLM);

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


if($bolJSON === true) {
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
}else{
	// Print_R2($aryResponse);
	print_r(json_encode($aryResponse));
}



function ATOM_CHANGE_GET($iConRLM,$aryInputTemp,$bolDebug=false) {
	#2023-10-19 RM remove _ZEROCAP as that's an incoming filter
	$strTester = str_replace("_ZEROCAP","",$aryInputTemp["TESTER"]);
	$strHandler = str_replace("_ZEROCAP","",$aryInputTemp["HANDLER"]);

	$strSQL = "
		SELECT
		AM.AM_ID,
		AC.AC_ID,
		AC.RES_TYPE,
		AC.RES_NM_FROM,
		AC.RES_NM_TO,
		AL.LOGIC_STRING
		FROM BODS_JDA_ADI_EXPORT.ATOM_MASTER AM
		INNER JOIN BODS_JDA_ADI_EXPORT.ATOM_CHANGE AC ON AM.AC_MASTER_ID = AC.AC_MASTER_ID
		INNER JOIN BODS_JDA_ADI_EXPORT.ATOM_LOGIC AL ON AC.LINE_NUMBER = AL.LINE_NUMBER
		WHERE AM.PART_NAME = '".mysqli_real_escape_string($iConRLM,$aryInputTemp["MFG_PART_NUM"])."'
		AND AM.STEP_NAME = '".mysqli_real_escape_string($iConRLM,$aryInputTemp["STEP_NM"])."'
		AND AM.TESTER_NEW = '".mysqli_real_escape_string($iConRLM,$strTester)."'
		AND AM.HANDLER_NEW = '".mysqli_real_escape_string($iConRLM,$strHandler)."'
		ORDER BY AM_ID, RES_TYPE, AC_ID;";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMain = array();
	$aryLogic = array();

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intAMID = $LineMain["AM_ID"];
		$intACID = $LineMain["AC_ID"];
		$strRT = $LineMain["RES_TYPE"];
		
		$aryMain[$intAMID][$strRT][$intACID] = array(
			"FROM"=>$LineMain["RES_NM_FROM"],
			"TO"=>$LineMain["RES_NM_TO"],
			"LOGIC_STRING"=>$LineMain["LOGIC_STRING"]
		);
	}

	return $aryMain;
}


function PRODUCT_DATA_ADI_GET($iConRLM,$aryInput,$bolDebug=false, $aryRequest=null) {

	$aryReturn = array();

	#batch it up
	$aryMap = array(
		"INPUT_CTR"=>array(),
		"MPN_SRI"=>array(),
		"SRI_MPN"=>array()
	);

	foreach($aryInput as $intCtr => $strInput) {
		$aryMap["INPUT_CTR"][$strInput][] = $intCtr;

		$strSQL = "
			SELECT DISTINCT
			SAP_RTE_ID
			FROM BODS_JDA_STAGE.ADI_ALLDB
			WHERE SAP_RTE_ID LIKE '".mysqli_real_escape_string($iConRLM,$strInput)."%'
			HAVING SAP_RTE_ID REGEXP '".mysqli_real_escape_string($iConRLM,$strInput).".[0-9]{2}$'";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSRI = $LineMain["SAP_RTE_ID"];
			$aryMap["MPN_SRI"][$strInput][] = $strSRI;
			$aryMap["SRI_MPN"][$strSRI][] = $strInput;
		}
	}

	
	$aryTemp = array();
	foreach($aryInput as $strInput) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strInput);
	}

	$aryTemp = array();
	foreach(array_keys($aryMap["SRI_MPN"]) as $strSRI) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strSRI);
	}


	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT DISTINCT
			ROUTE_ID,
			PARMNAME,
			PARMVAL
			FROM ADI_ESLDATA.ADI_ROUTE_PARAM
			WHERE PARMNAME IN ('\$GENERIC','\$BODYSIZE','\$LEADCOUNT','\$PACKAGETYPE')
			AND ROUTE_ID IN ('".implode("','",$aryTemp)."')";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSRI = $LineMain["ROUTE_ID"];
			$strField = str_replace("\$","",$LineMain["PARMNAME"]);

			foreach($aryMap["SRI_MPN"][$strSRI] as $strInput) {
				foreach($aryMap["INPUT_CTR"][$strInput] as $intCtr) {
					$strInput = $aryInput[$intCtr];
					$aryReturn[$strInput][$strField][] = $LineMain["PARMVAL"];
				}
			}
		}
	}

	foreach($aryInput as $strInput) {
		if(!isset($aryReturn[$strInput])) {
			$aryReturn[$strInput] = array();
		}
	}

	if ($aryRequest['INPUT_TYPE'] == 'RECORD_ALL') {
		foreach ($rawResult as $raw) {
			$aryReturn[$raw['MFG_PART_NUM']]['TEST_TYPE'] = $raw['RES_AREA'];
		}
	}

	return $aryReturn;
}


function MFG_PART_NUM_ADI_GET($iConRLM,$aryInput,$bolDebug=false) {
	$aryReturn = array();
	foreach($aryInput as $intCtr => $strInput) {
		$aryReturn[$intCtr] = array();

		#2023-09-18 RM added generic
		$strSQL = "
			SELECT DISTINCT
			MFG_PART_NUM
			FROM BODS_JDA_STAGE.ADI_ALLDB
			WHERE MFG_PART_NUM LIKE '".mysqli_real_escape_string($iConRLM,$strInput)."%'
			OR GENERIC LIKE '".mysqli_real_escape_string($iConRLM,$strInput)."%'
			ORDER BY MFG_PART_NUM;";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$aryReturn[$intCtr][] = $LineMain["MFG_PART_NUM"];
		}
	}
	return $aryReturn;
}


function BODS_JDA_ADI_ALL_GET($iConRLM,$aryMFRPN,$bolDebug=false) {
	$aryTemp = array();
	foreach($aryMFRPN as $strMFRPN) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strMFRPN);
	}

	$aryMain = array();
	$aryMap = array(
		"SRI_FLOW"=>array(),
		"SRI_MFRPN"=>array(),
		"HWSID_SN_RA_MFRPN_START_END_SRI_SEQ_PRI"=>array()
	);

	#2023-09-13 RM have to fake sample_rate
	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			MFG_PART_NUM,
			EFF_START_DT,
			EFF_END_DT,
			SAP_RTE_ID,
			RES_AREA,
			RTE_SEQ_NUM,
			STEP_NM,
			SITE_NUM,
			TEMP_C,
			TEMP_CLASS,
			RES_PRIO_CD,
			TESTER,
			HANDLER,
			HW_SET_ID,
			UTPI,
			IFNULL(TESTTIME,5.55) TESTTIME,
			IFNULL(INDEXTIME,1) INDEXTIME,
			TESTER_PROCEFF,
			HANDLER_PROCEFF,
			PTIME,
			YLD,
			TEST_PERC
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL
			WHERE MFG_PART_NUM IN ('".implode("','",$aryTemp)."')
			ORDER BY MFG_PART_NUM, IFNULL(EFF_START_DT,'2000-01-01'), IFNULL(EFF_END_DT,'2049-12-31'), SAP_RTE_ID, RTE_SEQ_NUM, RES_PRIO_CD;";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strMFRPN = $LineMain["MFG_PART_NUM"];
			$strStart = $LineMain["EFF_START_DT"];
			$strEnd = $LineMain["EFF_END_DT"];
			$strSRI = $LineMain["SAP_RTE_ID"];
			$intSeq = $LineMain["RTE_SEQ_NUM"];
			$intPri = $LineMain["RES_PRIO_CD"];

			$strSN = $LineMain["SITE_NUM"];
			$strRA = $LineMain["RES_AREA"];
			$intHWSID = $LineMain["HW_SET_ID"];

			$aryTemp = array(
				"UTPI"=>$LineMain["UTPI"],
				"TTPI"=>$LineMain["TESTTIME"],
				"INDX"=>$LineMain["INDEXTIME"],
				"WFR_IDX_PCT"=>1
			);

			$decSUTPH = ADI_SPRINT_UTPH_GET($aryTemp);

			$aryTemp = array(
				"RES_AREA"=>$LineMain["RES_AREA"],
				"STEP_NM"=>$LineMain["STEP_NM"],
				"SITE_NUM"=>$LineMain["SITE_NUM"],
				"TEMP_C"=>$LineMain["TEMP_C"],
				"TEMP_CLASS"=>$LineMain["TEMP_CLASS"],
				"TESTER"=>$LineMain["TESTER"],
				"HANDLER"=>$LineMain["HANDLER"],
				"HW_SET_ID"=>$LineMain["HW_SET_ID"],
				"UTPI"=>$LineMain["UTPI"],
				"TESTTIME"=>$LineMain["TESTTIME"],
				"INDEXTIME"=>$LineMain["INDEXTIME"],
				"TESTER_PROCEFF"=>$LineMain["TESTER_PROCEFF"],
				"HANDLER_PROCEFF"=>$LineMain["HANDLER_PROCEFF"],
				"STAGE_UPH"=>$LineMain["PTIME"],
				"TEST_PERC"=>$LineMain["TEST_PERC"],
				"YIELD"=>$LineMain["YLD"],
				"SPRINT_UTPH"=>round($decSUTPH,3),
				"UTPH"=>round($decSUTPH*$LineMain["TESTER_PROCEFF"]*$LineMain["HANDLER_PROCEFF"],3),
				"HARDWARE"=>array()
			);

			$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI]["SEQUENCE"][$intSeq][$intPri] = $aryTemp;

			$aryMap["SRI_MFRPN"][$strSRI] = $strMFRPN;

			$aryMap["HWSID_SN_RA_MFRPN_START_END_SRI_SEQ_PRI"][$intHWSID][$strSN][$strRA][] = array(
				"MFRPN"=>$strMFRPN,
				"START"=>$strStart,
				"END"=>$strEnd,
				"SAP_RTE_ID"=>$strSRI,
				"SEQ"=>$intSeq,
				"RES_PRIO_CD"=>$intPri
			);
		}
	}


	#2023-11-21 RM hardware
	$aryTemp = array();
	foreach(array_keys($aryMap["HWSID_SN_RA_MFRPN_START_END_SRI_SEQ_PRI"]) as $intHWSID) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intHWSID);
	}

	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			*
			FROM BODS_JDA_STAGE.ADI_HW_FLAT_STATIC
			WHERE HW_SET_ID IN (".implode(",",$aryTemp).");";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$aryHW = array();

		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSN = $LineMain["SITE_NUM"];
			$strRA = $LineMain["RES_AREA"];
			$intHWSID = $LineMain["HW_SET_ID"];

			foreach(array_keys($LineMain) as $strField) {
				if(strpos($strField,"_ERC") !== false) {
					$strType = str_replace("_ERC","",$strField);

					if(!is_null($LineMain[$strType])) {
						$strPA = "ALT";
						if($LineMain["IS_PRI"] == 1) {
							$strPA = "PRI";
						}
						$strName = $LineMain[$strType];

						$aryHW[$strSN][$strRA][$intHWSID][$strPA][$strType][$strName] = array(
							"RQ"=>$LineMain[$strType."_RQ"],
							"RC"=>$LineMain[$strType."_RC"],
							"ERC"=>$LineMain[$strType."_ERC"]
						);
					}
				}
			}
		}

		#some light ordering (PRI first)
		foreach(array_keys($aryHW) as $strSN) {
			foreach(array_keys($aryHW[$strSN]) as $strRA) {
				foreach($aryHW[$strSN][$strRA] as $intHWSID => $aryThis) {
					$aryThat = array();

					if(isset($aryThis["PRI"])) {
						foreach(array_keys($aryThis["PRI"]) as $strType) {
							foreach($aryThis["PRI"][$strType] as $strName => $aryThisTemp) {
								$aryThat[$strType]["PRI"][$strName] = $aryThisTemp;
							}
						}
					}

					if(isset($aryThis["ALT"])) {
						foreach(array_keys($aryThis["ALT"]) as $strType) {
							foreach($aryThis["ALT"][$strType] as $strName => $aryThisTemp) {
								$aryThat[$strType]["ALT"][$strName] = $aryThisTemp;
							}
						}
					}

					$aryHW[$strSN][$strRA][$intHWSID] = $aryThat;
				}
			}
		}

		if($bolDebug === true) {
			Print_R2($aryHW);
		}
		#die();

		#then write back for SN, RA, HWSID
		foreach(array_keys($aryHW) as $strSN) {
			foreach(array_keys($aryHW[$strSN]) as $strRA) {
				foreach($aryHW[$strSN][$strRA] as $intHWSID => $aryHWTemp) {
					if(isset($aryMap["HWSID_SN_RA_MFRPN_START_END_SRI_SEQ_PRI"][$intHWSID][$strSN][$strRA])) {
						foreach($aryMap["HWSID_SN_RA_MFRPN_START_END_SRI_SEQ_PRI"][$intHWSID][$strSN][$strRA] as $aryMapTemp) {
							$strMFRPN = $aryMapTemp["MFRPN"];
							$strStart = $aryMapTemp["START"];
							$strEnd = $aryMapTemp["END"];
							$strSRI = $aryMapTemp["SAP_RTE_ID"];
							$intSeq = $aryMapTemp["SEQ"];
							$intPri = $aryMapTemp["RES_PRIO_CD"];

							$aryMainTemp = $aryMain[$strMFRPN][$strStart][$strEnd][$strSRI]["SEQUENCE"][$intSeq][$intPri];

							$aryMainTemp["HARDWARE"] = $aryHWTemp;

							#dont need hw_set_id anymore
							unset($aryMainTemp["HW_SET_ID"]);

							$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI]["SEQUENCE"][$intSeq][$intPri] = $aryMainTemp;							
						}
					}
				}
			}
		}

		#Print_R2($aryMain);
		#die();

	}


	#this is kind of fucked up
	foreach(array_keys($aryMain) as $strMFRPN) {
		foreach(array_keys($aryMain[$strMFRPN]) as $strStart) {
			foreach(array_keys($aryMain[$strMFRPN][$strStart]) as $strEnd) {
				foreach($aryMain[$strMFRPN][$strStart][$strEnd] as $strSRI => $aryMainTemp) {
					$aryFlow = array("HOT"=>0,"ROOM"=>0,"COLD"=>0,"TOTAL"=>0);

					$arySeq = $aryMainTemp["SEQUENCE"];
					krsort($arySeq);

					#work backwards and yield each step up by the following
					#forcing integers and operating at 5 decimal points scale (as integer)
					$decUnits = 1;
					$decYield = 1;
					$decSHPU = 0;
					$decSHPUSource = 0;

					foreach(array_keys($arySeq) as $intSeq) {
						foreach($arySeq[$intSeq] as $intPri => $arySeqTemp) {
							$decYield = (int) $decYield * $arySeqTemp["YIELD"];
							$decUnits = (int) $decUnits * $arySeqTemp["YIELD"];
							
							$decTests = (int) $decUnits * $arySeqTemp["TEST_PERC"];
							$decSHPU += (int) $decTests / $arySeqTemp["SPRINT_UTPH"];

							if($arySeqTemp["STAGE_UPH"] != 1) {
								$decSHPUSource += (int) $decTests / $arySeqTemp["STAGE_UPH"];
							}

							$strTC = $arySeqTemp["TEMP_CLASS"];
							$aryFlow[$strTC] = round($aryFlow[$strTC] + $arySeqTemp["TEST_PERC"],5);
							$aryFlow["TOTAL"] = round($aryFlow["TOTAL"] + $arySeqTemp["TEST_PERC"],5);

							#only take min(pri)
							break;
						}
					}

					$aryMainTemp["OVERALL"] = array(
						#"YIELD"=>round($decYield/10000,3),
						"SPRINT_UTPH"=>round(1/$decSHPU,3),
						"SPRINT_SPU"=>round($decSHPU*3600,1),
						#"FGEPH"=>round($decYield/$decHPU,3),
						"FLOW"=>$aryFlow
					);

					if($decSHPUSource > 0) {
						$aryMainTemp["OVERALL"]["SPRINT_UTPH_SOURCE"] = round(1/$decSHPUSource,3);
						$aryMainTemp["OVERALL"]["SPRINT_SPU_SOURCE"] = round($decSHPUSource * 3600,1);
						#$aryMainTemp["OVERALL"]["FGEPH_SOURCE"] = round($decYield/$decHPUSource,3);
					}else{
						$aryMainTemp["OVERALL"]["UTPH_SOURCE"] = 1;
						$aryMainTemp["OVERALL"]["SPRINT_SPU_SOURCE"] = 1;
						#$aryMainTemp["OVERALL"]["FGEPH_SOURCE"] = 1;
					}

					$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI] = $aryMainTemp;
				}
			}
		}
	}


	#2023-09-21 RM no mention of dates, but temporarily pull calculated flow names
	$aryTemp = array();
	foreach(array_keys($aryMap["SRI_MFRPN"]) as $strSRI) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strSRI);
	}

	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			ASF.SAP_RTE_ID,
			ASF.BEST_OTLN,
			ASFM.ROUTE_ID
			FROM ADI_SAP_FLOW.ASF_MAIN ASF
			INNER JOIN ADI_SAP_FLOW.ASF_MATCH ASFM ON ASF.ASF_ID = ASFM.ASF_ID AND ASF.BEST_OTLN = ASFM.OTLN
			WHERE ASF.SAP_RTE_ID IN ('".implode("','",$aryTemp)."');";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSRI = $LineMain["SAP_RTE_ID"];
			$strMFRPN = $aryMap["SRI_MFRPN"][$strSRI];

			foreach(array_keys($aryMain[$strMFRPN]) as $strStart) {
				foreach(array_keys($aryMain[$strMFRPN][$strStart]) as $strEnd) {
					$aryMainTemp = $aryMain[$strMFRPN][$strStart][$strEnd][$strSRI];
					$strOTLN = $LineMain["BEST_OTLN"];

					$aryMainTemp["FLOW"] = array(
						$strOTLN=>array(
							"ROUTE_ID"=>$LineMain["ROUTE_ID"]
						)
					);

					$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI] = $aryMainTemp;
				}
			}
		}
	}

	foreach($aryMFRPN as $strMFRPN) {
		if(!isset($aryMain[$strMFRPN])) {
			$aryMain[$strMFRPN] = array();
		}
	}

	return $aryMain;
}


function BODS_JDA_ADI_SUS_GET($iConRLM,$aryMFRPN,$bolDebug=false) {
	$aryTemp = array();
	foreach($aryMFRPN as $strMFRPN) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strMFRPN);
	}

	$aryMain = array();
	$aryMap = array("SRI_FLOW"=>array(),"SRI_MFRPN"=>array());

	#2023-09-13 RM have to fake sample_rate
	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			MFG_PART_NUM,
			EFF_START_DT,
			EFF_END_DT,
			SAP_RTE_ID,
			RES_AREA,
			RTE_SEQ_NUM,
			STEP_NM,
			SITE_NUM,
			TEMP_C,
			TEMP_CLASS,
			TESTER,
			HANDLER,
			UTPI,
			IFNULL(TESTTIME,5.55) TESTTIME,
			IFNULL(INDEXTIME,1) INDEXTIME,
			TESTER_PROCEFF,
			HANDLER_PROCEFF,
			PTIME,
			YLD,
			TEST_PERC,
			REF_DOC_ID,
			REF_STEP_ID,
			REF_SETUP_ID
			FROM BODS_JDA_STAGE.ADI_ALLDB
			WHERE MFG_PART_NUM IN ('".implode("','",$aryTemp)."')
			ORDER BY MFG_PART_NUM, IFNULL(EFF_START_DT,'2000-01-01'), IFNULL(EFF_END_DT,'2049-12-31'), SAP_RTE_ID, RTE_SEQ_NUM;";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strMFRPN = $LineMain["MFG_PART_NUM"];
			$strStart = $LineMain["EFF_START_DT"];
			$strEnd = $LineMain["EFF_END_DT"];
			$strSRI = $LineMain["SAP_RTE_ID"];
			$intSeq = $LineMain["RTE_SEQ_NUM"];


			$aryTemp = array(
				"UTPI"=>$LineMain["UTPI"],
				"TTPI"=>$LineMain["TESTTIME"],
				"INDX"=>$LineMain["INDEXTIME"],
				"WFR_IDX_PCT"=>1
			);

			$decSUTPH = ADI_SPRINT_UTPH_GET($aryTemp);

			$decTPE = 1;
			$decHPE = 1;

			switch (true) {
				case is_null($LineMain["TESTER_PROCEFF"]) || trim($LineMain["TESTER_PROCEFF"]) == "":
					break;
				case $LineMain["TESTER_PROCEFF"] <= 0 || $LineMain["TESTER_PROCEFF"] > 1:
					break;
				default:
					$decTPE = $LineMain["TESTER_PROCEFF"];
					break;
			}

			switch (true) {
				case is_null($LineMain["HANDLER_PROCEFF"]) || trim($LineMain["HANDLER_PROCEFF"]) == "":
					break;
				case $LineMain["HANDLER_PROCEFF"] <= 0 || $LineMain["HANDLER_PROCEFF"] > 1:
					break;
				default:
					$decHPE = $LineMain["HANDLER_PROCEFF"];
					break;
			}

			if($decTPE <= $decHPE) {
				$decPE = $decTPE;
			}else{
				$decPE = $decHPE;
			}

			$aryTemp = array(
				"RES_AREA"=>$LineMain["RES_AREA"],
				"STEP_NM"=>$LineMain["STEP_NM"],
				"SITE_NUM"=>$LineMain["SITE_NUM"],
				"TEMP_C"=>$LineMain["TEMP_C"],
				"TEMP_CLASS"=>$LineMain["TEMP_CLASS"],
				"TESTER"=>$LineMain["TESTER"],
				"HANDLER"=>$LineMain["HANDLER"],
				"UTPI"=>$LineMain["UTPI"],
				"TESTTIME"=>$LineMain["TESTTIME"],
				"INDEXTIME"=>$LineMain["INDEXTIME"],
				"TESTER_PROCEFF"=>$LineMain["TESTER_PROCEFF"],
				"HANDLER_PROCEFF"=>$LineMain["HANDLER_PROCEFF"],
				"PROCEFF"=>round($decPE,3),
				"STAGE_UPH"=>$LineMain["PTIME"],
				"TEST_PERC"=>$LineMain["TEST_PERC"],
				"YIELD"=>$LineMain["YLD"],
				"SPRINT_UTPH"=>round($decSUTPH,3),
				"UTPH"=>round($decSUTPH*$decPE,3),
				"REF_DOC_ID"=>$LineMain["REF_DOC_ID"],
				"REF_STEP_ID"=>$LineMain["REF_STEP_ID"],
				"REF_SETUP_ID"=>$LineMain["REF_SETUP_ID"]
			);

			$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI]["SEQUENCE"][$intSeq] = $aryTemp;

			$aryMap["SRI_MFRPN"][$strSRI] = $strMFRPN;
		}
	}


	#this is kind of fucked up
	foreach(array_keys($aryMain) as $strMFRPN) {
		foreach(array_keys($aryMain[$strMFRPN]) as $strStart) {
			foreach(array_keys($aryMain[$strMFRPN][$strStart]) as $strEnd) {
				foreach($aryMain[$strMFRPN][$strStart][$strEnd] as $strSRI => $aryMainTemp) {
					$aryFlow = array("HOT"=>0,"ROOM"=>0,"COLD"=>0,"MULTI"=>0,"TOTAL"=>0);

					$arySeq = $aryMainTemp["SEQUENCE"];
					krsort($arySeq);

					#work backwards and yield each step up by the following
					#forcing integers and operating at 5 decimal points scale (as integer)
					$decUnits = 1;
					$decYield = 1;
					$decSHPU = 0;
					$decSHPUSource = 0;

					foreach($arySeq as $arySeqTemp) {
						$decYield = (int) $decYield * $arySeqTemp["YIELD"];
						$decUnits = (int) $decUnits * $arySeqTemp["YIELD"];
						
						$decTests = (int) $decUnits * $arySeqTemp["TEST_PERC"];
						$decSHPU += (int) $decTests / $arySeqTemp["SPRINT_UTPH"];

						if($arySeqTemp["STAGE_UPH"] > 1) {
							$decSHPUSource += (int) $decTests / $arySeqTemp["STAGE_UPH"];
						}

						$strTC = $arySeqTemp["TEMP_CLASS"];
						$aryFlow[$strTC] = round($aryFlow[$strTC] + $arySeqTemp["TEST_PERC"],5);
						$aryFlow["TOTAL"] = round($aryFlow["TOTAL"] + $arySeqTemp["TEST_PERC"],5);
					}

					if($decSHPU > 0) {
						$aryMainTemp["OVERALL"] = array(
							#"YIELD"=>round($decYield/10000,3),
							"SPRINT_UTPH"=>round(1/$decSHPU,3),
							"SPRINT_SPU"=>round($decSHPU*3600,1),
							#"FGEPH"=>round($decYield/$decHPU,3),
							"FLOW"=>$aryFlow
						);
					}else{
						$aryMainTemp["OVERALL"] = array(
							#"YIELD"=>round($decYield/10000,3),
							"SPRINT_UTPH"=>1,
							"SPRINT_SPU"=>1,
							#"FGEPH"=>round($decYield/$decHPU,3),
							"FLOW"=>$aryFlow
						);
					}

					if($decSHPUSource > 0) {
						$aryMainTemp["OVERALL"]["SPRINT_UTPH_SOURCE"] = round(1/$decSHPUSource,3);
						$aryMainTemp["OVERALL"]["SPRINT_SPU_SOURCE"] = round($decSHPUSource * 3600,1);
						#$aryMainTemp["OVERALL"]["FGEPH_SOURCE"] = round($decYield/$decHPUSource,3);
					}else{
						$aryMainTemp["OVERALL"]["UTPH_SOURCE"] = 1;
						$aryMainTemp["OVERALL"]["SPRINT_SPU_SOURCE"] = 1;
						#$aryMainTemp["OVERALL"]["FGEPH_SOURCE"] = 1;
					}

					$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI] = $aryMainTemp;
				}
			}
		}
	}


	#2023-09-21 RM no mention of dates, but temporarily pull calculated flow names
	$iConDev = Open_ILink("mxhdafot01l");

	$aryTemp = array();
	foreach(array_keys($aryMap["SRI_MFRPN"]) as $strSRI) {
		$aryTemp[] = mysqli_real_escape_string($iConDev,$strSRI);
	}

	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			ASF.SAP_RTE_ID,
			ASF.BEST_OTLN,
			ASFM.ROUTE_ID
			FROM ADI_SAP_FLOW.ASF_MAIN ASF
			INNER JOIN ADI_SAP_FLOW.ASF_MATCH ASFM ON ASF.ASF_ID = ASFM.ASF_ID AND ASF.BEST_OTLN = ASFM.OTLN
			WHERE ASF.SAP_RTE_ID IN ('".implode("','",$aryTemp)."');";
		$RSMain = ExecuteIQuery($strSQL,$iConDev);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSRI = $LineMain["SAP_RTE_ID"];
			$strMFRPN = $aryMap["SRI_MFRPN"][$strSRI];

			foreach(array_keys($aryMain[$strMFRPN]) as $strStart) {
				foreach(array_keys($aryMain[$strMFRPN][$strStart]) as $strEnd) {
					$aryMainTemp = $aryMain[$strMFRPN][$strStart][$strEnd][$strSRI];
					$strOTLN = $LineMain["BEST_OTLN"];

					$aryMainTemp["FLOW"] = array(
						$strOTLN=>array(
							"ROUTE_ID"=>$LineMain["ROUTE_ID"]
						)
					);

					$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI] = $aryMainTemp;
				}
			}
		}
	}
	
	mysqli_close($iConDev);

	
	foreach($aryMFRPN as $strMFRPN) {
		if(!isset($aryMain[$strMFRPN])) {
			$aryMain[$strMFRPN] = array();
		}
	}

	return $aryMain;
}


function BODS_JDA_ADI_GET($iConRLM,$aryMFRPN,$bolDebug=false) {
	$aryTemp = array();
	foreach($aryMFRPN as $strMFRPN) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strMFRPN);
	}

	$aryMain = array();
	$aryMap = array("SRI_FLOW"=>array(),"SRI_MFRPN"=>array());

	#2023-09-13 RM have to fake sample_rate
	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			MFG_PART_NUM,
			EFF_START_DT,
			EFF_END_DT,
			SAP_RTE_ID,
			RES_AREA,
			RTE_SEQ_NUM,
			STEP_NM,
			SITE_NUM,
			TEMP_C,
			TEMP_CLASS,
			TESTER,
			HANDLER,
			UTPI,
			IFNULL(TESTTIME,5.55) TESTTIME,
			IFNULL(INDEXTIME,1) INDEXTIME,
			TESTER_PROCEFF,
			HANDLER_PROCEFF,
			PTIME,
			YLD,
			TEST_PERC
			FROM BODS_JDA_STAGE.ADI_ALLDB
			WHERE MFG_PART_NUM IN ('".implode("','",$aryTemp)."')
			ORDER BY MFG_PART_NUM, IFNULL(EFF_START_DT,'2000-01-01'), IFNULL(EFF_END_DT,'2049-12-31'), SAP_RTE_ID, RTE_SEQ_NUM;";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strMFRPN = $LineMain["MFG_PART_NUM"];
			$strStart = $LineMain["EFF_START_DT"];
			$strEnd = $LineMain["EFF_END_DT"];
			$strSRI = $LineMain["SAP_RTE_ID"];
			$intSeq = $LineMain["RTE_SEQ_NUM"];


			$aryTemp = array(
				"UTPI"=>$LineMain["UTPI"],
				"TTPI"=>$LineMain["TESTTIME"],
				"INDX"=>$LineMain["INDEXTIME"],
				"WFR_IDX_PCT"=>1
			);

			$decSUTPH = ADI_SPRINT_UTPH_GET($aryTemp);

			$aryTemp = array(
				"RES_AREA"=>$LineMain["RES_AREA"],
				"STEP_NM"=>$LineMain["STEP_NM"],
				"SITE_NUM"=>$LineMain["SITE_NUM"],
				"TEMP_C"=>$LineMain["TEMP_C"],
				"TEMP_CLASS"=>$LineMain["TEMP_CLASS"],
				"TESTER"=>$LineMain["TESTER"],
				"HANDLER"=>$LineMain["HANDLER"],
				"UTPI"=>$LineMain["UTPI"],
				"TESTTIME"=>$LineMain["TESTTIME"],
				"INDEXTIME"=>$LineMain["INDEXTIME"],
				"TESTER_PROCEFF"=>$LineMain["TESTER_PROCEFF"],
				"HANDLER_PROCEFF"=>$LineMain["HANDLER_PROCEFF"],
				"STAGE_UPH"=>$LineMain["PTIME"],
				"TEST_PERC"=>$LineMain["TEST_PERC"],
				"YIELD"=>$LineMain["YLD"],
				"SPRINT_UTPH"=>round($decSUTPH,3),
				"UTPH"=>round($decSUTPH*$LineMain["TESTER_PROCEFF"]*$LineMain["HANDLER_PROCEFF"],3)
			);

			$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI]["SEQUENCE"][$intSeq] = $aryTemp;

			$aryMap["SRI_MFRPN"][$strSRI] = $strMFRPN;
		}
	}


	#this is kind of fucked up
	foreach(array_keys($aryMain) as $strMFRPN) {
		foreach(array_keys($aryMain[$strMFRPN]) as $strStart) {
			foreach(array_keys($aryMain[$strMFRPN][$strStart]) as $strEnd) {
				foreach($aryMain[$strMFRPN][$strStart][$strEnd] as $strSRI => $aryMainTemp) {
					$aryFlow = array("HOT"=>0,"ROOM"=>0,"COLD"=>0,"TOTAL"=>0);

					$arySeq = $aryMainTemp["SEQUENCE"];
					krsort($arySeq);

					#work backwards and yield each step up by the following
					#forcing integers and operating at 5 decimal points scale (as integer)
					$decUnits = 1;
					$decYield = 1;
					$decSHPU = 0;
					$decSHPUSource = 0;

					foreach($arySeq as $arySeqTemp) {
						$decYield = (int) $decYield * $arySeqTemp["YIELD"];
						$decUnits = (int) $decUnits * $arySeqTemp["YIELD"];
						
						$decTests = (int) $decUnits * $arySeqTemp["TEST_PERC"];
						$decSHPU += (int) $decTests / $arySeqTemp["SPRINT_UTPH"];

						if($arySeqTemp["STAGE_UPH"] > 1) {
							$decSHPUSource += (int) $decTests / $arySeqTemp["STAGE_UPH"];
						}

						$strTC = $arySeqTemp["TEMP_CLASS"];
						$aryFlow[$strTC] = round($aryFlow[$strTC] + $arySeqTemp["TEST_PERC"],5);
						$aryFlow["TOTAL"] = round($aryFlow["TOTAL"] + $arySeqTemp["TEST_PERC"],5);
					}

					$aryMainTemp["OVERALL"] = array(
						#"YIELD"=>round($decYield/10000,3),
						"SPRINT_UTPH"=>round(1/$decSHPU,3),
						"SPRINT_SPU"=>round($decSHPU*3600,1),
						#"FGEPH"=>round($decYield/$decHPU,3),
						"FLOW"=>$aryFlow
					);

					if($decSHPUSource > 0) {
						$aryMainTemp["OVERALL"]["SPRINT_UTPH_SOURCE"] = round(1/$decSHPUSource,3);
						$aryMainTemp["OVERALL"]["SPRINT_SPU_SOURCE"] = round($decSHPUSource * 3600,1);
						#$aryMainTemp["OVERALL"]["FGEPH_SOURCE"] = round($decYield/$decHPUSource,3);
					}else{
						$aryMainTemp["OVERALL"]["UTPH_SOURCE"] = 1;
						$aryMainTemp["OVERALL"]["SPRINT_SPU_SOURCE"] = 1;
						#$aryMainTemp["OVERALL"]["FGEPH_SOURCE"] = 1;
					}

					$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI] = $aryMainTemp;
				}
			}
		}
	}


	#2023-09-21 RM no mention of dates, but temporarily pull calculated flow names
	$iConDev = Open_ILink("mxhdafot01l");

	$aryTemp = array();
	foreach(array_keys($aryMap["SRI_MFRPN"]) as $strSRI) {
		$aryTemp[] = mysqli_real_escape_string($iConDev,$strSRI);
	}

	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			ASF.SAP_RTE_ID,
			ASF.BEST_OTLN,
			ASFM.ROUTE_ID
			FROM ADI_SAP_FLOW.ASF_MAIN ASF
			INNER JOIN ADI_SAP_FLOW.ASF_MATCH ASFM ON ASF.ASF_ID = ASFM.ASF_ID AND ASF.BEST_OTLN = ASFM.OTLN
			WHERE ASF.SAP_RTE_ID IN ('".implode("','",$aryTemp)."');";
		$RSMain = ExecuteIQuery($strSQL,$iConDev);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSRI = $LineMain["SAP_RTE_ID"];
			$strMFRPN = $aryMap["SRI_MFRPN"][$strSRI];

			foreach(array_keys($aryMain[$strMFRPN]) as $strStart) {
				foreach(array_keys($aryMain[$strMFRPN][$strStart]) as $strEnd) {
					$aryMainTemp = $aryMain[$strMFRPN][$strStart][$strEnd][$strSRI];
					$strOTLN = $LineMain["BEST_OTLN"];

					$aryMainTemp["FLOW"] = array(
						$strOTLN=>array(
							"ROUTE_ID"=>$LineMain["ROUTE_ID"]
						)
					);

					$aryMain[$strMFRPN][$strStart][$strEnd][$strSRI] = $aryMainTemp;
				}
			}
		}
	}
	
	mysqli_close($iConDev);

	
	foreach($aryMFRPN as $strMFRPN) {
		if(!isset($aryMain[$strMFRPN])) {
			$aryMain[$strMFRPN] = array();
		}
	}

	return $aryMain;
}


function ADI_SPRINT_UTPH_GET($aryInput) {
	if($aryInput["UTPI"] > 1) {
		$decSiteEff = 0.95;
		$decUTPH = ((3600 * $aryInput["UTPI"]) / ($aryInput["TTPI"] + $aryInput["INDX"])) * $aryInput["WFR_IDX_PCT"] * $decSiteEff;
		return round($decUTPH,3);
	}else{
		$decUTPH = (3600 / ($aryInput["TTPI"] + $aryInput["INDX"])) * $aryInput["WFR_IDX_PCT"];
		return round($decUTPH,3);
	}
}


function GET_DETAILS($iConRLM,$aryInput,$bolDebug, $aryRequest=null){
	// if ($aryRequest != null && $aryRequest['INPUT_TYPE'] != 'MFG_PART_NUM') {

		$newInput = [];
		$rawResult = [];
		$to_search = [];
		$inner_join_count = 0;
		$categories = ['ATOM_TESTER','ENGINEERING_TESTER','ATOM_HANDLER','ENGINEERING_HANDLER'];
		$where_statement = "";
		// $limiter = " LIMIT 0, 11";

		foreach ($aryInput as $key => $input) {

			$explode_str = explode("|",  str_replace("'", "", $input));
			$value = $explode_str[0];
			$type = $explode_str[1];

			$to_search[$type][] = $value;
		}

		foreach ($to_search as $key => $search) {

			$command = "";
			$condition = "";
			$operator = ' AND ';
			$to_match = (count($search) > 1) ? " IN ('".implode("','", $search)."')" : " = '".$search[0]."'";

			if (in_array($key, $categories)) {
				$inner_join_count++;
				switch ($key) {
					case 'ATOM_TESTER':
						$condition = "AM.TESTER_NEW".$to_match;
						break;

					case 'ENGINEERING_TESTER':
						$condition = "AM.TESTER".$to_match;
						break;

					case 'ATOM_HANDLER':
						$condition = "AM.HANDLER_NEW".$to_match;
						break;

					case 'ENGINEERING_HANDLER':
						$condition = "AM.HANDLER".$to_match;
						break;
				}
			}
			else {
				switch ($key) {
					case 'GENERIC':
						$condition = "AAA.GENERIC".$to_match;
						break;

					case 'MFG_PART_NUM':
						$condition = "AAA.MFG_PART_NUM".$to_match;
						break;
				}
			}

			reset($to_search);
			if ($key === key($to_search)) {
				$command = 'WHERE ';
			}

			end($to_search);
			if ($key === key($to_search)) {
				$operator = '';
			}
			
			$where_statement .= $command.$condition.$operator;
		}

		$inner_join_stmt = ($inner_join_count > 0) ? "INNER JOIN BODS_JDA_STAGE.ATOM_MASTER AM ON AAA.AM_HASH = AM.AM_HASH " : "";
		$query = "SELECT AAA.MFG_PART_NUM, AAA.GENERIC, AAA.RES_AREA FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA ".$inner_join_stmt.$where_statement."GROUP BY AAA.MFG_PART_NUM";
		$RSMain = ExecuteIQuery($query,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				array_push($newInput, $LineMain['MFG_PART_NUM']);
				array_push($rawResult, $LineMain);
		}
		// $aryInput = $newInput;
	// }
		return $rawResult;

}

//NEW SEARCH API - JCDP
function SEARCH_V2_ADI_GET($iConRLM,$aryInput,$bolDebug){
	
	$result = [
		["details" => [], "type" => "GENERIC", 				"value" => []],
		["details" => [], "type" => "MFG_PART_NUM", 		"value" => []],
		["details" => [], "type" => "ENGINEERING_TESTER", 	"value" => []],
		["details" => [], "type" => "ATOM_TESTER", 			"value" => []],
		["details" => [], "type" => "ENGINEERING_HANDLER", 	"value" => []],
		["details" => [], "type" => "ATOM_HANDLER", 		"value" => []]
	];

	$query = "SELECT
			'MFG_PART_NUM' as type,
			aaa.MFG_PART_NUM as value,
			aaa.RES_AREA as detail1,
			aaa.GENERIC as detail2
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA
			LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
			WHERE AAA.MFG_PART_NUM LIKE '%".mysqli_real_escape_string($iConRLM, $aryInput[0])."%' group by AAA.MFG_PART_NUM LIMIT 0, 11
			UNION
			SELECT 
			'GENERIC' as type,
			aaa.GENERIC as value,
			aaa.RES_AREA as detail1,
			aaa.GENERIC as detail2
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA
			LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
			WHERE AAA.GENERIC LIKE '%".mysqli_real_escape_string($iConRLM, $aryInput[0])."%' group by aaa.GENERIC LIMIT 0, 11
			UNION
			SELECT
			'ENGINEERING_TESTER' as type,
			aaa.TESTER as value,
			aaa.RES_AREA as detail1,
			aaa.GENERIC as detail2
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA
			LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
			WHERE AM.TESTER LIKE '%".mysqli_real_escape_string($iConRLM, $aryInput[0])."%' group by aaa.TESTER LIMIT 0, 11
			UNION
			SELECT 
			'ATOM_TESTER' as type,
			aaa.TESTER as value,
			aaa.RES_AREA as detail1,
			aaa.GENERIC as detail2
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA
			LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
			WHERE AAA.TESTER LIKE '%".mysqli_real_escape_string($iConRLM, $aryInput[0])."%' group by aaa.TESTER LIMIT 0, 11
			UNION
			SELECT
			'ENGINEERING_HANDLER' as type,
			aaa.HANDLER as value,
			aaa.RES_AREA as detail1,
			aaa.GENERIC as detail2
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA
			LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
			WHERE AM.HANDLER LIKE '%".mysqli_real_escape_string($iConRLM, $aryInput[0])."%' group by aaa.HANDLER LIMIT 0, 11
			UNION
			SELECT 
			'ATOM_HANDLER' as type,
			aaa.HANDLER as value,
			aaa.RES_AREA as detail1,
			aaa.GENERIC as detail2
			FROM BODS_JDA_STAGE.ADI_ALLDB_ALL AAA
			LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
			WHERE AAA.HANDLER LIKE '%".mysqli_real_escape_string($iConRLM, $aryInput[0])."%' group by aaa.HANDLER LIMIT 0, 11";

	$RSMain = ExecuteIQuery($query,$iConRLM);

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		switch ($LineMain['type']) {
			case 'GENERIC':
				$result[0]['type']  = $LineMain['type'];
				$result[0]['value'][] = $LineMain['value'];
				break;

			case 'MFG_PART_NUM':
				$result[1]['type']    = $LineMain['type'];
				$result[1]['value'][]   = $LineMain['value'];
				$result[1]['details'][] = ['MFG_PART_NUM' => $LineMain['value'], 'RES_AREA' => $LineMain['detail1'], 'GENERIC' => $LineMain['detail2']];
				break;

			case 'ENGINEERING_TESTER':
				$result[2]['type']  = $LineMain['type'];
				$result[2]['value'][] = $LineMain['value'];
				break;

			case 'ATOM_TESTER':
				$result[3]['type']  = $LineMain['type'];
				$result[3]['value'][] = $LineMain['value'];
				break;

			case 'ENGINEERING_HANDLER':
				$result[4]['type']  = $LineMain['type'];
				$result[4]['value'][] = $LineMain['value'];
				break;
			
			case 'ATOM_HANDLER':
				$result[5]['type']  = $LineMain['type'];
				$result[5]['value'][] = $LineMain['value'];
				break;
		}
	}

	// $result = [];
	// $dbArr = [
	// 	[
	// 		"database" => "BODS_JDA_STAGE",
	// 		"table" => "ADI_ALLDB_ALL",
	// 		"columns" => [
	// 			"GENERIC",
	// 			"MFG_PART_NUM",
	// 			"HANDLER"
	// 		]
	// 	],
	// 	[
	// 		"database" => "BODS_JDA_ADI",
	// 		"table" => "ATOM_MASTER",
	// 		"columns" => [
	// 			"TESTER",
	// 			"TESTER_NEW",
	// 			"HANDLER"
	// 		]
	// 	]
	// ];

	// if (count($aryInput) == 1 && strlen($aryInput[0]) >= 9) {
	// 	$query = "SELECT DISTINCT MFG_PART_NUM, RES_AREA, GENERIC FROM BODS_JDA_STAGE.ADI_ALLDB WHERE MFG_PART_NUM LIKE '".$aryInput[0]."%'";
	// 	$RSMain = ExecuteIQuery($query,$iConRLM);
	// 	$temp = [
	// 		"type" => 'MFG_PART_NUM',
	// 		"details" => [],
	// 		"value" => []
	// 	];
	// 	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
	// 		array_push($temp['value'], $LineMain['MFG_PART_NUM']);
	// 		array_push($temp['details'], $LineMain);
	// 	}

	// 	array_push($result, $temp);

	// 	$partnum_exists = $result[0]['value'];

	// 	if (count($partnum_exists) > 0) {
	// 		$get_product = PRODUCT_DATA_ADI_GET($iConRLM,$result[0]['value'],$bolDebug=false);
	// 		$result[0]['value'] = array();
	// 		foreach ($get_product as $key => $product) {
	// 			$result[0]['value'][] = [$key, $product];
	// 		}
	// 	}
	// }
	// else {



		// if (count($aryInput) > 0) {
		// 	foreach ($aryInput as $input) {
		// 		foreach ($dbArr as $db) {
		
		// 			$db_name = $db['database'];
		// 			$tbl_name = $db['table'];
			
		// 			if (count($db['columns']) > 0) {
		// 				foreach ($db['columns'] as $haystack) {
		// 					switch ($haystack) {
		// 						case 'TESTER':
		// 							$alias = "ENGINEERING_TESTER";
		// 							$other_column = "";
		// 							break;
		// 						case 'TESTER_NEW':
		// 							$alias = "ATOM_TESTER";
		// 							$other_column = "";
		// 							break;
		// 						case 'HANDLER':
		// 							$alias = ($tbl_name == "ADI_ALLDB_ALL") ? "ATOM_HANDLER" : "ENGINEERING_HANDLER";
		// 							$other_column = "";
		// 							break;
		// 						case 'MFG_PART_NUM':
		// 							$alias = $haystack;
		// 							$other_column = " ,RES_AREA, GENERIC ";
		// 							break;
		// 						default:
		// 							$alias = $haystack;
		// 							$other_column = "";
		// 							break;
		// 					}
		// 					$query = "SELECT ".$haystack." AS ".$alias.$other_column." FROM ".$db_name.".".$tbl_name." WHERE ".$haystack." LIKE '%".mysqli_real_escape_string($iConRLM, $input)."%'  GROUP BY ".$haystack." LIMIT 0, 11";
		// 					// $query = 'SELECT '.$haystack.' AS '.$alias.$other_column.' FROM '.$db_name.'.'.$tbl_name.' WHERE '.$haystack.' LIKE "'.mysqli_real_escape_string($iConRLM, $input).'%" GROUP BY '.$haystack ." limit 0, 11";
		// 					$RSMain = ExecuteIQuery($query,$iConRLM);
		// 					$temp = [
		// 						"type" => $alias,
		// 						"details" => [],
		// 						"value" => []
		// 					];
		// 					while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		// 						array_push($temp['value'], $LineMain[$alias]);
		// 						if ($haystack == "MFG_PART_NUM") {
		// 							// if ($LineMain['MFG_PART_NUM'] != null && $LineMain['RES_AREA'] != null && $LineMain['GENERIC'] != null) {
		// 								array_push($temp['details'], $LineMain);
		// 							// }
		// 						}
		// 					}
		// 					array_push($result, $temp);
		// 				}
		// 			}
		// 			else{
		// 				return 1;
		// 			}
			
		// 		}
		// 	}
	
		// 	$order = [
		// 		'GENERIC',
		// 		'MFG_PART_NUM',
		// 		'ENGINEERING_TESTER',
		// 		'ATOM_TESTER',
		// 		'ENGINEERING_HANDLER',
		// 		'ATOM_HANDLER'
		// 	];
			
		// 	usort($result, function ($a, $b) use ($order) {
		// 		$pos_a = array_search($a['type'], $order);
		// 		$pos_b = array_search($b['type'], $order);
		// 		return $pos_a - $pos_b;
		// 	});
		// }



		// $partnum_exists = $result[1]['value'];
	
		// if (count($partnum_exists) > 0) {
		// 	$get_product = PRODUCT_DATA_ADI_GET($iConRLM,$result[1]['value'],$bolDebug=false);
		// 	$result[1]['value'] = array();
		// 	foreach ($get_product as $key => $product) {
		// 		$result[1]['value'][] = [$key, $product];
		// 	}
		// }
	// }

	return $result;

}