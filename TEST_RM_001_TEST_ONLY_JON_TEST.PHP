<?php
header('Access-Control-Allow-Origin: *');
ini_set("display_errors",E_ALL);
define("_OEE_DECIMALS",3);
include_once("/var/www/html/MYSQLI_COMMON_FUNCTIONS.PHP");

$decStartTime = time()+microtime();
$bolDebug = false;
if(isset($_REQUEST["DEBUG"]) && $_REQUEST["DEBUG"] != "") {
	$bolDebug = true;
}


$iConLocal = Open_ILink("localhost");


/*
$aryField = array("TEMP_CLASS");
#$aryCommon = array();
#$aryCommon["MFG_PART_NUM"] = array("7366-5BRUCU-T0JURZ");
$aryCommon = array(
	"SITE_NUM"=>array("ADGT"),
	"TESTER"=>array("TS88_EXP_LT")
);
*/

$aryField = $_POST['main_fields'];
$aryCommon = $_POST['common_fields'];


// $aryField = array("MFG_PART_NUM", "SITE_NUM","RES_AREA", "TESTER", "HANDLER", "TEMP_CLASS");
// $aryCommon = array("MFG_PART_NUM"=>array("AD11/1012-0PCU-T0"));
#$aryCommon = array("MFG_PART_NUM"="TESTER"=>TESTER_GET($iConLocal));

#$aryField = array("SITE_NUM","RES_AREA");
#$aryCommon = array("TESTER"=>array("TS88_EXP_LTI"));

#Print_R2($aryCommon);



try{
	$aryOEE = OEE_AAA_GET($iConLocal,$aryField,$aryCommon,$bolDebug);

	#$aryOEE = OEE_OVERRIDE_APPLY($iConLocal,$aryOEE,$bolDebug);
	
	$aryReturn = array(
		"STATUS"=>"SUCCESS",
		"MESSAGE"=>"",
		"DATA"=>$aryOEE
	);
} catch (exception $e) {
	$aryReturn = array(
		"STATUS"=>"FAILURE",
		"MESSAGE"=>$e->getMessage(),
		"DATA"=>array()
	);
}

mysqli_close($iConLocal);


if(isset($_REQUEST["NO_JSON"]) && $_REQUEST["NO_JSON"] != "") {
	Print_R2($aryReturn);
}else{
	echo json_encode($aryReturn);
}


$decEndTime = time()+microtime();
if($bolDebug === true) {
	echo "<BR>".number_format($decEndTime-$decStartTime,3)." s.";
}


