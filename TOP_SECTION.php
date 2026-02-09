<?php
header('Access-Control-Allow-Origin: *');
include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("../TEST/ADI_FUNCTIONS_022.PHP");
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

$aryIO = array([
    'INPUT_TYPE' => [
        'MFG_PART_NUM'
    ],
    'OUTPUT_TYPE' => [
        'BODS_JDA_ADI'
    ]
]);

// $checkRequest = REQUEST_VALIDATE($aryIO, $aryRequest);

// if ($checkRequest === true) {
    
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

    $iConRLM = Open_ILink("localhost");

    #2025-10-03 RM changed to BRAIN_ADI_ALLDB_ALL
    $retrieve_data 			= BODS_JDA_ADI_STAGE_V2($iConRLM,$aryInput);
	$retrieve_prio 			= ADD_SETUP_PRIO($iConRLM, $retrieve_data);
    $retrieve_prim_setup 	= GET_PRIMARY_SETUP($iConRLM, $aryInput);
    $retrieve_prim_pending 	= GET_PRIMARY_SETUP($iConRLM, null);
    $retrieve_prim_former 	= GET_PRIMARY_SETUP_FORMER($iConRLM, $retrieve_data);
    // $retrieve_prim_hardware = GET_PRIMARY_HARDWARE($aryInput);
    #2025-11-18 RM V3. was dropping info when condensing with " or "
    $retrieve_hardware 		= GET_HARDWARE_RECORD_V3($iConRLM, $retrieve_data);
	$retrieve_steps 		= GET_UNIQUE_RECORD($retrieve_data, "step");
	// $retrieve_oee 			= GET_OEE($iConRLM, $aryInput);
	$retrieve_oee_override 	= GET_OEE_OVERRIDE($iConRLM, $retrieve_data);
	$retrieve_unplannable 	= GET_UNPLANNABLE($iConRLM, $retrieve_data);
	$retrieve_dedication 	= GET_DEDICATION($iConRLM, $aryInput);

    mysqli_close($iConRLM);

// }
// else {
//     print_r("error");
// }


