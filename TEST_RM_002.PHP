<?php
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


#$aryField = array("SITE_NUM","RES_AREA","TESTER","HANDLER","TEMP_CLASS");
#$aryFieldInput = array("SITE_NUM","RES_AREA","TESTER","TEMP_CLASS");
#$aryCommon = array("MFG_PART_NUM"=>array("7366-5BRUCU-T0JURZ"));
#$aryCommon = array("MFG_PART_NUM"="TESTER"=>TESTER_GET($iConLocal));

/*
$aryTemp = array(
	"SITE_NUM"=>"ADGT",
	"RES_AREA"=>"ATE FT"
);
$strSHA1 = sha1(serialize($aryTemp));
echo $strSHA1."<BR>";
die();
*/


#2025-04-09 RM its not really helpful to use combo hash as key. just use numerics, less confusing as the hash combo might not yet exist when doing a GET
$aryCombo = array(
	array(
		"SITE_NUM"=>"ADGT",
		"RES_AREA"=>"ATE FT"
	)
);

$aryCommon = array(
	"TESTER"=>array("TS88_EXP_LT","TS88_EXP_GT"),
	"TEMP_CLASS"=>array("HOT","COLD"),
	"HANDLER"=>array("MT9928")
);

$aryOverride = array(
	"OEE"=>0.4
);

#$aryField = array("SITE_NUM","RES_AREA");
#$aryCommon = array("TESTER"=>array("TS88_EXP_LTI"));

#Print_R2($aryCommon);

$aryField = array(
	"ALL"=>array("SITE_NUM","RES_AREA","STEP_NM","TESTER","HANDLER","TEMP_CLASS"),
	"OEE"=>array("SITE_NUM","RES_AREA","TESTER","HANDLER","TEMP_CLASS"),
	"DED_SETUP"=>array("SITE_NUM","RES_AREA","STEP_NM","TESTER","HANDLER")
);