function OEE_AAA_GET($iCon,$aryField,$aryCommon,$bolDebug=false) {
	#2025-03-24 RM there should be some priority system here
	$aryControl = array("MFG_PART_NUM","SITE_NUM","RES_AREA","TESTER","HANDLER","TEMP_CLASS");

	#no unexpected fields in common, ignore. each field must be a non-zero count array
	$aryInvalid = array("FIELD"=>array(),"COMMON"=>array(),"IGNORE"=>array());

	$aryMessage = array();
	if(count($aryField) <= 0) {
		$aryMessage[] = "NEED AT LEAST ONE FIELD INPUT";
	}else{
		$aryDiff = array_diff($aryField,$aryControl);
		if(count($aryDiff) > 0) {
			$aryInvalid["FIELD"][] = $strField;
		}

		foreach(array_keys($aryCommon) as $strField) {
			if(!in_array($strField,$aryControl)) {
				$aryInvalid["COMMON"][] = $strField;
			}
			if(!is_array($aryCommon[$strField]) || count($aryCommon[$strField]) == 0) {
				$aryInvalid["COMMON"][] = $strField;
			}
		}

		foreach(array_keys($aryInvalid) as $strInput) {
			#bad data, construct error message and throw exception
			if(count($aryInvalid[$strInput]) > 0) {
				$aryInvalid[$strInput] = array_unique($aryInvalid[$strInput]);
				sort($aryInvalid[$strInput]);

				$aryMessage[] = "INVALID ".$strInput." INPUT (".implode(",",$aryInvalid[$strInput]).")";
			}
		}
	}

	if(count($aryMessage) > 0) {
		throw new exception(implode(", ",$aryMessage));
	}


	#continue if we did not exit	
	$aryFieldTemp = array();
	foreach($aryField as $strField) {
		switch (true) {
			/*
			case $strField == "TESTER" || $strField == "HANDLER":
				$aryFieldTemp[] = "REPLACE(".$strField.",'_ZEROCAP','') ".$strField;
				break;
			*/
			default:
				$aryFieldTemp[] = $strField;
		}
	}

	$arySQL = array(
		"SELECT"=>$aryFieldTemp,
		"WHERE"=>array("1 = 1")
	);
	
	foreach(array_keys($aryCommon) as $strField) {
		$aryTemp = array();
		foreach($aryCommon[$strField] as $strValue) {
			$aryTemp[] = mysqli_real_escape_string($iCon,$strValue);
		}
		
		switch (true) {
			case count($aryTemp) == 0:
				break;
			/*
			case $strField == "TESTER" || $strField == "HANDLER":
				$arySQL["WHERE"][] = "REPLACE(".$strField.",'_ZEROCAP','') IN ('".implode("','",$aryTemp)."')";
				break;
			*/
			default:
				$arySQL["WHERE"][] = $strField." IN ('".implode("','",$aryTemp)."')";
				break;
		}
	}
	
	
	#Print_R2($aryCommon);
	#Print_R2($arySQL);
	#die();

	$aryExec = array();

	$aryExec[] = "
		DROP TEMPORARY TABLE IF EXISTS
		BODS_JDA_STAGE.OEE_TEMPORARY;";

	$aryExec[] = "
		CREATE TEMPORARY TABLE
		BODS_JDA_STAGE.OEE_TEMPORARY
		(
			INDEX IDX1 (SHA1_ROW_VALUE)
		)
		SELECT
		SHA1(
			CONCAT_WS(
				'|',
				".implode(",",$arySQL["SELECT"])."
			)
		) SHA1_ROW_VALUE,
		".implode(",",$arySQL["SELECT"]).",
		IFNULL(OEE,LEAST(TESTER_PROCEFF,HANDLER_PROCEFF)) OEE,
		AAA_ID
		#FROM BODS_JDA_ADI.V_AAA
		FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL
		WHERE ".implode(" AND ",$arySQL["WHERE"]).";";

	$aryExec[] = "
		ALTER TABLE
		BODS_JDA_STAGE.OEE_TEMPORARY
		ADD PRIMARY KEY (AAA_ID);";

	foreach($aryExec as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		ExecuteIQuery($strSQL,$iCon);
	}

	#die();

	$strSQL = "
		SELECT DISTINCT
		SHA1_ROW_VALUE,
		".implode(",",$arySQL["SELECT"])."
		FROM BODS_JDA_STAGE.OEE_TEMPORARY
		ORDER BY ".implode(",",$arySQL["SELECT"]).";";
	$RSMain = ExecuteIQuery($strSQL,$iCon);
	$aryOEE = array();
	
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryTemp = $LineMain;
		unset($aryTemp["SHA1_ROW_VALUE"]);

		$strSHA1 = $LineMain["SHA1_ROW_VALUE"];

		if(!isset($aryOEE[$strSHA1])) {
			$aryOEE[$strSHA1] = array(
				"COMBO"=>$aryTemp,
				"OEE"=>array()
			);
		}
	}
	
	#get OEE range across AAA_ID for each combo (each combo is represented by hash)
	foreach(array_keys($aryOEE) as $strSHA1) {
		$strSQL = "
			SELECT 
			ROUND(MAX(val),"._OEE_DECIMALS.") AS OEE_MAX,
			ROUND(MIN(val),"._OEE_DECIMALS.") AS OEE_MIN,
			ROUND(AVG(val),"._OEE_DECIMALS.") AS OEE_AVG,
			ROUND(AVG(CASE WHEN rn IN (FLOOR((@tr+1)/2), FLOOR((@tr+2)/2)) THEN val END),"._OEE_DECIMALS.") AS OEE_MEDIAN
			FROM 
			(
				SELECT 
				val, 
				@rownum := @rownum + 1 AS rn,
				@tr := @rownum AS tr
				FROM
				(
					SELECT
					OEE AS val
					FROM BODS_JDA_STAGE.OEE_TEMPORARY OEE_TEMP
					WHERE SHA1_ROW_VALUE = '".mysqli_real_escape_string($iCon,$strSHA1)."'
				) d,
				(SELECT @rownum:=0) r
				ORDER BY val
			) c;";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iCon);
		$LineMain = mysqli_fetch_assoc($RSMain);

		$aryOEE[$strSHA1]["OEE"] = array(
			"MAX"=>$LineMain["OEE_MAX"],
			"MIN"=>$LineMain["OEE_MIN"],
			"AVG"=>$LineMain["OEE_AVG"],
			"MEDIAN"=>$LineMain["OEE_MEDIAN"],
			"OVERRIDE"=>null
		);

	}
	#die();

	$strSQL = "
		DROP TEMPORARY TABLE IF EXISTS
		BODS_JDA_STAGE.OEE_TEMPORARY;";
	ExecuteIQuery($strSQL,$iCon);

	#can decide whether or not to get rid of AAA_ID. might be useful later, not sure
	return $aryOEE;
}


function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}