function BODS_JDA_ADI_STAGE_V2($iConRLM, $aryInput){

    #2025-02-28 RM fixed this. you cant do a str_replace on an array. and anyway, should be using mysqli_real_escape_string. and always check for > 0 elements
    #connections should not be made within a function. impossible to track open/close from a flow perspective
    #note that there was no mysqli_close.  always close your connections - now in main flow
    #also the grouping is questionable.  added tester and handler at a minimum.
    #also you cant assume everything will get a entry in atom_master.  ADTH for example will never have a conversion while running on camstar (opcenter will but not yet implemented)
    #AM.TESTER IS NOT NULL AND AM.HANDLER IS NOT NULL
    #dont do group_concat. it can be useful in very specific circumstances but not for general use
    #yes, hw_set_id is integer which is probably ok but its a bad habit because:
    #a) you can not guarantee the size of group_concat will always fit in the concat size set, and
    #b) any commas in the values returned breaks the result, and 
    #c) you still need to parse them out into array anyway. so just save a multi-dimensional array to begin with, and 
    #d) the group by is questionable as mentioned above, and its only necessary if youre doing a group_concat to begin with, and 
    #e) youre already retrieving AAA.HW_SET_ID as well as in the group_concat, so predicting the result is unnecessarily complex.
    #added ifnull for eng_tester, eng_handler when no transformation was performed

	// LEAST(AAA.TESTER_PROCEFF, AAA.HANDLER_PROCEFF) AS OEE,
    //         AAA.PTIME * LEAST(AAA.TESTER_PROCEFF, AAA.HANDLER_PROCEFF) AS UPH,
// LEFT JOIN BODS_JDA_STAGE.ATOM_MASTER AM ON AAA.AM_HASH = AM.AM_HASH

	#$input = str_replace("'", "", $aryInput);
    $aryTemp = array();
    foreach($aryInput as $strInput) {
    	$aryTemp[] = mysqli_real_escape_string($iConRLM,$strInput);
    }

    $strSQL = "
        SELECT 
        	AAA.AAA_ID,
            AAA.MFG_PART_NUM AS PART,
            AAA.GENERIC,
            AAA.SITE_NUM AS SITE,
			AAA.RES_AREA AS TEST_TYPE,
            AAA.SAP_RTE_ID AS RTE_ID,
            AAA.STEP_NM AS STEP,
			AAA.TEMP_C AS TEMP,
			AAA.TEMP_CLASS,
            AAA.TEST_PERC AS QC_FACTOR,
            #AAA.PTIME AS SPRINT_UPH,
			IFNULL(AAA.OEE,LEAST(AAA.TESTER_PROCEFF,AAA.HANDLER_PROCEFF)) AS OEE,
			#IFNULL(AAA.UTPH,AAA.PTIME * LEAST(AAA.TESTER_PROCEFF,AAA.HANDLER_PROCEFF)) AS UPH,
			AAA.TESTTIME AS TEST_TIME,
			AAA.UTPI,
			AAA.INDEXTIME AS INDEX_TIME,
			IFNULL(AAA.TTPI,AAA.UTPI * TESTTIME) TTPI,
			IFNULL(AAA.INDX,AAA.UTPI * INDEXTIME) INDX,
			CASE TRUE
				WHEN TD_EFF IS NOT NULL THEN TD_EFF
				WHEN UTPI > 1 THEN 0.95
				ELSE 1
			END TD_EFF,
			IFNULL(AAA.WFR_IDX_PCT,1) WFR_IDX_PCT,
			NULL AS HASH,
			NULL AS HASH_ERR,
			IFNULL(AM.TESTER,AAA.TESTER) AS ENGR_TESTER,
			IFNULL(AM.HANDLER,AAA.HANDLER) AS ENGR_HANDLER,
			IFNULL(AM.TESTER_NEW,AAA.TESTER) AS ATOM_TESTER,
			IFNULL(AM.HANDLER_NEW,AAA.HANDLER) AS ATOM_HANDLER,
			AAA.RES_PRIO_CD AS PRIO_CD,
			AAA.ATTR_SET_ID AS ATTR_ID,
			AAA.HW_SET_ID,
			AAA.RES_SET_ID,
			AAA.ATOM_MASTER_ID
        FROM BODS_JDA_STAGE.BRAIN_ADI_ALLDB_ALL AAA
		LEFT JOIN BODS_JDA_ADI_EXPORT.ATOM_MASTER AM ON AAA.ATOM_MASTER_ID = AM.AM_ID
        WHERE AAA.MFG_PART_NUM IN ('".implode("','",$aryTemp)."')";
    $RSMain = ExecuteIQuery($strSQL,$iConRLM);

    $aryTry = array("ENGR_TESTER","ENGR_HANDLER","ATOM_TESTER","ATOM_HANDLER");
    $aryRX = array(
    	"PATTERN"=>array(
	    	"/_ZEROCAP/",
			"/_FIX[0-9]+/"
		),
		"REPLACE"=>array(
			"",
			""
		)
    );
    
    $aryReturn = array();
    while ($aryRow = mysqli_fetch_assoc($RSMain)) {
    	if($aryRow["TTPI"] > 0 && $aryRow["INDX"] > 0) {
    		$aryRow["SPRINT_UPH"] = (3600 * $aryRow["UTPI"] * $aryRow["TD_EFF"] * $aryRow["WFR_IDX_PCT"]) / ($aryRow["TTPI"] + $aryRow["INDX"]);
    		$aryRow["UPH"] = $aryRow["SPRINT_UPH"] * $aryRow["OEE"];
    	}else{
    		$aryRow["SPRINT_UPH"] = 1;
    		$aryRow["UPH"] = 1;
    	}
    	
		#print_r($aryRow);
    	
		foreach($aryTry as $strTry) {
			if(isset($aryRow[$strTry])) {
				#so you can see before/after if you care
				$strValue = $aryRow[$strTry];

				$strValue = preg_replace($aryRX["PATTERN"],$aryRX["REPLACE"],$strValue);

				$aryRow[$strTry] = $strValue;
			}
		}

		$aryRow['DB_TABLE'] = 'ADI_ALLDB_ALL';
        array_push($aryReturn,$aryRow);
        // print_r($row);
    }

    return $aryReturn;
}

function GET_PRIMARY_SETUP($iConRLM, $aryInput){

	$array_main = array();
	$hash_arr = array();
	$temp_arr = array();
	if(count($aryInput) > 0){
		$condition = ($aryInput != null) ? 'WHERE MFG_PART_NUM IN ("'.implode('","', $aryInput).'")' : 'WHERE STATUS = "PENDING"';
		$query = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP '.$condition.' ORDER BY CREATED_AT ASC';
		$result = ExecuteIQuery($query,$iConRLM);
	
		while($row = mysqli_fetch_assoc($result)){
			array_push($temp_arr, [
				$row['MFG_PART_NUM'],
				$row['SITE_NUM'],
				$row['STEP_NM'],
				$row['HASH'],
				$row['SAP_RTE_ID'],
				$row['HW_SET_ID'],
				$row['STATUS'],
				$row['TESTER'],
				$row['HANDLER'],
				$row['CREATED_AT'],
				$row['ID'],
				$row['CREATED_BY'],
				$row['SCHEDULE'],
				$row['RES_SET_ID'],
				$row['ATOM_MASTER_ID'],
				$row['DED_ID'],
				$row['TO_REMOVE']
			]);
		}
	}
    return $temp_arr;
}