try{
	switch ($strOutputType) {
		case "OVERRIDE_PUT":
			$aryData = OVERRIDE_PUT($iConRLM,$aryField,$aryCommon,$aryCombo,$aryOverride,$bolDebug);
			break;
		case "AAA_GET":
			$aryData = AAA_GET($iConLocal,$aryField,$aryFieldInput,$aryCommon,$bolDebug);
			break;
	}

	$aryReturn = array(
		"STATUS"=>"SUCCESS",
		"MESSAGE"=>"",
		"DATA"=>$aryData
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




function AAA_GET($iCon,$aryField,$aryFieldInput,$aryCommon,$bolDebug=false) {
	#2025-03-24 RM there should be some priority system here
	#$aryControl = array("MFG_PART_NUM","SITE_NUM","RES_AREA","TESTER","HANDLER","TEMP_CLASS");

	#no unexpected fields in common. each field must be a non-zero count array
	$aryInvalid = array("FIELD"=>array(),"COMMON"=>array());

	$aryMessage = array();
	if(count($aryFieldInput) == 0) {
		$aryMessage[] = "NEED AT LEAST ONE FIELD INPUT";
	}else{
		foreach($aryFieldInput as $strField) {
			if(!in_array($strField,$aryField["ALL"])) {
				$aryInvalid["FIELD"][] = $strField;
			}
		}

		foreach(array_keys($aryCommon) as $strField) {
			switch (true) {
				case !in_array($strField,$aryControl):
					$aryInvalid["COMMON"][] = $strField;
					break;
				case !is_array($aryCommon[$strField]) || count($aryCommon[$strField]) == 0:
					$aryInvalid["COMMON"][] = $strField;
					break;
			}
		}

		foreach(array_keys($aryInvalid) as $strType) {
			#bad data, construct error message and throw exception
			if(count($aryInvalid[$strType]) > 0) {
				$aryInvalid[$strType] = array_unique($aryInvalid[$strType]);
				sort($aryInvalid[$strType]);

				$aryMessage[] = "INVALID ".$strType." FIELD INPUT (".implode(",",$aryInvalid[$strType]).")";
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
		FROM BODS_JDA_STAGE.ADI_ALLDB_ALL
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



function OVERRIDE_PUT($iConRLM,$aryField,$aryCommon,$aryCombo,$aryOVM,$bolDebug=false) {
	#as above, valid combo fields for OEE are SITE_NUM, RES_AREA, TESTER, HANDLER, TEMP_CLASS
	switch (true) { 
		case isset($aryOVM["OEE"]):
			$strOVType = "OEE";
			break;
		#others to follow
	}

	if(!isset($aryField[$strOVType])) {
		#throw some exception
	}
	$aryFieldThis = $aryField[$strOVType];

	$aryOVCID = OVERRIDE_COMBO_ID_GET($iConRLM,$aryFieldThis,$aryCommon,$aryCombo,$bolDebug);

	$intOVMID = OVERRIDE_MAIN_ID_GET($iConRLM,$aryOVM,$bolDebug);


	#now check ov_link (which has mfg_part_num optionally)
	foreach($aryOVCID as $intOVCID) {
		#MFG_PART_NUM can only be in $aryCommon
		if(isset($aryCommon["MFG_PART_NUM"])) {
			foreach($aryCommon["MFG_PART_NUM"] as $strMFRPN) {
				#have to specify whether or not to insert
				$intOVLID = OVERRIDE_LINK_ID_GET($iConRLM,$intOVCID,$intOVMID,$strMFRPN,true,$bolDebug);
			}
		}else{
			#must specify insert here
			$intOVLID = OVERRIDE_LINK_ID_GET($iConRLM,$intOVCID,$intOVMID,null,true,$bolDebug);
		}
	}


}




function OVERRIDE_LINK_ID_GET($iConRLM,$intOVCID,$intOVMID,$strMFRPN,$bolInsert,$bolDebug=false) {
	#this one is special - do not automatically insert like the others

	$strSQL = "
		SELECT
		OVL_ID
		FROM OVERRIDE_LINK
		WHERE OVC_ID = ".mysqli_real_escape_string($iConRLM,$intOVCID)."
		AND OVM_ID = ".mysqli_real_escape_string($iConRLM,$intOVMID);

	if(!is_null($strMFRPN)) {
		$strSQL .= " AND MFG_PART_NUM = '".mysqli_real_escape_string($iConRLM,$strMFRPN)."'";
	}else{
		$strSQL .= " AND MFG_PART_NUM IS NULL";
	}

	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		return $LineMain["OVL_ID"];
	}

	if($bolInsert === true) {
		$aryTemp = array(
			"OVC_ID"=>$intOVCID,
			"OVM_ID"=>$intOVMID
		);
		if(!is_null($strMFRPN)) {
			$aryTemp["MFG_PART_NUM"] = $strMFRPN;
		}

		foreach($aryTemp as $key => $value) {
			$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
		}

		$strSQL = "
			INSERT INTO
			OVERRIDE_LINK
			(".implode(",",array_keys($aryTemp)).")
			VALUES
			('".implode("','",$aryTemp)."');";
		ExecuteIQuery($strSQL,$iConRLM);
		return mysqli_insert_id($iConRLM);
	}

	return null;
}


function OVERRIDE_MAIN_ID_GET($iConRLM,$aryOVM,$bolDebug=false) {
	foreach($aryOVM as $key => $value) {
		if(is_numeric($value)) {
			$aryOVM[$key] = round($value,3);
			#make sure it is treated as number for serializing - deterministic
		}
	}
	ksort($aryOVM); #sort by key - deterministic
	$strSHA1 = sha1(serialize($aryOVM));
	

	$strSQL = "
		SELECT
		OVM_ID
		FROM OVERRIDE_MAIN_MASTER
		WHERE OVM_HASH_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	$intOVMID = null;
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intOVMID = $LineMain["OVM_ID"];
	}
	
	if(is_null($intOVMID)) {
		$strSQL = "
			INSERT INTO
			OVERRIDE_MAIN_MASTER
			(OVM_SHA1_VALUE)
			VALUES
			('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
		ExecuteIQuery($strSQL,$iConRLM);
		$intOVMID = mysqli_insert_id($iConRLM);

		$arySQLTemp = array();

		foreach($aryOVM as $key => $value) {
			$aryTemp = array(
				"OVM_ID"=>$intOVMID,
				"OVM_FIELD"=>$key,
				"OVM_VALUE"=>$value
			);
			foreach($aryTemp as $key => $value) {
				$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
			}
			$arySQLTemp[] = "('".implode("','",$aryTemp)."')";
		}

		if(count($arySQLTemp) > 0) {
			$strSQL = "
				INSERT INTO
				OVERRIDE_MAIN_MASTER
				(OVM_ID,OVM_FIELD,OVM_VALUE)
				VALUES
				".implode(",",$arySQLTemp).";";
			ExecuteIQuery($strSQL,$iConRLM);
		}
	}

	return $intOVMID;
}


function OVERRIDE_COMBO_ID_GET($iConRLM,$aryFieldThis,$aryCommon,$aryCombo,$bolDebug=false) {
	$aryReturn = array();

	
	
	#check for any fields in $aryCommon, $aryCombo besides MFG_PART_NUM which are not in $aryFieldThis
	#probably need custom logic for the "either tester or handler" must be provided for OEE
	#any fields in $aryFieldAll not in the incoming $aryCommon, $aryCombo are populated in array but forced to null
	
	$aryDiff1 = array_diff(array_keys($aryCommon),$aryFieldThis);
	$aryDiff2 = array_diff(array_keys($aryCombo),$aryFieldThis);
	$aryDiff3 = array_merge($aryDiff1,$aryDiff2);
	
	switch (true) {
		case count($aryDiff3) > 1:
			#throw exception
			break;
		case count($aryDiff3) == 1 && !in_array("MFG_PART_NUM",$aryDiff3):
			#throw exception
			break;
	}
	
	
	$aryValueBase = array();			
	#aryCombo will be single value per field
	foreach($aryCommon as $key => $value) {
		$aryValueBase[$strField] = $aryCombo[$strField];
	}
	#at this point $aryValueBase is:
	/*	$aryValueBase = array(
			"SITE_NUM"=>"ADGT",
			"RES_AREA"=>"ATE FT"
		);
	*/
	#$aryCommon will be array of values per field and requires cartesian product for n number of fields. this can be done with in-memory subqueries. difficult to do in code
	
	$arySQL = array(
		"SELECT"=>array(),
		"SUB"=>array()
	);
	
	foreach($aryCommon as $strField => $aryCommonValue) {
		#not mfg_part_num
		if($strField != "MFG_PART_NUM") {
			$arySQLTemp = array();
			foreach($aryCommonValue as $strValue) {
				$arySQLTemp[] = "SELECT '".mysqli_real_escape_string($iConRLM,$strValue)." ".$strField;
			}
			$strTable = "TBL_".$strField;
			
			$arySQL["SELECT"][] = $strCommon;
			$arySQL["SUB"][] = "(".implode(" UNION ",$arySQLTemp).") AS ".$strTable;
		}
	}			
	
	if(count($arySQL["SELECT"]) == 0) {
		#nothing in $aryCommon except maybe MFG_PART_NUM
		$aryValueTemp = $aryValueBase;
		ksort($aryValueTemp);
		$strSHA1 = sha1(serialize($aryValueTemp));
		$aryValue[$strSHA1] = $aryValueTemp;
	}else{
		$aryRaw = array();
		$strSQL = "SELECT DISTINCT ".implode(", ",$arySQL["SELECT"])." FROM ".implode(",",$arySQL["SUB"]).";";
		#$strSQL looks like this now (structured)
		
		/*
		SELECT DISTINCT 
		TEMP_CLASS, TESTER, HANDLER
		FROM 
		(
			SELECT 'HOT' TEMP_CLASS
			UNION
			SELECT 'COLD'
		) TBL_TEMP_CLASS,
		(
			SELECT 'TS88_EXP_LT' TESTER
			UNION
			SELECT 'TS88_EXP_GT'
		) TBL_TESTER,
		(
			SELECT 'MT9928' HANDLER
		) TBL_HANDLER
		*/

		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$aryRaw[] = $LineMain;
		}
	
		foreach($aryRaw as $aryRawTemp) {
			$aryValueTemp = $aryValueBase;
			foreach($aryRawTemp as $key => $value) {
				$aryValueTemp[$key] = $value;
			}
			ksort($aryValueTemp);
			$strSHA1 = sha1(serialize($aryValueTemp));
			$aryValue[$strSHA1] = $aryValueTemp;
		}
	}
	
	#for each combo, get its OVC_ID from override_combo if existing, or insert and return autoincrement otherwise
	foreach($aryValue as $strSHA1 => $aryValueTemp) {
		$strSQL = "SELECT OVC_ID FROM OVERRIDE_COMBO WHERE OVC_SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$intOVCID = null;
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intOVCID = $LineMain["OVC_ID"];
		}
						
		if(is_null($intOVCID)) {
			$aryTemp = array();
			$aryTemp["OVC_SHA1_VALUE"] = mysqli_real_escape_string($iConRLM,$strSHA1);
			foreach($aryValueTemp as $key => $value) {
				$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
			}
			
			$strSQL = "INSERT INTO OVERRIDE_COMBO (".implode(",",array_keys($aryTemp)).") VALUES ('".implode("','",$aryTemp)."');";
			ExecuteIQuery($strSQL,$iConRLM);
			$intOVCID = mysqli_insert_id($iConRLM);
		}
						
		$aryReturn[] = $intOVCID;
	}
	return $aryReturn;
}


function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}