function GET_PRIMARY_SETUP_FORMER($iConRLM, $aryInput){
	$temp_arr = array();
	$aryTemp = array();

	// foreach ($aryInput as $key => $input) {
	// 	array_push($aryTemp, mysqli_real_escape_string($iConRLMm, $input));
	// }
	
	if (count($aryTemp) > 0) {
		$query = "SELECT APS.MFG_PART_NUM, APS.HASH, APS.HW_SET_ID FROM TEST.ADI_PRIMARY_SETUP 
					WHERE APS.MFG_PARTNUM IN (".implode(",", $aryInput).")
					AND APS.STATE LIKE 'DELETED_%' ORDER BY DELETED_AT DESC";
		$result = ExecuteIQuery($query,$iConRLM);
		while($row = mysqli_fetch_assoc($result)){
			array_push($temp_arr, $row);
		}
	}
	return $temp_arr;
}


function GET_HARDWARE_RECORD_V3($iConRLM,$aryAAA) {
    #2025-11-18 RM this is missing quantity_required, name filters against hw_nonboard_hms because of the early grouping of " or "
    #need to implode by " or " AFTER retrieving relevant data
    #also, you are filtering for ADGT FT but have the data available in AAA already...yes it is a bit of extra work but it sets you up forever
	#2025-11-18 RM why are you renaming fields?!?!! SITE_NUM is SITE_NUM. please dont call it SITE. RES_AREA is RES_AREA. please dont call it TEST_TYPE. thats a completely different thing

	#$aryMap["HWSID_NAME_RQ"] = array();
	$aryMap["HWSID_SN_RA"] = array();
	foreach($aryAAA as $aryAAATemp) {
		$intHWSID = $aryAAATemp["HW_SET_ID"];
		$strSN = $aryAAATemp["SITE"];

		$aryMap["HWSID_SN_RA"][$intHWSID][$strSN][] = $aryAAATemp["TEST_TYPE"];
	}

	$aryTemp = array();
	foreach(array_keys($aryMap["HWSID_SN_RA"]) as $intHWSID) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intHWSID);
	}

	$aryHW = array();
	$aryMap["NAME_HWSID_TYPE"] = array();

	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			HW_SET_ID,
			HW_TYPE,
			HW_NM,
			REQUIRED_QTY,
			0 AS SORT_ORDER
			FROM BODS_JDA_ADI_EXPORT.HARDWARE_SET
			WHERE HW_SET_ID IN (".implode(",",$aryTemp).")
			UNION
			SELECT
			HW_SET_ID,
			HW_TYPE,
			HW_NM,
			REQUIRED_QTY,
			1 AS SORT_ORDER
			FROM BODS_JDA_ADI_EXPORT.HARDWARE_ALTERNATES
			WHERE HW_SET_ID IN (".implode(",",$aryTemp).")
			ORDER BY HW_SET_ID, HW_TYPE, HW_NM, SORT_ORDER;";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strName = $LineMain["HW_NM"];
			$intHWSID = $LineMain["HW_SET_ID"];
			$strType = $LineMain["HW_TYPE"];

			#sort order only exists for ordering. not important
			$aryHW[$intHWSID][$strType][$strName] = array(
				"REQUIRED_QTY"=>$LineMain["REQUIRED_QTY"],
				"TOTAL_QTY"=>0
			);

			#2025-11-18 RM pretty sure type is unique at this level
			$aryMap["NAME_HWSID_TYPE"][$strName][$intHWSID][] = $strType;
		}
	}


	#2025-11-18 RM when dealing with a potentially unknown set of inputs, better to just open the query filter (reduce DB load) and keep only what you need from the results
	$aryTemp = array();
	foreach(array_keys($aryMap["NAME_HWSID_TYPE"]) as $strName) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strName);
	}
	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			PRR.SITE_NUM,
			PRR.RES_AREA,
			REF.HW_NM,
			MIN(HMS.AVAIL_QTY) AVAIL_QTY,
			MIN(HMS.TOTAL_QTY) TOTAL_QTY
			FROM BRAIN.HW_NONBOARD_HMS HMS
			INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
			INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
			WHERE REF.HW_NM IN ('".implode("','",$aryTemp)."')
			GROUP BY SITE_NUM, RES_AREA, HW_NM;";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strName = $LineMain["HW_NM"];
			$strSN = $LineMain["SITE_NUM"];
			$strRA = $LineMain["RES_AREA"];

			if(isset($aryMap["NAME_HWSID_TYPE"][$strName])) {
				foreach(array_keys($aryMap["NAME_HWSID_TYPE"][$strName]) as $intHWSID) {
					if(isset($aryMap["HWSID_SN_RA"][$intHWSID][$strSN]) && in_array($strRA,$aryMap["HWSID_SN_RA"][$intHWSID][$strSN])) {
						foreach($aryMap["NAME_HWSID_TYPE"][$strName][$intHWSID] as $strType) {
							$aryHWTemp = $aryHW[$intHWSID][$strType][$strName];
							$aryHWTemp["TOTAL_QTY"] = $LineMain["TOTAL_QTY"];
							$aryHW[$intHWSID][$strType][$strName] = $aryHWTemp;
						}
					}
				}
			}
		}
	}

	#2025-11-18 RM now figure out the condensed display
	#looks like you are returning total_capacity as required_qty...?

	$aryReturn = array();
	foreach(array_keys($aryHW) as $intHWSID) {
		foreach(array_keys($aryHW[$intHWSID]) as $strType) {
			$intQty = 0;			
			foreach($aryHW[$intHWSID][$strType] as $strName => $aryHWTemp) {
				$intQty += $aryHWTemp["TOTAL_QTY"];
			}
			$strNameTemp = implode(" or ",array_keys($aryHW[$intHWSID][$strType]));

			$aryReturn[$intHWSID][$strType][] = array(
				"HW_NM"=>$strNameTemp,
				"REQUIRED_QTY"=>$intQty
			);
		}
	}

	return $aryReturn;
}


function GET_HARDWARE_RECORD_V2($iConRLM,$aryAAA) {
	#2025-02-28 RM do not open mysqli connections inside functions. connection opening/closing are extremely expensive
	#there was no mysqli_close here. this is another reason why its better to do all connection open/close in the main flow
	#no need to parse an array. you already were retrieving hw_set_id by itself, so just use that

	$aryHW = array();
	foreach($aryAAA as $aryAAATemp) {
		$intHWSID = $aryAAATemp["HW_SET_ID"];
		$aryHW[$intHWSID] = array();
		#simply writing over the key is faster than checking existence
	}

	#2025-02-28 RM always use mysqli_real_escape_string
	$aryTemp = array();
	foreach(array_keys($aryHW) as $intHWSID) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intHWSID);
	}

	#2025-02-28 RM always check for > 0 elements before sending to mysql. if empty then dont send
	#dont need string containers, this is an INT
	#by the way, you are retrieving from both hardware_set and hardware_alternates without recording them as being primary/alternate.
	#you cannot assume that any hw_name after the first for a hw_set_id + hw_type combination, will be alternate.
	#hardware_set can have multiple lineitems for a specific hw_set_id and hw_type.  you will need to address this later and set up clear delineation for pri/alt
	#i did not set the delineation here because im sure its being used down the line and i did not want to break that, yet.
    

	if(count($aryTemp) > 0) {

		$hw_names = [];
		$strSQL = "SELECT
		HW_SET_ID,
		HW_TYPE,
		GROUP_CONCAT(DISTINCT HW_NM ORDER BY SORT_ORDER SEPARATOR ' or ') AS HW_NM
		FROM
		(
			SELECT
			HW_SET_ID,
			HW_TYPE,
			HW_NM,
			0 AS SORT_ORDER
			FROM BODS_JDA_ADI_EXPORT.HARDWARE_SET
			WHERE HW_SET_ID IN (".implode(",", $aryTemp).")
			UNION
			SELECT
			HW_SET_ID,
			HW_TYPE,
			HW_NM,
			1 AS SORT_ORDER
			FROM BODS_JDA_ADI_EXPORT.HARDWARE_ALTERNATES
			WHERE HW_SET_ID IN (".implode(",", $aryTemp).")
		) HW
		GROUP BY HW_SET_ID, HW_TYPE";

		// $strSQL = "
		// 	SELECT 
		// 	HW_SET_ID,
		// 	HW_TYPE,
		// 	HW_NM,
		// 	REQUIRED_QTY
		// 	FROM BODS_JDA_ADI_EXPORT.HARDWARE_SET
		// 	WHERE HW_SET_ID IN (".implode(",",$aryTemp).")
		// 	UNION
		// 	SELECT
		// 	HW_SET_ID,
		// 	HW_TYPE,
		// 	HW_NM,
		// 	REQUIRED_QTY
		// 	FROM BODS_JDA.HARDWARE_ALTERNATES
		// 	WHERE HW_SET_ID IN (".implode(",",$aryTemp).");";

		$RSMain = ExecuteIQuery($strSQL,$iConRLM);

		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intHWSID = $LineMain["HW_SET_ID"];
			$strType = $LineMain["HW_TYPE"];

			#this doesnt work yet
			$aryHW[$intHWSID][$strType][] = array(
				"HW_NM"=>$LineMain["HW_NM"],
				"REQUIRED_QTY"=>0
			);

			array_push($hw_names, mysqli_real_escape_string($iConRLM,$LineMain["HW_NM"]));
		}

		
		$get_hw_cap = "SELECT
		PRR.SITE_NUM,
		PRR.RES_AREA,
		REF.HW_NM,
		MIN(HMS.AVAIL_QTY) AVAIL_QTY,
		MIN(HMS.TOTAL_QTY) TOTAL_QTY
		FROM BRAIN.HW_NONBOARD_HMS HMS
		INNER JOIN BRAIN.HW_NONBOARD_PRR PRR ON HMS.SITE_NUM = PRR.SITE_NUM AND HMS.RES_AREA = PRR.RES_AREA
		INNER JOIN BRAIN.HW_NONBOARD_REF REF ON PRR.REF_SETUP_ID = REF.REF_SETUP_ID AND HMS.HW_NM = REF.HW_NM
		WHERE PRR.SITE_NUM = 'ADGT'
		AND HMS.RES_AREA = 'ATE FT'
		AND REF.HW_NM IN ('".implode("','",$hw_names)."')
		GROUP BY SITE_NUM, RES_AREA, HW_NM;";

		$get_hw_cap_res = ExecuteIQuery($get_hw_cap, Open_ILink("MXHTAFOT01L"));
		while ($row = mysqli_fetch_assoc($get_hw_cap_res)) {
			
			$name = $row['HW_NM'];
			$capacity = $row['TOTAL_QTY'];

			// Loop through the outer ID (e.g., 61518)
			foreach ($aryHW as $id => &$sections) {

				// Loop through each section (CNTCR, DUTBD, etc.)
				foreach ($sections as $section => &$items) {

					// Each section can have multiple hardware items
					foreach ($items as &$item) {

						if ($item['HW_NM'] == $name) {
							// Found a match → update value
							$item['REQUIRED_QTY'] = $capacity;
						}

					}
					unset($item); // clean reference
				}
				unset($items);
			}
			unset($sections);

		}
	}

	return $aryHW;
}

function GET_UNIQUE_RECORD($part_data, $type){
	$temp_arr = [];
	foreach ($part_data as $value) {
		switch ($type) {
			case 'step':
				array_push($temp_arr, $value['STEP']);
				break;
		}	
	}
	return array_values(array_unique($temp_arr));
}

function GET_OEE($iConRLM, $part_number, $eff_start_date = null){
	$location = $res_area = $tester = $handler = $temp_class = $oee = $product = $cell = array();
	if (count($part_number) > 0) {
		$query = 'SELECT RES_AREA, TESTER, HANDLER, TEMP_CLASS, LEAST(TESTER_PROCEFF, HANDLER_PROCEFF) AS OEE FROM BODS_JDA_STAGE.ADI_ALLDB_ALL WHERE MFG_PART_NUM IN ("'.implode('","', $part_number).'")';
		$result = ExecuteIQuery($query,$iConRLM);
	
		while($row = mysqli_fetch_assoc($result)){
			(!in_array($row['RES_AREA'], $res_area)) 		? array_push($res_area, $row['RES_AREA']) : "";
			(!in_array($row['TESTER'], $tester)) 			? array_push($tester, $row['TESTER']) : "";
			(!in_array($row['HANDLER'], $handler)) 			? array_push($handler, $row['HANDLER']) : "";
			(!in_array($row['TEMP_CLASS'], $temp_class)) 	? array_push($temp_class, $row['TEMP_CLASS']) : "";
			(!in_array($row['OEE'], $oee)) 					? array_push($oee, $row['OEE']) : "";
		}
	
		array_push($cell, [
			$res_area,
			$tester,
			$handler,
			$temp_class,
			$oee
		]);
	}

	return [
		'CL' => $cell
	];
}

function GET_OEE_OVERRIDE($iConRLM, $retrieve_data){

	$oee_overrides  = [];
	$details		= [];
	$partnum 		= [];
	$no_part 		= [];
	$has_part 		= [];
	$oeeo_id 		= [];

	foreach ($retrieve_data as $key => $rd) {
		if (!in_array($rd['PART'], $partnum)) {
			array_push($partnum, $rd['PART']);
		}
		if (!in_array($rd['SITE'], $details)) {
			array_push($details, $rd['SITE']);
		}
		if (!in_array($rd['TEST_TYPE'], $details)) {
			array_push($details, $rd['TEST_TYPE']);
		}
		if (!in_array($rd['ENGR_TESTER'], $details)) {
			array_push($details, $rd['ENGR_TESTER']);
		}
		if (!in_array($rd['ENGR_HANDLER'], $details)) {
			array_push($details, $rd['ENGR_HANDLER']);
		}
		if (!in_array($rd['ATOM_TESTER'], $details)) {
			array_push($details, $rd['ATOM_TESTER']);
		}
		if (!in_array($rd['ATOM_HANDLER'], $details)) {
			array_push($details, $rd['ATOM_HANDLER']);
		}
		if (!in_array($rd['TEMP_CLASS'], $details)) {
			array_push($details, $rd['TEMP_CLASS']);
		}

		// if ($rd['HASH'] == NULL || $rd['HASH'] == '') {
		// 	$part_details = $rd['AAA_ID'].'|'.$rd['PART'].'|'.$rd['GENERIC'].'|'.$rd['SITE'].'|'.$rd['TEST_TYPE'].'|'.$rd['RTE_ID'].'|'.$rd['STEP'].'|'.$rd['TEMP_CLASS'].'|'.
		// 					$rd['ENGR_TESTER'].'|'.$rd['ENGR_HANDLER'].'|'.$rd['ATOM_TESTER'].'|'.$rd['ATOM_HANDLER'].'|'.$rd['PRIO_CD'].'|'.$rd['ATTR_ID'].'|'.$rd['HW_SET_ID'].'|'.$rd['RES_SET_ID'];

		// 	$retrieve_data[$key]['HASH'] = hash('sha256', $part_details);
		// }

		$retrieve_data[$key]['HASH'] = $rd['ATOM_MASTER_ID'];
	}

	//CHECK IF THERE'S AN OVERRIDE WITH PARTNUM APPLICABLE TO THE SELECTED PARTNUM IN PLANNING ASSUMPTIONS
	$get_override_part = 'SELECT OM.OEEO_ID FROM BRAIN.OEE_OVERRIDE_MAIN OM RIGHT JOIN BRAIN.OEE_OVERRIDE_PART OP ON OM.OEEO_ID = OP.OEEO_ID WHERE OP.MFG_PART_NUM IN ("'.implode('","', $partnum).'")';
	$get_override_part_res = ExecuteIQuery($get_override_part,$iConRLM);
	while($row = mysqli_fetch_assoc($get_override_part_res)){
		array_push($oeeo_id, $row['OEEO_ID']);
	}
	
	//CHECK IF THERE'S AN OVERRIDE WITHOUT PARTNUM APPLICABLE TO THE SELECTED PARTNUM IN PLANNING ASSUMPTIONS
	$implode = implode('","', $details);
	$get_override_main = 'SELECT OEEO_ID FROM BRAIN.OEE_OVERRIDE_MAIN OM WHERE 
	(
		OM.SITE_NUM IN ("'.$implode.'")
		OR OM.RES_AREA IN ("'.$implode.'")
		OR REPLACE(OM.TESTER, "_ZEROCAP", "") IN ("'.$implode.'")
		OR REPLACE(OM.HANDLER, "_ZEROCAP", "") IN ("'.$implode.'")
		OR OM.TEMP_CLASS IN ("'.$implode.'")
	)
	AND OM.OEEO_ID NOT IN (SELECT OEEO_ID FROM BRAIN.OEE_OVERRIDE_PART)';
	
	$get_override_main_res = ExecuteIQuery($get_override_main,$iConRLM);
	while($row = mysqli_fetch_assoc($get_override_main_res)){
		array_push($oeeo_id, $row['OEEO_ID']);
	}

	$latest_override = 'SELECT * FROM BRAIN.OEE_OVERRIDE_MAIN_HIST WHERE OEEO_ID IN ("'.implode('","', $oeeo_id).'") AND (CHANGE_TYPE = "NEW_OVERRIDE" OR CHANGE_TYPE = "UPDATE_OVERRIDE") ORDER BY CHANGE_DT ASC';
	$latest_override_res = ExecuteIQuery($latest_override,$iConRLM);
	while($row = mysqli_fetch_assoc($latest_override_res)){
		array_push($oee_overrides, $row);
	}
	
	if (count($oee_overrides) > 0 && $oee_overrides != null) {
		$identifier = [];
		foreach ($oee_overrides as $oee_key => $overrides) {
			$criteria = $oee_key."|SITE_NUM|RES_AREA";
			if ($overrides['TESTER'] != '') {
				$criteria .= "|TESTER";
			}
			if ($overrides['HANDLER'] != '') {
				$criteria .= "|HANDLER";
			}
			if ($overrides['TEMP_CLASS'] != '') {
				$criteria .= "|TEMP_CLASS";
			}
			array_push($identifier, $criteria);
		}
		$test = [];
		foreach ($retrieve_data as $rkey => $rd) {
			foreach ($identifier as $idtf) {
				$match_counter = 0;
				$split = explode("|", $idtf);
				$split_len = count($split);
				$oee_index = $split[0];
				foreach ($split as $skey => $sp) {
					if ($skey != 0) {
						//TRIM TESTER & HANDLER WITH "_ZEROCAP" VALUE
						if ($sp == 'TESTER' || $sp == 'HANDLER') {
							$oee_overrides[$oee_index][$sp] = str_replace("_ZEROCAP", "", $oee_overrides[$oee_index][$sp]);
						}
						switch ($sp) {
							case 'SITE_NUM':
								if ($rd['SITE'] == $oee_overrides[$oee_index][$sp]) {
									$match_counter++;
								}
							break;
							case 'RES_AREA':
								if ($rd['TEST_TYPE'] == $oee_overrides[$oee_index][$sp]) {
									$match_counter++;
								}
								break;
							case 'TESTER':
								if ($rd['ENGR_TESTER'] == $oee_overrides[$oee_index][$sp] || $rd['ATOM_TESTER'] == $oee_overrides[$oee_index][$sp]) {
									$match_counter++;
								}
								break;

							case 'HANDLER':
								if ($rd['ENGR_HANDLER'] == $oee_overrides[$oee_index][$sp] || $rd['ATOM_HANDLER'] == $oee_overrides[$oee_index][$sp]) {
									$match_counter++;
								}
								break;
							
							default:
								if ($rd['TEMP_CLASS'] == $oee_overrides[$oee_index][$sp]) {
									$match_counter++;
								}
								break;
						}
					}
				}
				$match_counter += 1;
				if ($match_counter == $split_len) {
					$retrieve_data[$rkey]['OEE'] = $oee_overrides[$oee_index]['OEE_VAL'];
				}
			}
		}

		$return_data = $retrieve_data;
	}
	else{
		$return_data = $retrieve_data;
	}

	//RETRIEVE ACTUAL PRIO CD
	foreach ($return_data as $key => $rd) {
		// FOR RETRIEVING PRIO BY HASH
		$new_prio = "";
		$string = $rd['PART'].'|'.$rd['SITE'].'|'.$rd['RTE_ID'].'|'.$rd['STEP'].'|'.$rd['TEMP_CLASS'].'|'.$rd['ENGR_TESTER'].'|'.$rd['ENGR_HANDLER'].'|'.$rd['ATOM_TESTER'].'|'.$rd['ATOM_HANDLER'].'|'.$rd['PRIO_CD'];
		$hash = hash("sha256", $string);
		$get_prio_cd = 'SELECT NEW_PRIO FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER = "'.$hash.'"';
		$get_prio_cd_res = ExecuteIQuery($get_prio_cd,$iConRLM);
		while($row = mysqli_fetch_assoc($get_prio_cd_res)){
			$new_prio = $row['NEW_PRIO'];
		}

		$return_data[$key]['PRIO_CD'] = $new_prio;
		$return_data[$key]['ORIG_PRIO'] = $rd['PRIO_CD'];
	}
	
	return $return_data;
}

function ADD_SETUP_PRIO($iConRLM, $retrieve_data){
	$response = "";
	$idtf_arr = [];
	$existing_prio = [];
	foreach ($retrieve_data as $key => $rd) {
		$string = $rd['PART'].'|'.$rd['SITE'].'|'.$rd['RTE_ID'].'|'.$rd['STEP'].'|'.$rd['TEMP_CLASS'].'|'.$rd['ENGR_TESTER'].'|'.$rd['ENGR_HANDLER'].'|'.$rd['ATOM_TESTER'].'|'.$rd['ATOM_HANDLER'].'|'.$rd['PRIO_CD'];
		$hash = hash("sha256", $string);
		$retrieve_data[$key]['IDENTIFIER'] = $hash;
		array_push($idtf_arr, $hash);
	}

	$check_prio = 'SELECT IDENTIFIER FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER IN ("'.implode('","', $idtf_arr).'")';
	$check_prio_res = ExecuteIQuery($check_prio,$iConRLM);
	while($row = mysqli_fetch_assoc($check_prio_res)){
		array_push($existing_prio, $row['IDENTIFIER']);
	}

	foreach ($retrieve_data as $key => $rd) {
		if (!in_array($rd['IDENTIFIER'], $existing_prio)) {
			$insert_prio = 'INSERT INTO TEST.ADI_SETUP_PRIO VALUES (
				"", "'.$rd['IDENTIFIER'].'", "'.$rd['PART'].'", "'.$rd['SITE'].'", "'.$rd['RTE_ID'].'",
				"'.$rd['STEP'].'", "DEFAULT", "NO", "'.$rd['PRIO_CD'].'", 
				"'.$rd['PRIO_CD'].'"
			)';
			$insert_prio_res = ExecuteIQuery($insert_prio,$iConRLM);
			$response = $insert_prio_res;
		}
	}

	return $response;
}

function GET_UNPLANNABLE($iConRLM, $data){
	$unplannable_arr = [];
	
	if (count($data) > 0) {
		$aaa_id = [];
		foreach ($data as $key => $dt) {
			array_push($aaa_id, $dt['ATOM_MASTER_ID']);
		}
		$query = 'SELECT * FROM TEST.ADI_UNPLANNABLE_SETUP WHERE ATOM_MASTER_ID IN ("'.implode('","', $aaa_id).'")';
		$result = ExecuteIQuery($query,$iConRLM);
	
		while($row = mysqli_fetch_assoc($result)){
			array_push($unplannable_arr, $row);
		}
	}

	return $unplannable_arr;
}

function GET_DEDICATION($iConRLM, $data){
	$result = [];
	$prio_result = [];
	$get_dedication = 'SELECT * FROM TEST.ADI_PRIMARY_SETUP APS INNER JOIN TEST.ADI_DEDICATION ADN ON APS.DED_ID = ADN.DED_ID WHERE APS.MFG_PART_NUM IN ("'.implode('","', $data).'")';
	$get_dedication_res = ExecuteIQuery($get_dedication,$iConRLM);
	while($row = mysqli_fetch_assoc($get_dedication_res)){
		array_push($result, $row);
	}

	$identifier_arr = array_map(function($item) {
    	return $item['IDENTIFIER'];
	}, $result);

	//GET CURRENT PRIO_CD FROM LIST
	$get_prio = 'SELECT ORIG_PRIO, NEW_PRIO, IDENTIFIER FROM TEST.ADI_SETUP_PRIO WHERE IDENTIFIER IN ("'.implode('","', $identifier_arr).'")';
	$get_prio_res = ExecuteIQuery($get_prio,$iConRLM);
	while($row = mysqli_fetch_assoc($get_prio_res)){
		array_push($prio_result, $row);
	}

	foreach ($result as $key => $res) {
		$src_idtf = $res['IDENTIFIER'];
		foreach ($prio_result as $key2 => $prio) {
			if ($src_idtf == $prio['IDENTIFIER']) {
				$result[$key]['ORIG_PRIO'] = $prio['ORIG_PRIO'];
				$result[$key]['PRIO_CD'] = $prio['NEW_PRIO'];
			}
		}
	}
	return $result;
}

$aryResponse["DATA"] 				= $retrieve_oee_override;
$aryResponse["PRIMARY"] 			= $retrieve_prim_setup;
$aryResponse["PENDING_PRIMARY"] 	= $retrieve_prim_pending;
$aryResponse["FORMER_PRIMARY"] 		= $retrieve_prim_former;
// $aryResponse["PRIMARY_HARDWARE"] 	= $retrieve_prim_hardware;
$aryResponse["STEP"] 				= $retrieve_steps;
$aryResponse["HARDWARE"] 			= $retrieve_hardware;
// $aryResponse["OEE"] 				= $retrieve_oee;
// $aryResponse["OEE_OVERRIDE"] 		= $retrieve_oee_override;
$aryResponse["UNPLANNABLE"] 		= $retrieve_unplannable;
$aryResponse["DEDICATION"] 			= $retrieve_dedication;

// function Print_R2($aryArray) {
//     echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",Print_R($aryArray,true))));
// }

if($bolJSON === true) {
	$jsnResponse = json_encode($aryResponse);
	if(json_last_error() == JSON_ERROR_NONE) {
        print_r(json_encode($jsnResponse));
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

?>