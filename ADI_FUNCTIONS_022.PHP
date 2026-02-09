<?php
define("_DATE_MAX","2037-12-31");
define("_DATE_MIN","2011-11-02");


function EXCEPTION_SESSION_CLEAR($iConRLM,$bolDebug=false) {
	if(!defined("_SESSION_NAME")) {
		die("ERROR: _SESSION_NAME NOT SET");
	}
	$strSession = _SESSION_NAME;

	$strSQL = "
		DELETE FROM
		BODS_JDA_ADI.X_SESSION
		WHERE SESSION_NAME = '".mysqli_real_escape_string($iConRLM,$strSession)."';";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	ExecuteIQuery($strSQL,$iConRLM);
}


function EXCEPTION_SESSION_DO($iConRLM,$intXID,$bolDebug=false) {
	if(!defined("_SESSION_NAME")) {
		die("ERROR: _SESSION_NAME NOT SET");
	}
	$strSession = _SESSION_NAME;

	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.X_SESSION
		(EXCEPTION_ID,SESSION_NAME)
		VALUES
		(
			".mysqli_real_escape_string($iConRLM,$intXID).",
			'".mysqli_real_escape_string($iConRLM,$strSession)."'
		);";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	ExecuteIQuery($strSQL,$iConRLM);
}


function EXCEPTION_ID_GET($iConRLM,$aryX,$bolDebug=false) {
	#2024-09-27 RM avoid wasteful churn

	#define structure ahead of time so order of try does not matter
	$aryFinal = array(
		"MESSAGE"=>$aryX["MESSAGE"],
		"FUNCTION"=>$aryX["FUNCTION"],
		"IDEN"=>array(),
		"INFO"=>array()
	);

	$strRX = "/^[-]?[0-9]+(.[0-9]+)?$/";
	$aryTry = array("IDEN","INFO");
	foreach($aryTry as $strTry) {
		$aryThis = $aryX[$strTry];
		$aryThat = array();

		foreach(array_keys($aryThis) as $key) {
			foreach($aryThis[$key] as $value) {
				switch (true) {
					case is_null($value) || trim($value) == "":
						#empty string is deliberate
						$aryThat[] = "";
						break;
					#case $value * 1 == $value:
					case preg_match($strRX,$value) == 1:
						$aryThat[] = round($value,3);
						break;
					default:
						$aryThat[] = $value;
						break;
				}
			}
		}
		sort($aryThat);
		$aryFinal[$strTry] = $aryThat;
	}

	$strSHA1 = sha1(serialize($aryFinal));

	$strSQL = "
		SELECT
		EXCEPTION_ID
		FROM BODS_JDA_ADI.X_ID_MASTER
		WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intXID = $LineMain["EXCEPTION_ID"];

		return $intXID;
	}

	#still here? not found
	$arySQL = array();

	$arySQL[] = "BEGIN;";

	$arySQL[] = "
		INSERT INTO
		BODS_JDA_ADI.X_ID_MASTER
		(SHA1_VALUE,EXCEPTION_MESSAGE,EXCEPTION_FUNCTION)
		VALUES
		(
			'".mysqli_real_escape_string($iConRLM,$strSHA1)."',
			'".mysqli_real_escape_string($iConRLM,$aryFinal["MESSAGE"])."',
			'".mysqli_real_escape_string($iConRLM,$aryFinal["FUNCTION"])."'
		);";

	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);
	}
	$intXID = mysqli_insert_id($iConRLM);


	$arySQL = array();
	
	$aryTry = array("IDEN","INFO");
	foreach($aryTry as $strTry) {
		$arySQLTemp = array();
		
		foreach(array_keys($aryX[$strTry]) as $strField) {
			foreach($aryX[$strTry][$strField] as $strValue) {
				$aryTemp = array(
					"EXCEPTION_ID"=>$intXID,
					"FIELD"=>$strField,
					"VALUE"=>$strValue
				);

				foreach($aryTemp as $key => $value) {
					$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
				}

				$arySQLTemp[] = "('".implode("','",$aryTemp)."')";
			}
		}

		if(count($arySQLTemp) > 0) {
			$arySQL[] = "
				INSERT INTO
				BODS_JDA_ADI.X_".$strTry."
				(EXCEPTION_ID,".$strTry."_NAME,".$strTry."_VALUE)
				VALUES
				".implode(",",$arySQLTemp).";";
		}
	}

	$arySQL[] = "COMMIT;";

	foreach($arySQL as $strSQL) {
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);

		if(!is_null(error_get_last())) {
			$strSQL = "ROLLBACK;";
			ExecuteIQuery($strSQL,$iConRLM);

			die("ERROR ON SQL EXECUTE: ".$strSQL);

			break;
		}
	}

	return $intXID;
}


function ATOM_LOGIC_DO($iConRLM,$bolDebug=false) {
	echo __FUNCTION__."...";

	$strSQL = "
		TRUNCATE
		BODS_JDA_ADI.ATOM_LOGIC;";
	ExecuteIQuery($strSQL,$iConRLM);


	$strSQL = "
		SELECT DISTINCT
		AC.LINE_NUMBER
		FROM BODS_JDA_ADI.ATOM_CHANGE AC
		INNER JOIN BODS_JDA_ADI.ATOM_MASTER AM ON AC.AC_MASTER_ID = AM.AC_MASTER_ID
		WHERE DATE_FORMAT(AM.LAST_CHECKED_DT,'%Y-%m-%d') = 
		(
			SELECT
			MAX(DATE_FORMAT(LAST_CHECKED_DT,'%Y-%m-%d')) LAST_CHECKED_DT
			FROM BODS_JDA_ADI.ATOM_MASTER
		)
		ORDER BY LINE_NUMBER;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryThis = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryThis[] = $LineMain["LINE_NUMBER"];
	}


	$aryThat = explode("\n",file_get_contents(_FUNCTIONS_FILENAME));
	#Print_R($aryThat);
	#die();
	foreach($aryThis as $intThis) {
		$aryTemp = array();
		$bolFound = false;

		#2024-04-30 RM moved the reset here
		$intLinePrev = 0;
		for($i=$intThis;$i>=$intLinePrev;$i--) {
			$strLine = $aryThat[$i];

			if(strpos($strLine,"/*") !== false) {
				$intLinePrev = $i;
				break;
			}

			if($bolFound === true) {
				$aryTemp[$i] = $strLine;
			}

			if(strpos($strLine,"*/") !== false) {
				$bolFound = true;
			}
			
		}
		ksort($aryTemp);

		$strLogic = mysqli_real_escape_string($iConRLM,implode("\n",$aryTemp));
		$strSQL = "
			INSERT INTO
			BODS_JDA_ADI.ATOM_LOGIC
			(LINE_NUMBER,LOGIC_STRING)
			VALUES
			(
				".mysqli_real_escape_string($iConRLM,$intThis).",
				'".$strLogic."'
			);";
		ExecuteIQuery($strSQL,$iConRLM);
	}

	echo "DONE.\n";
}


function ATTR_STATIC_DO($iConRLM,$strSystem,$bolDebug=false) {
	echo __FUNCTION__."...";
	$decStartTime = time()+microtime();

	$strSQL = "
		SELECT DISTINCT
		ATTR_NM
		FROM BODS_JDA_ADI.RES_ALTERNATES_".$strSystem." RA
		INNER JOIN BODS_JDA_ADI.RESOURCE_ATTR RATTR ON RA.ATTR_SET_ID = RATTR.ATTR_SET_ID
		ORDER BY ATTR_NM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryAttr = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strAttr = $LineMain["ATTR_NM"];
		$strAttrFixed = str_replace(" ","_",$strAttr);
		$aryAttr[$strAttr] = $strAttrFixed;
	}

	if(count($aryAttr) > 0) {
		$arySQLTemp = array();
		foreach($aryAttr as $strAttrFixed) {
			$arySQLTemp[] = "`".$strAttrFixed."` VARCHAR(50) NULL DEFAULT NULL";
		}

		$arySQL = array();

		$arySQL[] = "
			DROP TABLE IF EXISTS
			BODS_JDA_ADI.ATTR_STATIC_".$strSystem.";";

		$arySQL[] = "
			CREATE TABLE 
			BODS_JDA_ADI.ATTR_STATIC_".$strSystem."
			(
				ATTR_SET_ID INT(11) NULL DEFAULT NULL,
				".implode(",",$arySQLTemp).",
				INDEX (ATTR_SET_ID)
			);";

		foreach($arySQL as $strSQL) {
			ExecuteIQuery($strSQL,$iConRLM);
		}


		$strSQL = "
			SELECT DISTINCT
			ATTR_SET_ID
			FROM BODS_JDA_ADI.RES_ALTERNATES_".$strSystem."
			ORDER BY ATTR_SET_ID;";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$aryASID = array();
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$aryASID[] = $LineMain["ATTR_SET_ID"];
		}

		$aryTemp = array();
		while ($intASID = array_pop($aryASID)) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$intASID);

			if(count($aryTemp) >= 100 || count($aryASID) == 0) {
				$strSQL = "
					SELECT
					ATTR_SET_ID,
					ATTR_NM,
					ATTR_VAL_NUM,
					ATTR_VAL_CHAR
					FROM BODS_JDA_ADI.RESOURCE_ATTR
					WHERE ATTR_SET_ID IN (".implode(",",$aryTemp).")
					ORDER BY ATTR_SET_ID, ATTR_NM;";
				$RSMain = ExecuteIQuery($strSQL,$iConRLM);
				$aryRaw = array();
				while ($LineMain = mysqli_fetch_assoc($RSMain)) {
					$intASID = $LineMain["ATTR_SET_ID"];
					$strAttr = $LineMain["ATTR_NM"];
					$strAttrFixed = $aryAttr[$strAttr];

					if(!is_null($LineMain["ATTR_VAL_NUM"])) {
						$strValue = $LineMain["ATTR_VAL_NUM"];
					}else{
						$strValue = $LineMain["ATTR_VAL_CHAR"];
					}
					$aryRaw[$intASID][$strAttrFixed] = $strValue;
				}

				
				$arySQLTemp = array();
				foreach(array_keys($aryRaw) as $intASID) {
					$aryTemp = array("ATTR_SET_ID"=>$intASID);
					foreach($aryAttr as $strAttrFixed) {
						if(isset($aryRaw[$intASID][$strAttrFixed])) {
							$aryTemp[$strAttrFixed] = "'".mysqli_real_escape_string($iConRLM,$aryRaw[$intASID][$strAttrFixed])."'";
						}else{
							$aryTemp[$strAttrFixed] = "NULL";
						}
					}

					$arySQLTemp[] = "(".implode(",",$aryTemp).")";
				}

				if(count($arySQLTemp) > 0) {
					$aryTemp = array();
					foreach($aryAttr as $strAttrFixed) {
						$aryTemp[] = $strAttrFixed;
					}

					$strSQL = "
						INSERT INTO
						BODS_JDA_ADI.ATTR_STATIC_".$strSystem."
						(`ATTR_SET_ID`,`".implode("`,`",$aryTemp)."`)
						VALUES
						".implode(",",$arySQLTemp).";";
					ExecuteIQuery($strSQL,$iConRLM);
				}

				#reset
				$aryTemp = array();
			}
		}
	}


	$decEndTime = time()+microtime();
	echo "DONE. (".number_format($decEndTime-$decStartTime,1)." s.)\n";
}


function BOR_CALC($iConRLM,$aryHWTypeAllowed,$aryResCap,$aryMMRThis,$strStepNamePP,$strTester,$strHandler,$aryHW,$intUTPI,$bolDebug=false) {

	#2024-04-23 RM since i cant control incoming hardware types, at least make sure any spaces have been replaced with underscores
	#2024-04-23 RM not needed. whatever the hw_type is in adi_tbl_hardware_inventory_autosched overrides whatever was in here once hw_nm is found

/*
	$aryThis = $aryHW;
	$aryThat = array();
	foreach(array_keys($aryThis) as $strHWTypeThis) {
		$strHWTypeThat = str_replace(" ","_",$strHWTypeThis);
		$aryThat[$strHWTypeThat] = $aryThis[$strHWTypeThis];
	}
	$aryHW = $aryThat;
*/

	#Print_R($aryHW);
	#die();
	$aryHWS = array();
	foreach(array_keys($aryHW) as $strHWType) {
		foreach($aryHW[$strHWType] as $strHWName => $intRQ) {
			switch (true) {
				case isset($aryResCap["HARDWARE"][$strHWName]):
					$strHWType = $aryResCap["HARDWARE"][$strHWName]["RES_TYPE"];

					#echo $strHWName." / ".$strHWType."\n";

					switch (true) {
						#2024-04-26 RES_CAP shuld alreayd be filtered to HW types we care about + those in adi_resource_override
						#case !in_array($strHWType,$aryHWTypeAllowed):
						#	break;
						case !isset($aryHWS[$strHWType]["PRI"]):
							$aryHWS[$strHWType]["PRI"][$strHWName] = $intRQ;
							break;
						default:
							$aryHWS[$strHWType]["ALT"][$strHWName] = $intRQ;
							break;
					}
					break;
				case isset($aryResCap["HWC"][$strHWName]):
					$strHWType = $aryResCap["HWC"][$strHWName]["RES_TYPE"];

					#Eecho $strHWName." / ".$strHWType."\n";

					switch (true) {
						#2024-04-26 RES_CAP shuld alreayd be filtered to HW types we care about + those in adi_resource_override
						#case !in_array($strHWType,$aryHWTypeAllowed):
						#	break;
						case !isset($aryHWS[$strHWType]["PRI"]):
							$aryHWS[$strHWType]["PRI"][$strHWName] = $intRQ;
							break;
						default:
							$aryHWS[$strHWType]["ALT"][$strHWName] = $intRQ;
							break;
					}
					break;
				default:
					#2024-04-19 RM added for other stuff
					
					if(in_array($strHWType,$aryHWTypeAllowed)) {
						if(!isset($aryHWS[$strHWType]["PRI"])) {
							$aryHWS[$strHWType]["PRI"][$strHWName] = $intRQ;
						}else{
							$aryHWS[$strHWType]["ALT"][$strHWName] = $intRQ;
						}
					}
					break;
			}
		}
	}

	#2024-04-30 RM apply UTPI to contactors
	foreach(array_keys($aryHWS) as $strHWType) {
		if($strHWType == "CNTCR") {
			foreach(array_keys($aryHWS[$strHWType]) as $strPA) {
				foreach($aryHWS[$strHWType][$strPA] as $strHWName => $intRQ) {
					$aryHWS[$strHWType][$strPA][$strHWName] = $intRQ * $intUTPI;
				}
			}
		}
	}

	#Print_R($aryHWS);
	#die();


	$intHWSID = HW_SET_ID_GET($iConRLM,$aryHWS,$bolDebug);
	#Print_R($aryHWS);
	#echo $intHWSID."\n";
	#die();
	
	$bolChanged = false;
	foreach($aryMMRThis as $aryMRTemp) {
		$strHWType = $aryMRTemp["CHANGE"]["RES_TYPE"];
		$strHWName = $aryMRTemp["CHANGE"]["RES_NM"];

		#echo $strSN." / ".$strStepName."\n";
		#Print_R($aryMRTemp);

		switch (true) {
			case isset($aryMRTemp["FILTER"]["SITE_NUM"]) && $aryMRTemp["FILTER"]["SITE_NUM"] == $strSN && isset($aryMRTemp["FILTER"]["STEP_NM"]) && $aryMRTemp["FILTER"]["STEP_NM"] == $strStepNamePP:
				#if both are set and both match, apply it

				$aryHWS[$strHWType]["PRI"][$strHWName] = 1;
				$bolChanged = true;
				break;
			case isset($aryMRTemp["FILTER"]["SITE_NUM"]) && isset($aryMRTemp["FILTER"]["STEP_NM"]):
				#if both are set but at least one does not match, do not apply
				break;
			case isset($aryMRTemp["FILTER"]["STEP_NM"]) && $aryMRTemp["FILTER"]["STEP_NM"] == $strStepNamePP:
				#step_nm filter is set and matches
				$aryHWS[$strHWType]["PRI"][$strHWName] = 1;
				$bolChanged = true;
				break;
			case isset($aryMRTemp["FILTER"]["SITE_NUM"]) && $aryMRTemp["FILTER"]["SITE_NUM"] == $strSN:
				#site_num filter is set and matches
				$aryHWS[$strHWType]["PRI"][$strHWName] = 1;
				$bolChanged = true;
				break;
			case isset($aryMRTemp["FILTER"]["STEP_NM"]) || isset($aryMRTemp["FILTER"]["SITE_NUM"]):
				#one or the other is set but does not match
				break;
			default:
				#no filter set, therefore is applicable everywhere
				$aryHWS[$strHWType]["PRI"][$strHWName] = 1;
				$bolChanged = true;
				break;
		}
	}

	#dont even bother checking for hw_set_id if there was no change
	if($bolChanged === true) {
		#if there was a measured change then write the new hws_id
		#Print_R($aryHWS);

		$intHWSIDNew = HW_SET_ID_GET($iConRLM,$aryHWS,$bolDebug);
		if($intHWSIDNew != $intHWSID) {
			$intHWSID = $intHWSIDNew;
		}

		#echo $intHWSID." => ".$intHWSIDNew."\n";
	}	


	if(is_null($strTester) || trim($strTester) == "") {
		$strTester = "NO TESTER";
	}
	if(is_null($strHandler) || trim($strHandler) == "") {
		$strHandler = "NO HANDLER";
	}

	$aryBOR = array(
		"TESTER"=>array($strTester=>1),
		"HANDLER"=>array($strHandler=>1),
		"HARDWARE_SET"=>array($intHWSID=>1)
	);

	#Print_R($aryBOR);
	#die();

	$intRSID = RES_SET_ID_GET($iConRLM,$aryBOR,$bolDebug);

	if($bolDebug === true) {
		Print_R($aryBOR);
		Print_R($intRSID);
	}

	return $intRSID;
}


function RES_CAP_GET_V3($iConRLM,$aryHWTypeAllowed,$aryMR,$bolDebug=false) {
	echo __FUNCTION__."...";
	$aryReturn = array();



	#2023-07-06 RM from KB 
	#2024-03-04 RM disabled . request from KS
	$strSQL = "
		SELECT DISTINCT
		RES_NM
		FROM EQUIP.RESOURCE_CAPACITY
		WHERE (RES_NM LIKE 'TS8%_LT%' OR RES_NM LIKE '%LTX_ADSC%') 
		AND RES_NM NOT LIKE 'TS88_STD_SL_LT%' 
		ORDER BY RES_NM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryHalf = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		#$aryHalf[] = $LineMain["RES_NM"];
	}

	#2023-05-23 RM return structure now SITE_NUM, RES_NM => array(RES_TYPE,RES_CNT,EFF_RES_CNT)
	#forces removal of any ambiguity caused by bad data

	#2024-02-26 RM now use equip.resource_capacity. 
	#but tester_proceff and handler_proceff are not available there, so keep pulling those from original tables
	#2024-03-05 RM SO FUCKING DUMB. BAND-AID SHIT AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN
	#2024-03-05 Rm fixing this upstream. this is bullshit
	$strSQL = "
		SELECT
		RES_TYPE,
		RES_NM,
		SITE_NUM,
		RES_AREA,
		EFF_START_DT,
		EFF_END_DT,
		SUM(RES_CNT) RES_CNT,
		SUM(EFF_RES_CNT) EFF_RES_CNT,
		MIN(OEE) OEE
		FROM EQUIP.RESOURCE_CAPACITY
		WHERE RES_TYPE IN ('TESTER','HANDLER')
		GROUP BY RES_TYPE, RES_NM, SITE_NUM, RES_AREA, EFF_START_DT, EFF_END_DT
		ORDER BY RES_TYPE, RES_NM, SITE_NUM, RES_AREA, IFNULL(EFF_START_DT,'"._DATE_MIN."'), IFNULL(EFF_END_DT,'"._DATE_MAX."');";			
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRT = $LineMain["RES_TYPE"];
		$strRN = $LineMain["RES_NM"];
		$strSN = $LineMain["SITE_NUM"];
		$strRA = $LineMain["RES_AREA"];

		#2023-07-06 RM from KB
		$aryTemp = array(
			"EFF_START_DT"=>$LineMain["EFF_START_DT"],
			"EFF_END_DT"=>$LineMain["EFF_END_DT"],
			"RES_CNT"=>$LineMain["RES_CNT"],
			"EFF_RES_CNT"=>$LineMain["EFF_RES_CNT"],
			"OEE"=>$LineMain["OEE"],
			"PROCEFF"=>1,
		);

		$aryReturn[$strRT][$strRN][$strSN][$strRA][] = $aryTemp;
	}

	#Print_R($aryReturn["HANDLER"]["AETRIUM_RS_HV_HPCS"]);
	#Print_R($aryReturn["HANDLER"]);
	#die();


	$arySQL = array();

	$arySQL[] = "
		SELECT
		PLANTSITE,
		'TESTER' RES_TYPE,
		STNFAM2 RES_NM,
		MIN(
			CASE TRUE
				WHEN ISNULL(PROCEFF) THEN 1
				WHEN PROCEFF = 0 THEN 1
				WHEN PROCEFF > 1 THEN 1
				ELSE PROCEFF
			END
		) PROCEFF
		FROM ADI_ESLDATA.ADI_TBL_TESTERS_INVENTORY_GENERATE
		GROUP BY PLANTSITE, RES_NM;";

	$arySQL[] = "
		SELECT
		PLANTSITE,
		'HANDLER' RES_TYPE,
		GENRESFAMSORT RES_NM,
		MIN(
			CASE TRUE
				WHEN ISNULL(PROCEFF) THEN 1
				WHEN PROCEFF = 0 THEN 1
				WHEN PROCEFF > 1 THEN 1
				ELSE PROCEFF
			END
		) PROCEFF
		FROM ADI_ESLDATA.ADI_TBL_HANDLERS_INVENTORY_GENERATE
		GROUP BY PLANTSITE, GENRESFAMSORT;";

	foreach($arySQL as $strSQL) {
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSN = $LineMain["PLANTSITE"];
			$strRT = $LineMain["RES_TYPE"];
			$strRN = $LineMain["RES_NM"];

			if(isset($aryReturn[$strRT][$strRN][$strSN])) {
				foreach(array_keys($aryReturn[$strRT][$strRN][$strSN]) as $strRA) {
					foreach($aryReturn[$strRT][$strRN][$strSN][$strRA] as $intCtr => $aryReturnTemp) {
						$aryReturnTemp["PROCEFF"] = $LineMain["PROCEFF"];
						$aryReturn[$strRT][$strRN][$strSN][$strRA][$intCtr] = $aryReturnTemp;
					}
				}
			}
		}
	}

	#2023-05-23 RM need to restructure this as we are now relying solely upon hardware_name -> eqpt_type from adi_tbl_hardware_inventory_autosched to override data coming from adi_route_param
	#as of today there are 5 hardware_names with different eqpt_types within the list of types that we want, with status in ('avail','spare') and current_location not in (filtered location list) so we need to pick a winner
	#2023-12-11 RM now there are 8
	
	#$bolDebug = true;
	
	#2023-07-05 RM changed structure of mapper and final array
	#determine name -> type relationship first, which is global. then add site_num

	#2024-02-29 RM added TGLTXNPW at KB request

	$arySQL = array(
		"FILTER"=>array(),
		"FILTER2"=>array()
	);

	$aryTemp = array('ENGG','OBSOLETE','PR','QUAL','SCRAP','SPARE','XFER');
	$arySQL["FILTER"][] = "STATUS NOT IN ('".implode("','",$aryTemp)."')";
	$aryTemp = array('PICALB','PIENGG','PIPM','PIQUAL','PIREPR','PIXFER','TGBIPM','TGQUAL','QUARANTINE','TGBIRPR','TGCALB','TGDEBUG','TGFAREL','TGPM','TGPR','TGREWORK','TGSCRAP','TGUNAVAIL','TGXFER','PREXFER','TGPREXFER','FLOATING','TGFLOATING','TGPCSCRAP','TGPCSHIP','TGPCXFER','TGVERF','TGMDOWN','TGNDHWDEF','TGLTXNPW');
	$arySQL["FILTER"][] = "NOT (STATUS = 'AVAIL' AND CURRENT_LOCATION IN ('".implode("','",$aryTemp)."'))";


	$aryTemp = array();
	foreach($aryHWTypeAllowed as $strHWType) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strHWType);
	}
	
	if(count($aryTemp) > 0) {
		$arySQL["FILTER2"][] = "EQPT_TYPE IN ('".implode("','",$aryTemp)."')";
	}
	

	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(EQPT_TYPE) HW_TYPE,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		GROUP BY HW_NAME, HW_TYPE
		ORDER BY HW_NAME, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];

		#2023-10-20 RM applying filters up-front for HW_TYPE assignment. since the winner is picked going forward
		#per NAME, TYPE with the most COUNT wins
		if(!isset($aryMap["NAME_TYPE"][$strName])) {
			$aryMap["NAME_TYPE"][$strName] = $LineMain["HW_TYPE"];
		}
	}
	

	#2024-01-02 RM when a name exists but filters rule it out, making 0 quantity, add into this array so we have a record at least. otherwise it gets dropped, which can unconstrain stuff
	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(EQPT_TYPE) HW_TYPE,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER2"])."
		GROUP BY HW_NAME, HW_TYPE
		ORDER BY HW_NAME, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];

		if(!isset($aryMap["NAME_TYPE"][$strName])) {
			$aryMap["NAME_TYPE"][$strName] = $LineMain["HW_TYPE"];
		}
	}



	#2023-10-20 RM Re: Hardware Repair LTC for pushout modified incoming filters, capturing both current_location and status
	#2023-12-15 RM added detection of complex category. data is currently inconsistent so just look for existence
	#2024-05-30 RM kill repair release, reservation of repair for spare. nothing changes in this function

	$strSQL = "
		SELECT DISTINCT
		UCASE(HARDWARE_NAME) HW_NAME
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		AND ".implode(" AND ",$arySQL["FILTER2"])."
		AND CATEGORY = 'COMPLEX';";
	#echo $strSQL."\n";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMap["NAME_CAT"] = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$aryMap["NAME_CAT"][$strName] = "COMPLEX";
	}

	#Print_R($aryMap["NAME_CAT"]["GTL-50195"]);
	#die();

	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(
			CASE LOCATION
				WHEN 'GTHTML' THEN 'ADGT'
				WHEN 'PIHTML' THEN 'ADPI'
				WHEN 'LTXHTML' THEN 'LTX'
				ELSE 'UNKNOWN'
			END
		) SITE_NUM,
		UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
		UCASE(STATUS) STATUS,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		AND ".implode(" AND ",$arySQL["FILTER2"])."
		GROUP BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS
		ORDER BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$arySN = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$strSN = $LineMain["SITE_NUM"];
		$strCL = $LineMain["CURRENT_LOCATION"];
		$strStatus = $LineMain["STATUS"];

		if(isset($aryMap["NAME_TYPE"][$strName])) {
			$strType = $aryMap["NAME_TYPE"][$strName];

			if(!isset($aryMain[$strName])) {
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					#2023-12-14 RM now capturing category. only complex has special treatment
					"CATEGORY"=>null,
					"SITE_NUM"=>array()
				);
			}

			if(isset($aryMap["NAME_CAT"][$strName])) {
				$aryMain[$strName]["CATEGORY"] = $aryMap["NAME_CAT"][$strName];
			}

			$aryMain[$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $LineMain["HW_COUNT"];

			$arySN[] = $strSN;
		}
	}
	$arySN = array_unique($arySN);


	#2024-01-02 RM also needed here, to establish existence so it gets recognized as hardware
	#2024-01-18 RM added exlcusion of scrap 
	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(
			CASE LOCATION
				WHEN 'GTHTML' THEN 'ADGT'
				WHEN 'PIHTML' THEN 'ADPI'
				WHEN 'LTXHTML' THEN 'LTX'
				ELSE 'UNKNOWN'
			END
		) SITE_NUM,
		UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
		UCASE(STATUS) STATUS,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER2"])."
		AND STATUS NOT IN ('SCRAP')
		GROUP BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS
		ORDER BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$strSN = $LineMain["SITE_NUM"];
		$strCL = $LineMain["CURRENT_LOCATION"];
		$strStatus = $LineMain["STATUS"];

		if(isset($aryMap["NAME_TYPE"][$strName])) {
			$strType = $aryMap["NAME_TYPE"][$strName];

			if(!isset($aryMain[$strName])) {
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					#2023-12-14 RM now capturing category. only complex has special treatment
					"CATEGORY"=>null,
					"SITE_NUM"=>array()
				);

				if(isset($aryMap["NAME_CAT"][$strName])) {
					$aryMain[$strName]["CATEGORY"] = $aryMap["NAME_CAT"][$strName];
				}

				#ONLY write initial entry. do not write any kind of quantity
			}
		}
	}

	
	#2023-10-26 RM specific enablement of site_num, res_type, res_nm combinations
	$aryTH = array("TESTER","HANDLER");
	#$bolDebug = true;
	foreach($aryMR as $intMRID => $aryMRTemp) {
		$strType = $aryMRTemp["RES_TYPE"];
		$strName = $aryMRTemp["RES_NM"];

		if(!in_array($strType,$aryTH)) {
			#echo $strType." / ".$strName."\n";

			$aryTemp = array();
			switch (true) {
				case !isset($aryMRTemp["SITE_NUM"]):
					break;
				case $aryMRTemp["SITE_NUM"] == "ADGT":
					$aryTemp[] = "LOCATION = 'GTHTML'";
					break;
				case $aryMRTemp["SITE_NUM"] == "ADPI":
					$aryTemp[] = "LOCATION = 'PIHTML'";
					break;
			}
			
			$aryTemp[] = "EQPT_TYPE = '".mysqli_real_escape_string($iConRLM,$strType)."'";
			$aryTemp[] = "HARDWARE_NAME = '".mysqli_real_escape_string($iConRLM,$strName)."'";

			$strSQL = "
				SELECT
				UCASE(
					CASE LOCATION
						WHEN 'GTHTML' THEN 'ADGT'
						WHEN 'PIHTML' THEN 'ADPI'
						WHEN 'LTXHTML' THEN 'LTX'
						ELSE 'UNKNOWN'
					END
				) SITE_NUM,
				UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
				UCASE(STATUS) STATUS,
				COUNT(ITEM_CODE) HW_COUNT
				FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
				WHERE ".implode(" AND ",$arySQL["FILTER"])."
				AND ".implode(" AND ",$aryTemp)."
				GROUP BY SITE_NUM, CURRENT_LOCATION, STATUS
				ORDER BY SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strSN = $LineMain["SITE_NUM"];
				$strCL = $LineMain["CURRENT_LOCATION"];
				$strStatus = $LineMain["STATUS"];

				if(!isset($aryMain[$strName])) {
					$aryMain[$strName] = array(
						"RES_TYPE"=>$strType,
						"SITE_NUM"=>array()
					);
				}

				$aryMain[$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $LineMain["HW_COUNT"];
			}


			#2023-10-27 RM any inv set here as part of override is not important. the existence of the hw_name is what makes the difference.  
			#actual quantity calculation happens in res_cap_do

			if(!isset($aryMain[$strName])) {
				#did not get any kind of inventory, so this is either a dummy or the person who entered it is a dummy
				#either way, create it now
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					"SITE_NUM"=>array()
				);				

				if(isset($aryMRTemp["SITE_NUM"])) {
					$strSN = $aryMRTemp["SITE_NUM"];
					$aryMain[$strName]["SITE_NUM"][$strSN] = array(
						"OVERRIDE"=>array(
							"OVERRIDE"=>$aryMRTemp["INV"]
						)
					);
				}else{
					foreach($arySN as $strSN) {
						$aryMain[$strName]["SITE_NUM"][$strSN] = array(
							"OVERRIDE"=>array(
								"OVERRIDE"=>$aryMRTemp["INV"]
							)
						);
					}
				}
			}
			#Print_R($aryMain[$strName]);
		}
	}

	#Print_R($aryMain["L-01755"]);
	#die();

	#Print_R($aryMain["DUMMY_1"]);
	#die();

	foreach(array_keys($aryMain) as $strName) {
		$aryReturn["HARDWARE"][$strName] = $aryMain[$strName];	
	}

	#2024-06-27 RM takes way too long, fixed
	$arySQL = array();

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_001_TEMPORARY
		(
			INDEX IDX1 (NAME, HW_TYPE, SITE, LOCATION)
		)
		SELECT
		BARCODE,
		NAME,
		CASE HW_TYPE
			WHEN 'LOADBOARD' THEN 'LDBRD'
			WHEN 'PROBECARD' THEN 'PRBCRD'
			WHEN 'CONTACTOR BOARD' THEN 'CNTBRD'
			WHEN 'DUTBOARD' THEN 'DUTBRD'
			WHEN 'MOTHERBOARD' THEN 'MTHRBRD'
		END HW_TYPE,
		SITE,
		LOCATION,
		STAT
		FROM
		(
			SELECT
			BARCODE,
			NAME,
			REPLACE(
				TRIM(
					REPLACE(
						REPLACE(
							HW_TYPE,
							'DEDICATED',
							''
						),
						'STANDARD',
						''
					)
				),
				' ',
				'_'
			) HW_TYPE,
			CASE SITE
				WHEN 'Thailand' THEN 'ADTH'
				ELSE SITE
			END AS SITE,
			LOCATION,
			STAT
			FROM WHICD.HW_INVENTORY
		) TBL_SUB
		HAVING HW_TYPE IN ('LDBRD','PRBCRD','CNTBRD','DUTBRD','MTHRBRD');";

	foreach($arySQL as $strSQL) {
		ExecuteIQuery($strSQL,$iConRLM);
	}


	$aryHWC = array();
	$strSQL = "
		SELECT
		NAME,
		HW_TYPE,
		SITE,
		LOCATION,
		COUNT(BARCODE) QTY
		FROM TEST.TEST_001_TEMPORARY
		WHERE SITE IN ('ADTH')
		AND STAT = 'RELEASED TO PRODUCTION'
		GROUP BY NAME, HW_TYPE, SITE, LOCATION;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	While ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["NAME"];
		$strSN = $LineMain["SITE"];
		$strCL = $LineMain["LOCATION"];

		if(!isset($aryHWC[$strName])) {
			$aryHWC[$strName] = array(
				"RES_TYPE"=>$LineMain["HW_TYPE"],
				"CATEGORY"=>null,
				"SITE_NUM"=>array()
			);				
		}
		
		if(!isset($aryHWC[$strName]["SITE_NUM"][$strSN][$strCL]["AVAIL"])) {
			$aryHWC[$strName]["SITE_NUM"][$strSN][$strCL]["AVAIL"] = 0;
		}
		$aryHWC[$strName]["SITE_NUM"][$strSN][$strCL]["AVAIL"] += $LineMain["QTY"];
	}		

	
	#2024-09-23 RM fill in the blanks
	$strSQL = "
		SELECT DISTINCT
		NAME,
		HW_TYPE,
		SITE
		FROM TEST.TEST_001_TEMPORARY;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	While ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["NAME"];
		$strSN = $LineMain["SITE"];
		$strCL = "OTHER";

		if(!isset($aryHWC[$strName])) {
			$aryHWC[$strName] = array(
				"RES_TYPE"=>$LineMain["HW_TYPE"],
				"CATEGORY"=>null,
				"SITE_NUM"=>array()
			);				
		}

		if(!isset($aryHWC[$strName]["SITE_NUM"]["ADTH"][$strCL])) {
			$aryHWC[$strName]["SITE_NUM"]["ADTH"][$strCL]["AVAIL"] = 0;
		}
	}		

	$aryReturn["HWC"] = $aryHWC;

	$strSQL = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY;";
	ExecuteIQuery($strSQL,$iConRLM);


	#Print_R($aryReturn["HWC"]["RT38/A LOADBOARD"]);
	#die();

	#Print_R(array_keys($aryReturn["HARDWARE"]));
	#print_r(array_keys($aryReturn["HANDLER"]));
	#Print_R($aryReturn["HWC"]);
	#Print_R($aryReturn["HARDWARE"]["LTC-00280"]);
	#Print_R($aryReturn["HARDWARE"]);
	#die();

	echo "DONE.\n";
	return $aryReturn;
}


function PART_STEP_MAP_GET_V2($iConRLM,$bolDebug=true) {
	$aryReturn = array();

	$strSQL = "
		SELECT
		PART_NAME,
		STEP_NAME_TRS,
		STEP_NAME_PP
		FROM ADI_ESLDATA.PART_STEP_TRS_PP;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strPart = $LineMain["PART_NAME"];
		$strStepNameTRS = $LineMain["STEP_NAME_TRS"];
		$aryReturn[$strPart][$strStepNameTRS] = $LineMain["STEP_NAME_PP"];
	}

	#add manual mapping as you like
	/*
	$aryReturn["LTC6655BHMS8-5#PBF-T0"] = array(
		'LATFT'=>'TFCLASS_LAT_A1',
		'LATFT2'=>'TFCLASS_LAT_C2',
		'LATFT3'=>'TFCLASS_LAT_H3',
		'FT'=>'TFCLASS_A1',
		'FT2'=>'TFCLASS_C2',
		'FT3'=>'TFCLASS_H3',
		'QA'=>'TQCLASS_A1',
		'QA2'=>'TQCLASS_C2',
		'QA3'=>'TQCLASS_H3',
	);
	*/

	return $aryReturn;
}


function TRS_RX_GET() {
	#2024-09-23 RM added REL
	$aryRX = array(
		"ROUTE"=>array(
			"TEST"=>array(
				"FIND"=>array(
					"^",
					#must go first
					"(BIFT[0-9]+)?",
					"(BIFT)?",
					"(BI)?",
					"(B)?",
					"(FB)?",
					"(TM)?",
					"(LAT)?",
					"(N)?",
					"(REL)?",
					"(FT)?",
					"(WS)?",
					"(QA)?",
					"(QM)?",
					"(TQA)?",
					"([0-9]?)",
					"$"
				),
				"FIELD"=>array(
					"",
					"IS_PSB^IS_TEST",
					"IS_PRB^IS_TEST",
					"IS_BRN",
					"IS_BKE",
					"IS_BTH",
					"IS_VOS^IS_MULTITEMP",
					"IS_LAT",
					"IS_NSE",
					"IS_REL",
					"IS_TEST",
					"IS_WS",
					"IS_QA",
					"IS_QA^IS_MULTITEMP",
					"IS_QA^IS_VOS",
					"STEP_TYPE_NUM",
					""
				)
			)
		)
	);

	foreach($aryRX["ROUTE"] as $strGroup => $aryRXTemp) {
		$aryRX["ROUTE"][$strGroup]["RX"] = "/".implode("",$aryRXTemp["FIND"])."/";
	}

	return $aryRX;
}


function BLEND_GET($iConRLM,$bolDebug=false) {
	$aryReturn = array();

	$strSQL = "
		SELECT
		PART_NAME,
		TESTER,
		HANDLER
		FROM ADI_ESLDATA.ADI_BLENDED_PARTS
		WHERE !ISNULL(PART_NAME) AND TRIM(PART_NAME) != ''
		AND !ISNULL(TESTER) AND TRIM(TESTER) != ''
		AND !ISNULL(HANDLER) AND TRIM(HANDLER) != ''
		ORDER BY PART_NAME, TESTER, HANDLER;";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strPart = $LineMain["PART_NAME"];
		$strTester = $LineMain["TESTER"];
		$strHandler = $LineMain["HANDLER"];
		$aryReturn[$strPart][$strTester][$strHandler] = 1;
	}

	return $aryReturn;
}


function ADI_CHECK_DO_V2($iConRLM,$strThis,$strThat,$bolDebug=false) {
	echo __FUNCTION__."...";
	$decStartTime = time()+microtime();

	$strThis = "OPC";
	$strThat = "GT";

	#2024-10-02 RM refactored exception reporting
	#EXCEPTION_CHECK_CLEAR($iConRLM,$bolDebug);

	#2024-05-06 RM addd PTIME (after implementing blended rates)
	#2024-05-24 RM added UPH3_OPC versions which match against OPC step name and calc
	$aryAttrTry = array("UTPI","TESTTIME","INDEXTIME","CAT_12","CAT_38","CAT_41","PART_TYPE","PKG_TYPE","LEAD_COUNT","GENERIC","PTIME","PTIME_UPH3_OPC");

	$aryRXTRS = TRS_RX_GET();
	
	$aryST = SCHEMA_TABLE_FROM_SOURCE_GET($strThis);
	$strSchema = $aryST["SCHEMA"];
	$strTable = $aryST["TABLE"];

	$strSQL = "
		SELECT DISTINCT
		MFG_PART_NUM
		FROM ".$strSchema.".".$strTable." RA
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		ORDER BY MFG_PART_NUM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMFRPN = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryMFRPN[] = $LineMain["MFG_PART_NUM"];
	}

	foreach($aryMFRPN as $strMFRPN) {
		if($bolDebug === true) {
			echo $strMFRPN."...";
		}

		$aryFilter = array("MFG_PART_NUM"=>array($strMFRPN));

		$aryMain = array();
		$aryMain["THIS"] = PRR_FROM_SOURCE_GET($iConRLM,$aryAttrTry,$aryFilter,$strThis,$bolDebug);
		$aryMain["THAT"] = PRR_FROM_SOURCE_GET($iConRLM,$aryAttrTry,$aryFilter,$strThat,$bolDebug);

		if($strMFRPN == "LT1013IS8-T0") {
			#Print_R($aryMain["THIS"]["LT1013IS8-T0"]);
			#Print_R($aryMain["THAT"]["LT1013IS8-T0"]);
			#die();
		}

		

		#Print_R($aryMap["PART_STEP_TRS"]["LTC4271IUF#PBF-TQ"]);
		#die();

		try{
			$strSQL = "
				SELECT
				STEP_NAME_OPC,
				STEP_NAME_TRS,
				STEP_NAME_PP
				FROM ADI_ESLDATA.PART_STEP_TRS_OPC OPC
				INNER JOIN ADI_ESLDATA.PART_STEP_TRS_PP TRS_PP ON OPC.TRS_PP_ID = TRS_PP.TRS_PP_ID
				WHERE PART_NAME = '".mysqli_real_escape_string($iConRLM,$strMFRPN)."';";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			$aryMapPSTRS = array();
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				#this is kind of manual and needs to be changed when you change MES comparisons
				
				$strStepThis = $LineMain["STEP_NAME_OPC"];
				$strStepThat = $LineMain["STEP_NAME_PP"];

				$aryMapPSTRS["THIS"][$strStepThis] = $LineMain["STEP_NAME_TRS"];
				$aryMapPSTRS["THAT"][$strStepThat] = $LineMain["STEP_NAME_TRS"];
			}
			
			#Print_R($aryMapPSTRS);
			#die();

			$aryDelta = ADI_DELTA_GET($aryRXTRS,$aryMapPSTRS,$aryAttrTry,$aryMain,$strMFRPN,$bolDebug);

			#Print_R($aryDelta);
			if(count($aryDelta) > 0) {
				#quick summary
				$aryCount = array();
				foreach($aryDelta as $aryDeltaTemp) {
					$strMessage = $aryDeltaTemp["MESSAGE"];
					if(!isset($aryCount[$strMessage])) {
						$aryCount[$strMessage] = 0;
					}
					++$aryCount[$strMessage];
				}

				if($bolDebug === true) {
					echo count($aryDelta)." DELTA/S.\n";
					arsort($aryCount);
					foreach($aryCount as $strMessage => $intCount) {
						echo chr(9).$strMessage.": ".$intCount."\n";
					}
				}

				#Print_R($aryDelta);
				
				foreach($aryDelta as $aryX) {
					$intXID = EXCEPTION_ID_GET($iConRLM,$aryX,$bolDebug);
					EXCEPTION_SESSION_DO($iConRLM,$intXID,$bolDebug);
					#EXCEPTION_CHECK_DO($iConRLM,$aryX,$bolDebug);
				}
				
			}else{
				if($bolDebug === true) {
					echo "ALL MATCH.\n";
				}
			}

		} catch (exception $e) {
			$strMessage = $e->getMessage();
			echo $strMessage."\n";
		}
	}

	$decEndTime = time()+microtime();
	echo "DONE. ".number_format($decEndTime-$decStartTime,1)." s.).\n";
}


function ADI_DELTA_GET($aryRXTRS,$aryMapPSTRS,$aryAttrTry,$aryMain,$strMFRPN,$bolDebug=false) {
	$aryDelta = array();

	switch (true) {
		case !isset($aryMain["THAT"]["PRR_RA"][$strMFRPN]):
			$aryInfo = array(
				"MFG_PART_NUM_THIS"=>array($strMFRPN)
			);
			$aryDelta[] = array(
				"FUNCTION"=>__FUNCTION__,
				"MESSAGE"=>"MFG_PART_NUM IN THIS NOT IN THAT",
				"IDEN"=>array(),
				"INFO"=>$aryInfo
			);
			return $aryDelta;			
			break;
		case !isset($aryMain["THIS"]["PRR_RA"][$strMFRPN]):
			$aryInfo = array(
				"MFG_PART_NUM_THAT"=>array($strMFRPN)
			);
			$aryDelta[] = array(
				"FUNCTION"=>__FUNCTION__,
				"MESSAGE"=>"MFG_PART_NUM IN THAT NOT IN THIS",
				"IDEN"=>array(),
				"INFO"=>$aryInfo
			);
			return $aryDelta;
			break;
		case count($aryMapPSTRS) == 0:
			$aryInfo = array(
				"MFG_PART_NUM_THIS"=>array($strMFRPN)
			);
			$aryDelta[] = array(
				"FUNCTION"=>__FUNCTION__,
				"MESSAGE"=>"MISSING STEP NAME MAPPING",
				"IDEN"=>array(),
				"INFO"=>$aryInfo
			);
			return $aryDelta;
			break;
	}

	#Print_R($aryMain["THIS"]["PRR_RA"][$strMFRPN]);
	#Print_R($aryMain["THAT"]["PRR_RA"][$strMFRPN]);
	#die();

	foreach($aryMain["THIS"]["PRR_RA"][$strMFRPN] as $strSRIThis => $aryThis) {}
	foreach($aryMain["THAT"]["PRR_RA"][$strMFRPN] as $strSRIThat => $aryThat) {}

	
	/*
	echo "OPC\n";
	Print_R($aryMain["OPC"]["PRR_RA"][$strMFRPN]);
	echo "GT\n";
	Print_R($aryMain["GT"]["PRR_RA"][$strMFRPN]);
	*/

	$aryIden = array(
		"MFG_PART_NUM"=>array($strMFRPN),
		"SAP_RTE_ID_THIS"=>array($strSRIThis),
		"SAP_RTE_ID_THAT"=>array($strSRIThat)
	);

	#Print_R($aryMapPSTRS);
	#die();
	#same count of steps hopefully
	#specifically exclude BAKE, BURNIN. this is one-way (THIS vs THAT)

	#$aryTry = array("BAKE","BURNIN");
	$aryDelete = array("THIS"=>array(),"THAT"=>array());
#	foreach($aryTry as $strTry) {
	foreach($aryThis as $decSeq => $arySeqTemp) {
		$strStepNameThis = $arySeqTemp["STEP_NM"];

		#2024-05-16 RM was testing for BURNIN in step name, they have preburnin, postburnin etc.
		#now just test if its been mapped (. if not, delete it
		if(!isset($aryMapPSTRS["THIS"][$strStepNameThis])) {
			$aryDelete["THIS"][$decSeq] = $strStepNameThis;
		}else{
			$strStepNameTRS = $aryMapPSTRS["THIS"][$strStepNameThis];
			$aryResponseStep = ROUTE_STEP_PARSE($aryRXTRS,$strStepNameTRS,$bolDebug);
			
			switch (true) {
				case isset($aryResponseStep["IS_BTH"]):
					$aryDelete["THIS"][$decSeq] = $strStepNameThis;
					break;
			}
		}
		/*
			$strStepNameTRS = $aryMapPSTRS["THIS"][$strStepNameThis];
			$aryResponseStep = ROUTE_STEP_PARSE($aryRXTRS,$strStepNameTRS,$bolDebug);

			#echo $strStepNameThis."\n";
			#Print_R($aryResponseStep);

			switch (true) {
				case isset($aryResponseStep["IS_BRN"]):
					$aryDelete["THIS"][$decSeq] = $strStepNameThis;
					break;
				case isset($aryResponseStep["IS_BKE"]):
					$aryDelete["THIS"][$decSeq] = $strStepNameThis;
					break;
			}
		}
		*/
	}

	#Print_R($aryDelete);
	#die();

	$aryTemp = array();
	foreach(array_keys($aryThis) as $decSeq) {
		if(!isset($aryDelete["THIS"][$decSeq])) {
			$aryTemp[$decSeq] = $aryThis[$decSeq];
		}
	}
	$aryThis = $aryTemp;

	$aryTemp = array();
	foreach(array_keys($aryThat) as $decSeq) {
		if(!isset($aryDelete["THAT"][$decSeq])) {
			$aryTemp[$decSeq] = $aryThat[$decSeq];
		}
	}
	$aryThat = $aryTemp;
	

	#Print_R($aryMapPSTRS);
	#Print_R($aryThis);
	#Print_R($aryThat);
	#die();

	#after removal if count is still not correct, return here
	$aryInfo = array(
		"STEP_NM_THIS"=>array(),
		"STEP_NM_THAT"=>array(),
		"STEP_NM_THIS_TRS"=>array(),
		"STEP_NM_THAT_TRS"=>array()
	);

	if(count($aryThis) == count($aryThat)) {
		foreach($aryDelete["THIS"] as $strStepNameThis) {
			$aryInfo["STEP_NM_THIS"][] = $strStepNameThis;
			if(isset($aryMapPSTRS["THIS"][$strStepNameThis])) {
				$aryInfo["STEP_NM_THIS_TRS"][] = $aryMapPSTRS["THIS"][$strStepNameThis];
			}
		}

		foreach($aryDelete["THAT"] as $strStepNameThat) {
			$aryInfo["STEP_NM_THAT"][] = $strStepNameThat;
			if(isset($aryMapPSTRS["THAT"][$strStepNameThat])) {
				$aryInfo["STEP_NM_THAT_TRS"][] = $aryMapPSTRS["THAT"][$strStepNameThat];
			}
		}

		if(count($aryInfo["STEP_NM_THIS"]) > 0) {
			$aryDelta[] = array(
				"FUNCTION"=>__FUNCTION__,
				"MESSAGE"=>"KNOWN EXTRA STEPS IN THIS NOT IN THAT",
				"IDEN"=>$aryIden,
				"INFO"=>$aryInfo
			);
		}

		if(count($aryInfo["STEP_NM_THAT"]) > 0) {
			#2024-04-25 RM as of today this will never fire since OPC has steps not in PP
			$aryDelta[] = array(
				"FUNCTION"=>__FUNCTION__,
				"MESSAGE"=>"KNOWN EXTRA STEPS IN THAT NOT IN THIS",
				"IDEN"=>$aryIden,
				"INFO"=>$aryInfo
			);
		}

		#DO NOT RETURN!!  just make a note of it and move on

	}else{
		foreach($aryThis as $decSeqThis => $arySeqTemp) {
			$strStepName = $arySeqTemp["STEP_NM"];
			$aryInfo["STEP_NM_THIS"][] = $strStepName;
			if(isset($aryMapPSTRS["THIS"][$strStepName])) {
				$aryInfo["STEP_NM_THIS_TRS"][] = $aryMapPSTRS["THIS"][$strStepName];
			}
		}
		foreach($aryThat as $decSeqThat => $arySeqTemp) {
			$strStepName = $arySeqTemp["STEP_NM"];
			$aryInfo["STEP_NM_THAT"][] = $strStepName;
			if(isset($aryMapPSTRS["THAT"][$strStepName])) {
				$aryInfo["STEP_NM_THAT_TRS"][] = $aryMapPSTRS["THAT"][$strStepName];
			}
		}

		$aryDelta[] = array(
			"FUNCTION"=>__FUNCTION__,
			"MESSAGE"=>"DIFFERENT STEP COUNT",
			"IDEN"=>$aryIden,
			"INFO"=>$aryInfo
		);

		#Print_R($aryThis);
		#Print_R($aryThat);
		#die();

		#cannot make an assumption in maching steps if count is different
		return $aryDelta;
	}


	#Print_R($aryThis);
	#Print_R($aryThat);
	#Print_R($aryDelta);
	#die();
	

	$arySeq = array();
	$intCtr = 0;

	$aryTemp = array_keys($aryThis);
	foreach($aryTemp as $intCtr => $decSeq) {
		$arySeq[$intCtr]["THIS"] = $decSeq;
	}
	
	$aryTemp = array_keys($aryThat);
	foreach($aryTemp as $intCtr => $decSeq) {
		$arySeq[$intCtr]["THAT"] = $decSeq;
	}


	#$aryReturn["PRR_RA"][$strMFRPN][$strSRI][$decSeq]["PRI"][$intPri] = $aryTemp;
	#Print_R($arySeq);
	foreach(array_keys($arySeq) as $intCtr) {
		$decSeqThis = $arySeq[$intCtr]["THIS"];
		$decSeqThat = $arySeq[$intCtr]["THAT"];

		$strStepNameThis = $aryThis[$decSeqThis]["STEP_NM"];
		$strStepNameThat = $aryThat[$decSeqThat]["STEP_NM"];

		#echo $strStepNameThis." / ".$strStepNameThat."\n";

		$aryIdenStep = $aryIden;
		$aryIdenStep["RTE_SEQ_NUM_THIS"] = array($decSeqThis);
		$aryIdenStep["RTE_SEQ_NUM_THAT"] = array($decSeqThat);
		$aryIdenStep["STEP_NM_THIS"] = array($strStepNameThis);

		$strStepNameTRS = null;
		$aryResponseStep = array();
		if(isset($aryMapPSTRS["THIS"][$strStepNameThis])) {
			$strStepNameTRS = $aryMapPSTRS["THIS"][$strStepNameThis];
			$aryIdenStep["STEP_NM_THIS_TRS"][] = $strStepNameTRS;

			$aryResponseStep = ROUTE_STEP_PARSE($aryRXTRS,$strStepNameTRS,$bolDebug);

			#echo $strStepNameThis." / ".$strStepNameTRS."\n";
			#Print_R($aryResponseStep);			
		}

		$aryIdenStep["STEP_NM_THAT"] = array($strStepNameThat);
		if(isset($aryMapPSTRS["THAT"][$strStepNameThat])) {
			$aryIdenStep["STEP_NM_THAT_TRS"][] = $aryMapPSTRS["THAT"][$strStepNameThat];
		}
		
		switch (true) {
			case isset($aryResponseStep["IS_MULTITEMP"]):
				break;
			case is_null($aryThis[$decSeqThis]["TEMP_C"]) && $aryThat[$decSeqThat]["TEMP_C"] == 0:
				break;
			case $aryThis[$decSeqThis]["TEMP_C"] == 0 && is_null($aryThat[$decSeqThat]["TEMP_C"]):
				break;
			case $aryThis[$decSeqThis]["TEMP_C"] != $aryThat[$decSeqThat]["TEMP_C"]:
				$aryInfo = array(
					"TEMP_C_THIS"=>array($aryThis[$decSeqThis]["TEMP_C"]),
					"TEMP_C_THAT"=>array($aryThat[$decSeqThat]["TEMP_C"])
				);

				$aryDelta[] = array(
					"FUNCTION"=>__FUNCTION__,
					"MESSAGE"=>"DIFFERENT TEMP_C",
					"IDEN"=>$aryIdenStep,
					"INFO"=>$aryInfo
				);
				break;
		}

		#2024-05-24 RM VP request, dont examine resources for NSE steps (already ignoring testtime, indxrime etc)
		$bolSkip =false;
		switch (true) {
			case isset($aryResponseStep["IS_NSE"]) && isset($aryResponseStep["IS_QA"]):
				$bolSkip = true;
				break;
		}

		if($bolSkip === true) {
			continue;
		}
	
		$aryData = array("THIS"=>array(),"THAT"=>array());

		foreach($aryThis[$decSeqThis]["PRI"] as $intPri => $aryTemp) {
			$intRSID = $aryTemp["RES_SET_ID"];
			$intASID = $aryTemp["ATTR_SET_ID"];
			$intHWSID = $aryTemp["HW_SET_ID"];

			$aryData["THIS"][$intPri] = array(
				"BOR"=>$aryMain["THIS"]["BOR"][$intRSID],
				"ATTR"=>$aryMain["THIS"]["ATTR"][$intASID],
				"HW"=>$aryMain["THIS"]["HW"][$intHWSID]
			);
		}

		foreach($aryThat[$decSeqThat]["PRI"] as $intPri => $aryTemp) {
			$intRSID = $aryTemp["RES_SET_ID"];
			$intASID = $aryTemp["ATTR_SET_ID"];
			$intHWSID = $aryTemp["HW_SET_ID"];

			$aryData["THAT"][$intPri] = array(
				"BOR"=>$aryMain["THAT"]["BOR"][$intRSID],
				"ATTR"=>$aryMain["THAT"]["ATTR"][$intASID],
				"HW"=>$aryMain["THAT"]["HW"][$intHWSID]
			);
		}

		if($bolDebug === true) {
			echo chr(9).$decSeqThis." (".$strStepNameThis.") / ".$decSeqThat." (".$strStepNameThat.")...";
		}
		#Print_R($aryData);
		#die();

		#2024-04-02 RM figure out some real this-that comparison
		#echo "THIS\n";
		#Print_R($aryData["THIS"]);
		#echo "THAT\n";
		#Print_R($aryData["THAT"]);
		#die();


		#2024-05-01 RM eliminated HW PRI/ALT keys
		#TT is THIS/THAT. compare in both directions
		#determine exact matches, eliminate for comparison
		$aryResMatch = array();
		foreach($aryData["THIS"] as $intPriThis => $aryThisTemp) {
			foreach($aryData["THAT"] as $intPriThat => $aryThatTemp) {
				$bolMatch = true;

				switch (true) {
					case $aryThisTemp["BOR"]["TESTER"] != $aryThatTemp["BOR"]["TESTER"]:
						#echo $intPriThis." / ".$intPriThat." / 1\n";
						$bolMatch = false;
						break;
					case $aryThisTemp["BOR"]["HANDLER"] != $aryThatTemp["BOR"]["HANDLER"]:
						#echo $intPriThis." / ".$intPriThat." / 2\n";
						$bolMatch = false;
						break;
					case count(array_diff(array_keys($aryThisTemp["HW"]),array_keys($aryThatTemp["HW"]))) > 0:
						#echo $intPriThis." / ".$intPriThat." / 3\n";
						$bolMatch = false;
						break;
					case count(array_diff(array_keys($aryThatTemp["HW"]),array_keys($aryThisTemp["HW"]))) > 0:
						#echo $intPriThis." / ".$intPriThat." / 4\n";
						$bolMatch = false;
						break;
					default:
						#check HW names and quantities
						foreach(array_keys($aryThisTemp["HW"]) as $strHWType) {
							switch (true) {
								case count(array_diff(array_keys($aryThisTemp["HW"][$strHWType]),array_keys($aryThatTemp["HW"][$strHWType]))) > 0:
									#echo $intPriThis." / ".$intPriThat." / 5\n";

							
									$bolMatch = false;
									break;
								case count(array_diff(array_keys($aryThatTemp["HW"][$strHWType]),array_keys($aryThisTemp["HW"][$strHWType]))) > 0:
									#echo $intPriThis." / ".$intPriThat." / 6\n";
						
									$bolMatch = false;
									break;
								default:
									foreach($aryThisTemp["HW"][$strHWType] as $strHWName => $intRQThis) {
										$intRQThat = $aryThatTemp["HW"][$strHWType][$strHWName];

										if($intRQThis != $intRQThat) {
											#echo $intPriThis." / ".$intPriThat." / 7\n";
						
											$bolMatch = false;
											break;
										}
									}

									if($bolMatch === true) {
										$aryResMatch["THIS"][$intPriThis][$intPriThat] = 1;
										$aryResMatch["THAT"][$intPriThat][$intPriThis] = 1;
									}

									break;
							}
						}
						break;
				}
			}
		}

		if($strMFRPN == "LT6230IS6#PBF-T0" && $strStepNameTRS == "QA2") {
		#	Print_R($aryResMatch);
		#	Print_R($aryData);
		#	die();
		}


		$aryDone = array();
		foreach(array_keys($aryData) as $strTT1) {
			foreach(array_keys($aryData) as $strTT2) {
				if($strTT1 != $strTT2) {
					foreach($aryData[$strTT1] as $intPri1 => $aryTemp1) {
						$bolFoundTH = false;

						foreach($aryData[$strTT2] as $intPri2 => $aryTemp2) {
							#if($strMFRPN == "LT1027CCS8-5#PBF-T0" && $strStepNameThis == "TMFT1_FT" && $intPri1 == 1 && $intPri2 == 1) {
								#Print_R($aryTemp1);
								#Print_R($aryTemp2);
								#die();
							#}

							$strTester = $aryTemp1["BOR"]["TESTER"];
							$strHandler = $aryTemp1["BOR"]["HANDLER"];

							$aryIdenSetup = $aryIdenStep;
							$aryIdenSetup["PRIO_CD_".$strTT1] = array($intPri1);
							$aryIdenSetup["PRIO_CD_".$strTT2] = array($intPri2);
							$aryIdenSetup["TESTER"] = array($strTester);
							$aryIdenSetup["HANDLER"] = array($strHandler);



							#bypass if we already measured these to completion (including but not limited to res match)
							switch (true) {
								case isset($aryDone[$strTT1][$intPri1][$intPri2]):
									#2024-06-10 RM why
									$bolFoundTH = true;
									break;
								case isset($aryDone[$strTT2][$intPri2][$intPri1]):
									#2024-06-10 RM why
									$bolFoundTH = true;
									break;
								default:
									$bolMatchTH = false;
							
									switch (true) {
										#bypass res check (but still do attr) if we already established resource match
										case $aryTemp1["BOR"]["TESTER"] != $aryTemp2["BOR"]["TESTER"]:
											break;
										case $aryTemp1["BOR"]["HANDLER"] != $aryTemp2["BOR"]["HANDLER"]:
											break;
										case isset($aryResMatch[$strTT1][$intPri1]):
											#2024-05-09 RM even if tester and handler match, dont check resources if this was found to be identical to *something*. still mark bolmatchTH as true so we compare attributes though
											$bolMatchTH = true;
											break;
										case isset($aryResMatch[$strTT2][$intPri2]):									
											#2024-05-09 RM even if tester and handler match, dont check resources if this was found to be identical to *something*. still mark bolmatchTH as true so we compare attributes though
											$bolMatchTH = true;
											break;
										default:
											$bolMatchTH = true;

											#check hardware types
											$bolMatchHW = true;

											$aryHWType = array_intersect(array_keys($aryTemp1["HW"]),array_keys($aryTemp2["HW"]));
											$aryDiff1 = array_diff(array_keys($aryTemp1["HW"]),array_keys($aryTemp2["HW"]));
											$aryDiff2 = array_diff(array_keys($aryTemp2["HW"]),array_keys($aryTemp1["HW"]));

											foreach($aryDiff1 as $strHWType) {
												$aryHWName = array_keys($aryTemp1["HW"][$strHWType]);

												$aryDelta[] = array(
													"FUNCTION"=>__FUNCTION__,
													"MESSAGE"=>"HW_TYPE IN ".$strTT1." NOT IN ".$strTT2,
													"IDEN"=>$aryIdenSetup,
													"INFO"=>array(
														"HW_TYPE"=>$aryDiff1,
														"HW_NM"=>$aryHWName
													)
												);

												$bolMatchHW = false;
											}

											foreach($aryDiff2 as $strHWType) {
												$aryHWName = array_keys($aryTemp2["HW"][$strHWType]);

												$aryDelta[] = array(
													"FUNCTION"=>__FUNCTION__,
													"MESSAGE"=>"HW_TYPE IN ".$strTT2." NOT IN ".$strTT1,
													"IDEN"=>$aryIdenSetup,
													"INFO"=>array(
														"HW_TYPE"=>array($strHWType),
														"HW_NM"=>$aryHWName
													)
												);

												$bolMatchHW = false;
											}

											#within each matching type, check for existence of each name
											foreach($aryHWType as $strHWType) {
												$aryIdenHW = $aryIdenSetup;
												$aryIdenHW["HW_TYPE"] = array($strHWType);

												$aryNameRQ1 = $aryTemp1["HW"][$strHWType];
												$aryNameRQ2 = $aryTemp2["HW"][$strHWType];

												$aryDiff1 = array_diff(array_keys($aryNameRQ1),array_keys($aryNameRQ2));
												$aryDiff2 = array_diff(array_keys($aryNameRQ2),array_keys($aryNameRQ1));

												if(count($aryDiff1) > 0) {
													$strHWName = "HW_NM_".$strTT1;

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>"HW_NM IN ".$strTT1." NOT IN ".$strTT2,
														"IDEN"=>$aryIdenHW,
														"INFO"=>array(
															$strHWName=>$aryDiff1
														)
													);

													$bolMatchHW = false;
												}

												if(count($aryDiff2) > 0) {
													$strHWName = "HW_NM_".$strTT2;
													
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>"HW_NM IN ".$strTT2." NOT IN ".$strTT1,
														"IDEN"=>$aryIdenHW,
														"INFO"=>array(
															$strHWName=>$aryDiff2
														)
													);

													$bolMatchHW = false;
												}

												#2024-05-01 RM check quantity_required
												
												$aryNameMatch = array_intersect(array_keys($aryNameRQ1),array_keys($aryNameRQ2));
												foreach($aryNameMatch as $strHWName) {
													if($aryNameRQ1[$strHWName] != $aryNameRQ2[$strHWName]) {
														$strField1 = "REQUIRED_QTY_".$strTT1;
														$strField2 = "REQUIRED_QTY_".$strTT2;

														$aryInfo = array(
															$strField1=>array($aryNameRQ1[$strHWName]),
															$strField2=>array($aryNameRQ2[$strHWName])
														);
													
														$aryDelta[] = array(
															"FUNCTION"=>__FUNCTION__,
															"MESSAGE"=>"REQUIRED_QTY DIFF",
															"IDEN"=>array(
																"HW_TYPE"=>array($strHWType),
																"HW_NM"=>array($strHWName)
															),
															"INFO"=>$aryInfo
														);
													}
												}
											}
											break;
									}


									if($bolMatchTH === true) {


										$bolDebug = false;
										if($strStepNameThis == "FT1_TEST_FT" && $aryTemp1["BOR"]["TESTER"] == "TS88_STD_LT" && $aryTemp1["BOR"]["HANDLER"] == "S170_K_RW_AHC_F2" && $aryTemp1["BOR"]["TESTER"] == $aryTemp2["BOR"]["TESTER"] && $aryTemp1["BOR"]["HANDLER"] == $aryTemp2["BOR"]["HANDLER"]) {
											#$bolDebug = true;
											#echo $intPri1." / ".$intPri2."\n";
											#Print_R($aryTemp1["ATTR"]);
											#Print_R($aryTemp2["ATTR"]);
											#Print_R($aryResMatch);
											#Print_R($aryDone);
											#Print_R($aryDelta);
											#die();

										}


										#FOUND is higher level than MATCH. 
										$bolFoundTH = true;

										foreach($aryAttrTry as $strAttr) {
											$strField1 = $strAttr."_".$strTT1;
											$strField2 = $strAttr."_".$strTT2;

											switch (true) {
												case ($strAttr == "TESTTIME" || $strAttr == "INDEXTIME" || $strAttr == "UTPI" || $strAttr == "PTIME" || $strAttr == "PTIME_UPH3_OPC" || $strAttr == "TESTTIME_UPH3_OPC" || $strAttr == "IDNEXTIME_UPH3_OPC") && (isset($aryResponseStep["IS_VOS"]) || isset($aryResponseStep["IS_NSE"])):
													if($bolDebug === true) {
														echo $strAttr.":1\n";
													}
													#echo $strStepNameTRS." / ".$strAttr."\n";
													#Print_R($aryResponseStep);


													#2024-05-24 RM NSE QC is already being ignored at the step level. so NSE QC here will never be checked

													#2024-05-07 RM dont measure testtime, indextime, ptime for tcvos, noise
													break;
												#case $strAttr == "PTIME" && strpos(strtoupper($strHandler),"HANDTEST") !== false:
													#2024-05-07 RM dont check ptime for handtest
													#2024-05-08 RM do
												#	break;
												case $strTT1 == "THIS" && ($strAttr == "PTIME_UPH3_OPC" || $strAttr == "TESTTIME_UPH3_OPC" || $strAttr == "INDEXTIME_UPH3_OPC"):
													
													$strAttrPure = str_replace("_UPH3_OPC","",$strAttr);

													if(isset($aryTemp1["ATTR"][$strAttr]) && isset($aryTemp1["ATTR"][$strAttrPure])) {
														$strField1 = $strAttr;
														$strField2 = $strAttrPure."_".$strTT1;

														switch (true) {
															case round($aryTemp1["ATTR"][$strAttr],1) == round($aryTemp1["ATTR"][$strAttrPure],1):
																break;
															case $aryTemp1["ATTR"][$strAttr] < 1 && $aryTemp1["ATTR"][$strAttrPure] > 0:
																
																$aryDelta[] = array(
																	"FUNCTION"=>__FUNCTION__,
																	"MESSAGE"=>$strAttr." NON-ZERO IN ".$strTT1.", ZERO IN UPH3_OPC",
																	"IDEN"=>$aryIdenSetup,
																	"INFO"=>array(
																		$strField1=>array($aryTemp1["ATTR"][$strAttr]),
																		$strField2=>array($aryTemp1["ATTR"][$strAttrPure])
																	)
																);
																break;
															case $aryTemp1["ATTR"][$strAttr] > 0 && $aryTemp1["ATTR"][$strAttrPure] < 1:
																
																$aryDelta[] = array(
																	"FUNCTION"=>__FUNCTION__,
																	"MESSAGE"=>$strAttr." ZERO IN ".$strTT1.", NON-ZERO IN UPH3_OPC",
																	"IDEN"=>$aryIdenSetup,
																	"INFO"=>array(
																		$strField1=>array($aryTemp1["ATTR"][$strAttr]),
																		$strField2=>array($aryTemp2["ATTR"][$strAttrPure])
																	)
																);
																break;
															case abs($aryTemp1["ATTR"][$strAttr] - $aryTemp1["ATTR"][$strAttrPure]) / $aryTemp1["ATTR"][$strAttrPure] < 0.01:

																$aryDelta[] = array(
																	"FUNCTION"=>__FUNCTION__,
																	"MESSAGE"=>$strAttr." IN THIS WITHIN 1% OF UPH3_OPC", 
																	"IDEN"=>$aryIdenSetup,
																	"INFO"=>array(
																		$strField1=>array($aryTemp1["ATTR"][$strAttr]),
																		$strField2=>array($aryTemp2["ATTR"][$strAttrPure])
																	)
																);
																break;
															default:

																$aryDelta[] = array(
																	"FUNCTION"=>__FUNCTION__,
																	"MESSAGE"=>$strAttr." IN THIS DIFF FROM UPH3_OPC",
																	"IDEN"=>$aryIdenSetup,
																	"INFO"=>array(
																		$strField1=>array($aryTemp1["ATTR"][$strAttr]),
																		$strField2=>array($aryTemp2["ATTR"][$strAttrPure])
																	)
																);
																break;
														}
													}

													break;
												case ($strAttr == "TESTTIME" || $strAttr == "INDEXTIME") && (isset($aryTemp1["ATTR"]["IS_BLENDED"]) || isset($aryTemp2["ATTR"]["IS_BLENDED"])):
													if($bolDebug === true) {
														echo $strAttr.":2\n";
													}
													#Print_R($aryTemp1);
													#Print_R($aryTemp2);
													#die();
													#2024-05-08 RM dont check testtime, index time for blended setups. keep checking UTPI, PTIME though
													break;
												case isset($aryTemp1["ATTR"][$strAttr]) && !isset($aryTemp2["ATTR"][$strAttr]):
													if($bolDebug === true) {
														echo $strAttr.":3\n";
													}
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." IN ".$strTT1." NOT IN ".$strTT2,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr])
														)
													);

													break;
												case !isset($aryTemp1["ATTR"][$strAttr]) && isset($aryTemp2["ATTR"][$strAttr]):
													if($bolDebug === true) {
														echo $strAttr.":4\n";
													}

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." IN ".$strTT2." NOT IN ".$strTT1,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case (!is_null($aryTemp1["ATTR"][$strAttr]) && trim($aryTemp1["ATTR"][$strAttr]) != "") && (is_null($aryTemp2["ATTR"][$strAttr]) || trim($aryTemp2["ATTR"][$strAttr]) == ""):
													if($bolDebug === true) {
														echo $strAttr.":5\n";
													}

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." IN ".$strTT1." NOT IN ".$strTT2,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case (is_null($aryTemp1["ATTR"][$strAttr]) || trim($aryTemp1["ATTR"][$strAttr]) == "") && (!is_null($aryTemp2["ATTR"][$strAttr]) && trim($aryTemp2["ATTR"][$strAttr]) != ""):
													if($bolDebug === true) {
														echo $strAttr.":6\n";
													}
													
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." IN ".$strTT2." NOT IN ".$strTT1,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												#2024-04-25 RM special case for default test times (5.550 on my side, 0.00 in TRS SUS)
												case $strAttr == "TESTTIME" && ($aryTemp1["ATTR"][$strAttr] * 1 != 5.55 && $aryTemp1["ATTR"][$strAttr] * 1 > 0) && ($aryTemp2["ATTR"][$strAttr] * 1 == 5.55 || $aryTemp2["ATTR"][$strAttr] * 1 <= 0 || is_null($aryTemp2["ATTR"][$strAttr])):
													if($bolDebug === true) {
														echo $strAttr.":7\n";
													}

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>"DEFAULT (MISSING) ".$strAttr." IN ".$strTT2,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case $strAttr == "TESTTIME" && ($aryTemp1["ATTR"][$strAttr] * 1 == 5.55 || $aryTemp1["ATTR"][$strAttr] * 1 <= 0 || is_null($aryTemp1["ATTR"][$strAttr])) && ($aryTemp2["ATTR"][$strAttr] * 1 != 5.55 && $aryTemp2["ATTR"][$strAttr] * 1 > 0):

													if($bolDebug === true) {
														echo $strAttr.":8\n";
													}

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>"DEFAULT (MISSING) ".$strAttr." IN ".$strTT1,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;													
												case $strAttr == "TESTTIME" && ($aryTemp1["ATTR"][$strAttr] * 1 == 5.55 || $aryTemp1["ATTR"][$strAttr] * 1 <= 0 || is_null($aryTemp1["ATTR"][$strAttr])) && ($aryTemp2["ATTR"][$strAttr] * 1 == 5.55 || $aryTemp2["ATTR"][$strAttr] * 1 <= 0 || is_null($aryTemp2["ATTR"][$strAttr])):

													if($bolDebug === true) {
														echo $strAttr.":9\n";
													}
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>"DEFAULT (MISSING) ".$strAttr." IN ".$strTT1." AND ".$strTT2,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case $aryTemp1["ATTR"][$strAttr] == $aryTemp2["ATTR"][$strAttr]:
													if($bolDebug === true) {
														echo $strAttr.":10\n";
													}
													break;													
												case $aryTemp1["ATTR"][$strAttr] != 0 && $aryTemp2["ATTR"][$strAttr] == 0:
													if($bolDebug === true) {
														echo $strAttr.":11\n";
													}
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." NON-ZERO IN ".$strTT1.", ZERO IN ".$strTT2,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case $aryTemp1["ATTR"][$strAttr] == 0 && $aryTemp2["ATTR"][$strAttr] != 0:
													if($bolDebug === true) {
														echo $strAttr.":12\n";
													}
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." NON-ZERO IN ".$strTT2.", ZERO IN ".$strTT1,
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case ($strAttr == "TESTTIME" || $strAttr == "INDEXTIME") && abs($aryTemp1["ATTR"][$strAttr] - $aryTemp2["ATTR"][$strAttr]) < 0.1:
													#2024-05-07 RM testtime, indextime within 0.1 seconds is a specific message. THIS IS NOT PERCENTAGE
													if($bolDebug === true) {
														echo $strAttr.":13\n";
													}
													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." WITHIN 0.1 SECOND",
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case $strAttr == "PTIME" && abs(($aryTemp1["ATTR"][$strAttr] - $aryTemp2["ATTR"][$strAttr]) / $aryTemp2["ATTR"][$strAttr]) < 0.01:
													#PTIME is 1% diff check
													if($bolDebug === true) {
														echo $strAttr.":14\n";
													}

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." WITHIN 1%", 
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
												case $aryTemp1["ATTR"][$strAttr] != $aryTemp2["ATTR"][$strAttr]:
													if($bolDebug === true) {
														echo $strAttr.":15\n";
													}

													$aryDelta[] = array(
														"FUNCTION"=>__FUNCTION__,
														"MESSAGE"=>$strAttr." DIFF",
														"IDEN"=>$aryIdenSetup,
														"INFO"=>array(
															$strField1=>array($aryTemp1["ATTR"][$strAttr]),
															$strField2=>array($aryTemp2["ATTR"][$strAttr])
														)
													);

													break;
											}
										}
										if($strStepNameThis == "FT1_TEST_FT" && $aryTemp1["BOR"]["TESTER"] == "TS88_STD_LT" && $aryTemp1["BOR"]["HANDLER"] == "S170_K_RW_AHC_F2" && $aryTemp1["BOR"]["TESTER"] == $aryTemp2["BOR"]["TESTER"] && $aryTemp1["BOR"]["HANDLER"] == $aryTemp2["BOR"]["HANDLER"]) {
											#Print_R($aryTemp1["ATTR"]);
											#Print_R($aryTemp2["ATTR"]);
											#Print_R($aryResMatch);
											#Print_R($aryDone);
											#Print_R($aryDelta);
											#die();

										}
										$bolDebug = false;

										#2024-05-01 RM set this here so we dont re-process it
										#2024-06-10 RM moved this up so it only happens upon match
										$aryDone[$strTT1][$intPri1][$intPri2] = 1;
										$aryDone[$strTT2][$intPri2][$intPri1] = 1;
									}


									break;
							}
						}

						if($bolFoundTH !== true) {
							#echo $strTT1." / ".$intPri1." / ".$aryTemp1["BOR"]["TESTER"]." / ".$aryTemp1["BOR"]["HANDLER"]."\n";
							#echo $strTT2."\n";
							#Print_R($aryData[$strTT2]);
							#Print_R($aryDone);
							$intPri = "PRIO_CD_".$strTT1;
							$strTester = "TESTER_".$strTT1;
							$strHandler = "HANDLER_".$strTT1;

							$aryDelta[] = array(
								"FUNCTION"=>__FUNCTION__,
								"MESSAGE"=>"TESTER+HANDLER IN ".$strTT1." NOT IN ".$strTT2,
								"IDEN"=>$aryIdenStep,
								"INFO"=>array(
									$intPri=>array($intPri1),
									$strTester=>array($aryTemp1["BOR"]["TESTER"]),
									$strHandler=>array($aryTemp1["BOR"]["HANDLER"])
								)
							);

							#die();
						}
					}
				}
			}
		}

		if($bolDebug === true) {
			if(count($aryDelta) > 0) {
				echo number_format(count($aryDelta),0)." DELTA FOUND.\n";
			}else{
				echo "ALL MATCH.\n";
			}
		}
	}

	#Print_R($aryDelta);
	#die();
	#2024-04-04 RM maybe consolidate info by iden & message
	return $aryDelta;

}


function SCHEMA_TABLE_FROM_SOURCE_GET($strSource) {
	#2024-04-04 RM default
	$strSchema = "BODS_JDA_ADI_PRE_OPC";
	$strTable = "RES_ALTERNATES";

	switch ($strSource) {
		case "OPC":
			$strSchema = "BODS_JDA_ADI";
			$strTable = "RES_ALTERNATES_OPC";
			break;
		case "PG":
			$strSchema = "BODS_JDA_ADI";
			$strTable = "RES_ALTERNATES_PG";
			break;
	}

	return array("SCHEMA"=>$strSchema,"TABLE"=>$strTable);
}


function PRR_FROM_SOURCE_GET($iConRLM,$aryAttrTry,$aryFilter,$strSource,$bolDebug=false) {
	$decStartTime = time()+microtime();
	

	#2024-04-04 RM default
	$strSchema = "BODS_JDA_ADI_PRE_OPC";
	$strTable = "RES_ALTERNATES";

	switch ($strSource) {
		case "OPC":
			$strSchema = "BODS_JDA_ADI";
			$strTable = "RES_ALTERNATES_OPC";
			break;
		case "PG":
			$strSchema = "BODS_JDA_ADI";
			$strTable = "RES_ALTERNATES_PG";
			break;
	}

	#echo __FUNCTION__." (".$strSource." = ".$strSchema.".".$strTable.").\n";


	$aryFilterSQL = array("ISNULL(NULL)");
	$aryMap = array(
		"RSID_MFRPN_SRI_RSN_PRI"=>array(),
		"HWSID_RSID"=>array(),
		"MFRPN_WRONG_RIGHT"=>array()
	);

	foreach(array_keys($aryFilter) as $strField) {
		$aryTemp = array();
		foreach($aryFilter[$strField] as $strValue) {
			if($strField == "MFG_PART_NUM" && $strSource == "GT") {
				#assumption here is that part filter is coming from OPC and is therefore correct
				$strMFRPNRight = $strValue;
				$strMFRPNWrong = $strMFRPNRight;

				if(strpos($strMFRPNRight,"^") !== false) {
					$strMFRPNWrong = str_replace("^",".",$strMFRPNRight);
					$aryMap["MFRPN_WRONG_RIGHT"][$strMFRPNWrong] = $strMFRPNRight;
				}

				$aryTemp[] = mysqli_real_escape_string($iConRLM,$strMFRPNWrong);
			}else{
				$aryTemp[] = mysqli_real_escape_string($iConRLM,$strValue);
			}
		}

		if(count($aryTemp) > 0) {
			$aryFilterSQL[] = $strField." IN ('".implode("','",$aryTemp)."')";
		}
	}

	#Print_R($aryMap["MFRPN_WRONG_RIGHT"]);
	#die();

	#echo chr(9)."PRR...";
	$decStartTimeTemp = $decStartTime;

	$strSQL = "
		SELECT
		PRR.SAP_RTE_ID,
		PRR.MFG_PART_NUM,
		PRR.RTE_SEQ_NUM,
		PRR.STEP_NM,
		PRR.TEMP_C,
		RA.PRS_ID,
		RA.RES_SET_ID,
		RA.ATTR_SET_ID,
		RA.PRIO_CD
		FROM ".$strSchema.".".$strTable." RA
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		INNER JOIN 
		(
			SELECT
			MAX(PRR.SAP_RTE_ID) SAP_RTE_ID
			FROM ".$strSchema.".".$strTable." RA
			INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
			WHERE ".implode(" AND ",$aryFilterSQL)."
			GROUP BY PRR.MFG_PART_NUM
		) PRR_FILTERED ON PRR.SAP_RTE_ID = PRR_FILTERED.SAP_RTE_ID
		ORDER BY MFG_PART_NUM, SAP_RTE_ID, RTE_SEQ_NUM, PRIO_CD;";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	#echo $strSQL."\n";
	#Print_R($aryMap["MFRPN_WRONG_RIGHT"]);
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	
	$aryReturn = array(
		"PRR_RA"=>array(),
		"BOR"=>array(),
		"ATTR"=>array(),
		"HW"=>array()
	);

	#dont actually need to map, just distinct values..but could be useful later?
	
	$aryASID = array();

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strMFRPN = $LineMain["MFG_PART_NUM"];
		if(isset($aryMap["MFRPN_WRONG_RIGHT"][$strMFRPN])) {
			$strMFRPN = $aryMap["MFRPN_WRONG_RIGHT"][$strMFRPN];
		}
		$strSRI = $LineMain["SAP_RTE_ID"];
		#2024-07-24 RM removed *1, apparently this converts it to integer
		$decSeq = $LineMain["RTE_SEQ_NUM"];
		$intRSID = $LineMain["RES_SET_ID"];
		$intASID = $LineMain["ATTR_SET_ID"];
		$intPri = $LineMain["PRIO_CD"];

		if(!isset($aryReturn["PRR_RA"][$strMFRPN][$strSRI][$decSeq])) {
			$aryReturn["PRR_RA"][$strMFRPN][$strSRI][$decSeq] = array(
				"STEP_NM"=>$LineMain["STEP_NM"],
				"TEMP_C"=>$LineMain["TEMP_C"],
				"PRS_ID"=>$LineMain["PRS_ID"],
				"PRI"=>array()
			);
		}

		$aryTemp = array(
			"RES_SET_ID"=>$intRSID,
			"ATTR_SET_ID"=>$intASID,
			"HW_SET_ID"=>null
		);

		$aryReturn["PRR_RA"][$strMFRPN][$strSRI][$decSeq]["PRI"][$intPri] = $aryTemp;

		$aryMap["RSID_MFRPN_SRI_RSN_PRI"][$intRSID][] = array(
			"MFG_PART_NUM"=>$strMFRPN,
			"SAP_RTE_ID"=>$strSRI,
			"RTE_SEQ_NUM"=>$decSeq,
			"PRIO_CD"=>$intPri
		);

		#dont need mapping this this...yet
		$aryASID[] = $intASID;
	}
	$aryASID = array_unique($aryASID);

	#Print_R($aryReturn["PRR_RA"]["LT1019ACN8-4^5#PBF-T0"]);

	$decEndTime = time()+microtime();
	#echo "DONE. (".number_format($decEndTime - $decStartTimeTemp,1)." s.).\n";
	$decStartTimeTemp = $decEndTime;


	#echo chr(9)."BOR...";

	$aryRSID = array_keys($aryMap["RSID_MFRPN_SRI_RSN_PRI"]);
	$aryTemp = array();
	while ($intRSID = array_pop($aryRSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intRSID);

		if(count($aryTemp) >= 1000 || count($aryRSID) == 0) {
			$strSQL = "
				SELECT
				RES_SET_ID,
				RES_TYPE,
				RES_NM
				FROM BODS_JDA_ADI.BILL_OF_RESOURCES
				WHERE RES_SET_ID IN (".implode(",",$aryTemp).");";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intRSID = $LineMain["RES_SET_ID"];
				$strType = $LineMain["RES_TYPE"];
				$aryReturn["BOR"][$intRSID][$strType] = $LineMain["RES_NM"];

				if($strType == "HARDWARE_SET") {
					$intHWSID = $LineMain["RES_NM"];
					$aryMap["HWSID_RSID"][$intHWSID][] = $intRSID;

					foreach($aryMap["RSID_MFRPN_SRI_RSN_PRI"][$intRSID] as $aryMapTemp) {
						$strMFRPN = $aryMapTemp["MFG_PART_NUM"];
						$strSRI = $aryMapTemp["SAP_RTE_ID"];
						$decSeq = $aryMapTemp["RTE_SEQ_NUM"];
						$intPri = $aryMapTemp["PRIO_CD"];
						$aryReturn["PRR_RA"][$strMFRPN][$strSRI][$decSeq]["PRI"][$intPri]["HW_SET_ID"] = $intHWSID;
					}
				}
			}
			$aryTemp = array();
		}
	}
	$decEndTime = time()+microtime();
	#echo "DONE. (".number_format($decEndTime - $decStartTimeTemp,1)." s.).\n";
	$decStartTimeTemp = $decEndTime;


	#echo chr(9)."HW...";
	$aryHWSID = array_keys($aryMap["HWSID_RSID"]);
	$aryTemp = array();
	while ($intHWSID = array_pop($aryHWSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$intHWSID);
		if(count($aryTemp) >= 1000 || count($aryHWSID) == 0) {
			$strSQL = "
				SELECT
				HW_SET_ID,
				HW_TYPE,
				HW_NM,
				REQUIRED_QTY
				FROM BODS_JDA_ADI.HARDWARE_SET
				WHERE HW_SET_ID IN (".implode(",",$aryTemp).")
				UNION
				SELECT
				HW_SET_ID,
				HW_TYPE,
				HW_NM,
				REQUIRED_QTY
				FROM BODS_JDA_ADI.HARDWARE_ALTERNATES
				WHERE HW_SET_ID IN (".implode(",",$aryTemp).")
				ORDER BY HW_SET_ID, HW_TYPE, HW_NM;";
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while  ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intHWSID = $LineMain["HW_SET_ID"];
				$strType = $LineMain["HW_TYPE"];
				$strName = $LineMain["HW_NM"];

				$aryReturn["HW"][$intHWSID][$strType][$strName] = $LineMain["REQUIRED_QTY"];
			}
			$aryTemp = array();
		}
	}

	$decEndTime = time()+microtime();
	#echo "DONE. (".number_format($decEndTime - $decStartTimeTemp,1)." s.).\n";
	$decStartTimeTemp = $decEndTime;


	#echo chr(9)."ATTR...";

	$aryAttrTemp = array();
	foreach($aryAttrTry as $strAttr) {
		$aryAttrTemp[] = mysqli_real_escape_string($iConRLM,$strAttr);
	}
	

	#echo count($aryASID)."\n";

	if(count($aryAttrTemp) > 0) {
		$aryTemp = array();
		while ($intASID = array_pop($aryASID)) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$intASID);

			if(count($aryTemp) >= 1000 || count($aryASID) == 0) {
				$strSQL = "
					SELECT
					ATTR_SET_ID,
					ATTR_NM,
					ATTR_VAL_NUM,
					ATTR_VAL_CHAR
					FROM BODS_JDA_ADI.RESOURCE_ATTR
					#WHERE ATTR_NM IN ('".implode("','",$aryAttrTemp)."')
					WHERE ATTR_SET_ID IN (".implode(",",$aryTemp).");";
				$RSMain = ExecuteIQuery($strSQL,$iConRLM);
				while ($LineMain = mysqli_fetch_assoc($RSMain)) {
					$intASID = $LineMain["ATTR_SET_ID"];
					$strType = $LineMain["ATTR_NM"];

					switch (true) {
						case !is_null($LineMain["ATTR_VAL_NUM"]):
							$aryReturn["ATTR"][$intASID][$strType] = $LineMain["ATTR_VAL_NUM"];		
							break;
						case !is_null($LineMain["ATTR_VAL_CHAR"]):
							$aryReturn["ATTR"][$intASID][$strType] = $LineMain["ATTR_VAL_CHAR"];
							break;
						default:
							$aryReturn["ATTR"][$intASID][$strType] = null;
							break;
					}
				}
				$aryTemp = array();
			}
		}
	}


	$decEndTime = time()+microtime();
	#echo "DONE. (".number_format($decEndTime - $decStartTimeTemp,1)." s.).\n";


	$decEndTime = time()+microtime();
	#echo "DONE. (".number_format($decEndTime-$decStartTime,1)." s.).\n";

	return $aryReturn;
}


function TABLE_COPY_SUBSET($iConRLM,$aryTable,$strFrom,$strTo,$bolDebug=false) {
	echo __FUNCTION__." FOR ".$strFrom." => ".$strTo." (".implode(",",$aryTable).").\n";
	$decStartTime = time()+microtime();

	foreach($aryTable as $strTable) {
		echo chr(9).$strTable."...";

		$bolExist = false;

		$strSQL = "
			SELECT
			TABLE_NAME
			from information_schema.tables
			where table_schema = '".$strFrom."'
			and table_name = '".$strTable."';";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$bolExist = true;
		}

		if($bolExist === true) {
			$arySQL = Array();

			$arySQL[] = "
				DROP TABLE IF EXISTS
				".$strTo.".".$strTable;

			$arySQL[] = "
				CREATE TABLE
				".$strTo.".".$strTable."
				LIKE 
				".$strFrom.".".$strTable;

			$arySQL[] = "
				INSERT INTO
				".$strTo.".".$strTable."
				SELECT
				*
				FROM ".$strFrom.".".$strTable;

			ForEach($arySQL as $strSQL) {
				if($bolDebug === true) {
					echo $strSQL."\n";
				}
				ExecuteIQuery($strSQL,$iConRLM);
			}
			echo "DONE.\n";
		}else{
			echo "DOES NOT EXIST.\n";
		}
	}

	$decEndTime = time()+microtime();
	echo "DONE. (".number_format($decEndTime-$decStartTime,1)." s.).\n";
}


function TABLE_COPY($iConPNE3,$strFrom,$strTo,$bolDebug=false) {
	echo __FUNCTION__." (".$strFrom." => ".$strTo.")\n";

	#2022-10-05 RM added master tables
	$aryTable = array("PROD_RES_RELATION","RES_ALTERNATES","HARDWARE_SET","HARDWARE_ALTERNATES","HW_ID_MASTER","BILL_OF_RESOURCES","RS_ID_MASTER","RESOURCE_ATTR","ATTR_ID_MASTER","RESOURCE_CAPACITY","RES_EXCEPTION","RES_EXCEPTION_IDEN","RES_EXCEPTION_INFO");

	foreach($aryTable as $strTable) {
		echo chr(9).$strTable."...";

		$bolExist = false;

		$strSQL = "
			SELECT
			TABLE_NAME
			from information_schema.tables
			where table_schema = '".$strFrom."'
			and table_name = '".$strTable."';";
		$RSMain = ExecuteIQuery($strSQL,$iConPNE3);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$bolExist = true;
		}

		if($bolExist === true) {
			$arySQL = Array();

			$arySQL[] = "
				DROP TABLE IF EXISTS
				".$strTo.".".$strTable;

			$arySQL[] = "
				CREATE TABLE
				".$strTo.".".$strTable."
				LIKE 
				".$strFrom.".".$strTable;

			$arySQL[] = "
				INSERT INTO
				".$strTo.".".$strTable."
				SELECT
				*
				FROM ".$strFrom.".".$strTable;

			ForEach($arySQL as $strSQL) {
				if($bolDebug === true) {
					echo $strSQL."\n";
				}
				ExecuteIQuery($strSQL,$iConPNE3);
			}
			echo "DONE.\n";
		}else{
			echo "DOES NOT EXIST.\n";
		}
	}
	echo "DONE.\n";
}


function RES_ALTERNATES_DO($iConRLM,$intPRSID,$intASID,$intRSID,$intPC,$bolDebug=false) {
	#sort for deterministic SHA1 generation
	$arySQL = array();

	$aryTemp = array(
		"PRS_ID"=>$intPRSID,
		"RES_SET_ID"=>$intRSID,
		"ATTR_SET_ID"=>$intASID,
		"PRIO_CD"=>$intPC
	);

	foreach($aryTemp as $key => $value) {
		$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
	}

	#2023-09-21 RM all integers now 
	$arySQL[] = "(".implode(",",$aryTemp).")";

	if(count($arySQL) > 0) {
		$strSQL = "
			INSERT INTO
			BODS_JDA_ADI.RES_ALTERNATES
			(PRS_ID,RES_SET_ID,ATTR_SET_ID,PRIO_CD)
			VALUES
			".implode(",",$arySQL).";";
		ExecuteIQuery($strSQL,$iConRLM);
	}

	++$intPC;
	return $intPC;
}


function ATTR_SET_ID_GET($iConRLM,$aryAttr,$bolDebug=false) {
	#2023-10-11 RM rounding and such needs to happen here, not from where it was called.
	$aryNumeric = array(
		"TESTTIME"=>3,
		"INDEXTIME"=>3,
		"PTIME"=>0,
		"UTPI"=>0,
		"TTPI"=>3,
		"INDX"=>3,
		"TD_EFF"=>3,
		"SPRINT_UTPH"=>3,
		"UTPH"=>3,
		"STAGEUPH"=>3,
		"TESTTIME"=>3,
		"INDEXTIME"=>3,
		"TESTER_PROCEFF"=>3,
		"HANDLER_PROCEFF"=>3,
		"OEE"=>3,
		"YLD"=>3,
		"TEST_PERC"=>3,
		"REF_DOC_ID"=>0,
		"REF_STEP_ID"=>0,
		"REF_SETUP_ID"=>0,
		"BAKE_HOURS"=>0,
		"BAKE_STAB_HOURS"=>0,
		"BURNIN_HOURS_1"=>0,
		"IS_BLENDED"=>0,
		"TESTTIME_UPH3_OPC"=>3,
		"INDEXTIME_UPH3_OPC"=>3,
		"PTIME_UPH3_OPC"=>0,
		"GROSS_DPW"=>0,
		"WFR_IDX_PCT"=>3
	);

	$aryThis = $aryAttr;
	$aryThat = array();

	ksort($aryThis);
	foreach($aryThis as $strField => $strValue) {
		switch (true) {
			case is_null($strValue) || trim($strValue) == "":
				$aryThat[$strField] = null;
				break;
			case isset($aryNumeric[$strField]):
				$intDecimal = $aryNumeric[$strField];
				$aryThat[$strField] = round($strValue,$intDecimal);
				break;
			default:
				$aryThat[$strField] = $strValue;
				break;
		}
	}

	$aryAttr = $aryThat;
	$strSHA1 = sha1(serialize($aryAttr));

	$strSQL = "
		SELECT
		ATTR_ID
		FROM BODS_JDA_ADI.ATTR_ID_MASTER
		WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intASID = $LineMain["ATTR_ID"];
		return $intASID;
	}

	#still here, didnt exist yet
	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.ATTR_ID_MASTER
		(SHA1_VALUE)
		VALUES
		('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
	ExecuteIQuery($strSQL,$iConRLM);
	$intASID = mysqli_insert_id($iConRLM);


	#Print_R($aryAttr);
	#die();

	$arySQL = array();
	#$aryNumeric = array("UTPI","PTIME","TESTER_PROCEFF","HANDLER_PROCEFF","YLD","TESTTIME","INDEXTIME","LEAD_COUNT","TEST_PERC");
	foreach($aryAttr as $strAN => $strValue) {
		$aryTemp = array(
			"ATTR_SET_ID"=>$intASID,
			"ATTR_NM"=>$strAN,
			"ATTR_VAL_NUM"=>null,
			"ATTR_VAL_CHAR"=>null
		);

		
		switch (true) {
			case is_null($strValue):
				break;
			case isset($aryNumeric[$strAN]):
				$intDecimal = $aryNumeric[$strAN];
				$aryTemp["ATTR_VAL_NUM"] = round($strValue,$intDecimal);
				break;
			default:
				$aryTemp["ATTR_VAL_CHAR"] = $strValue;
				break;
		}

		foreach($aryTemp as $key => $value) {
			switch(true) {
				case is_null($value) || trim($value) == "":
					$aryTemp[$key] = "NULL";
					break;
				default:
					$aryTemp[$key] = "'".mysqli_real_escape_string($iConRLM,trim($value))."'";
					break;
			}
		}

		$arySQL[] = "(".implode(",",$aryTemp).")";
	}

	if(count($arySQL) > 0) {
		$strSQL = "
			INSERT INTO
			BODS_JDA_ADI.RESOURCE_ATTR
			(ATTR_SET_ID,ATTR_NM,ATTR_VAL_NUM,ATTR_VAL_CHAR)
			VALUES
			".implode(",",$arySQL).";";
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);
	}

	#Print_R($aryAttr);
	#die();

	return $intASID;
}


function RES_SET_ID_GET($iConRLM,$aryBOR,$bolDebug=false) {
	#$bolDebug = true;
	
	ksort($aryBOR);
	foreach(array_keys($aryBOR) as $strType) {
		ksort($aryBOR[$strType]);
	}

	$strSHA1 = sha1(serialize($aryBOR));

	if($bolDebug === true) {
		print_r($aryBOR);
		echo $strSHA1."\n";		
	}

	$strSQL = "
		SELECT
		RS_ID
		FROM BODS_JDA_ADI.RS_ID_MASTER
		WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}

	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intRSID = $LineMain["RS_ID"];
		return $intRSID;
	}


	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.RS_ID_MASTER
		(SHA1_VALUE)
		VALUES
		('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	ExecuteIQuery($strSQL,$iConRLM);
	$intRSID = mysqli_insert_id($iConRLM);

	#for now, REQUIRED_QTY = 1 for everything in bill_of_resources (hardware can have > 1)

	#Print_R($aryBOR);
	#die();

	$arySQL = array();
	foreach(array_keys($aryBOR) as $strType) {
		foreach($aryBOR[$strType] as $strName => $intRQ) {
			$aryTemp = array(
				"RES_SET_ID"=>$intRSID,
				"RES_TYPE"=>$strType,
				"RES_NM"=>$strName,
				"REQUIRED_QTY"=>$intRQ
			);

			foreach($aryTemp as $key => $value) {
				$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
			}

			$arySQL[] = "('".implode("','",$aryTemp)."')";
		}
	}


	if(count($arySQL) > 0) {
		$strSQL = "
			INSERT INTO
			BODS_JDA_ADI.BILL_OF_RESOURCES
			(RES_SET_ID,RES_TYPE,RES_NM,REQUIRED_QTY)
			VALUES
			".implode(",",$arySQL).";";
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);							
	}

	return $intRSID;
}


function HW_SET_ID_GET($iConRLM,$aryHWS,$bolDebug=false) {
	if(count($aryHWS) == 0) {
		$aryHWS["PERFBD"]["PRI"]["PERFBD_FAKE"] = 1;
	}

	#2023-09-21 RM cases where PRI does not exist but ALT does, move the first ALT item to PRI
	foreach($aryHWS as $strType => $aryPA) {
		if(!isset($aryPA["PRI"]) && isset($aryPA["ALT"])) {
			foreach($aryPA["ALT"] as $strName => $intRQ) {
				$aryPA["PRI"][$strName] = $intRQ;
				unset($aryPA["ALT"][$strName]);

				$aryHWS[$strType] = $aryPA;
				#move only the first
				break;
			}
		}
	}

	#deterministic sorting
	foreach($aryHWS as $strType => $aryPA) {
		foreach(array_keys($aryPA) as $strPA) {
		
			ksort($aryPA[$strPA]);
		}
		#for readability, do not sort PRI, ALT 
		#ksort($aryHWS[$strType]);
		$aryHWS[$strType] = $aryPA;
	}
	ksort($aryHWS);


	#remove any occurrence of hw_type (per hw_name) after the first
	$aryThis = $aryHWS;
	$aryThat = array();
	$aryDone = array();
	$aryPA = array("PRI","ALT");

	foreach(array_keys($aryThis) as $strType) {
		foreach($aryPA as $strPA) {
			if(isset($aryThis[$strType][$strPA])) {
				foreach($aryThis[$strType][$strPA] as $strName => $intRQ) {
					if(!isset($aryDone[$strName])) {
						$aryThat[$strType][$strPA][$strName] = $intRQ;
						#2023-12-19 RM not strictly necessary to record the type. but could be useful. plus isset is faster than in_array
						$aryDone[$strName][] = array("TYPE"=>$strType,"PRI_ALT"=>$strPA);
					}
				}
			}
		}
	}

	$aryHWS = $aryThat;


	#Print_R($aryHWS);
	#Print_R($aryPA);
	#die();
	if($bolDebug === true) {
		Print_R($aryHWS);
	}

	$strSHA1 = sha1(serialize($aryHWS));
	
	$strSQL = "
		SELECT
		HW_ID
		FROM BODS_JDA_ADI.HW_ID_MASTER
		WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intHWSID = $LineMain["HW_ID"];
	
		#2024-05-06 RM how is possible to have identical HW sets but different SHA1 values?  maybe type?
		#yes it was type
		/*
		if($intHWSID == 74795 || $intHWSID == 119503) {
			echo $intHWSID."\n";
			Print_R($aryHWS);
			echo serialize($aryHWS)."\n";
			echo $strSHA1."\n";
		}
		*/

		return $intHWSID;
	}


	#still here? then it doesnt exist yet
	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.HW_ID_MASTER
		(SHA1_VALUE)
		VALUES
		('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
	ExecuteIQuery($strSQL,$iConRLM);
	$intHWSID = mysqli_insert_id($iConRLM);

	$arySQL = array("HARDWARE_SET"=>array(),"HARDWARE_ALTERNATES"=>array());

	foreach(array_keys($aryHWS) as $strType) {
		foreach(array_keys($aryHWS[$strType]) as $strPA) {
			if($strPA == "PRI") {
				$strTable = "HARDWARE_SET";
			}else{
				$strTable = "HARDWARE_ALTERNATES";
			}

			foreach($aryHWS[$strType][$strPA] as $strName => $intRQ) {
				$aryTemp = array(
					"HW_SET_ID"=>$intHWSID,
					"HW_TYPE"=>$strType,
					"HW_NM"=>$strName,
					"REQUIRED_QTY"=>$intRQ
				);

				foreach($aryTemp as $key => $value) {
					$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
				}

				$arySQL[$strTable][] = "('".implode("','",$aryTemp)."')";
			}
		}
	}

	foreach(array_keys($arySQL) as $strTable) {
		if(count($arySQL[$strTable]) > 0) {
			$strSQL = "
				INSERT INTO
				BODS_JDA_ADI.".$strTable."
				(HW_SET_ID,HW_TYPE,HW_NM,REQUIRED_QTY)
				VALUES
				".implode(",",$arySQL[$strTable]).";";
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			ExecuteIQuery($strSQL,$iConRLM);
		}
	}

	return $intHWSID;
}


function ADPG_ROUTE_GET($iConRLM,$aryFilter) {
	echo __FUNCTION__."...";

	$arySQL = array("ISNULL(NULL)");
	foreach(array_keys($aryFilter) as $strField) {
		$aryTemp = array();
		foreach($aryFilter[$strField] as $strValue) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$strValue);
		}
		$arySQL[] = mysqli_real_escape_string($iConRLM,$strField)." IN ('".implode("','",$aryTemp)."')";
	}


	$strSQL = "
		SELECT
		ROUTE_ID,
		PARTNAME,
		PINSINSTNUM,
		CALLPRCDID
		FROM ADI_ESLDATA.ADI_ROUTES_PG
		WHERE ".implode(" AND ",$arySQL)."
		ORDER BY ROUTE_ID, PINSINSTNUM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRID = $LineMain["ROUTE_ID"];
		$decSeq = $LineMain["PINSINSTNUM"];

		if(!isset($aryReturn[$strRID])) {
			$aryReturn[$strRID] = array(
				"SEQUENCE"=>array(),
				"PARTNAME"=>$LineMain["PARTNAME"]
			);
		}

		$aryReturn[$strRID]["SEQUENCE"][$decSeq] = array("CALLPRCDID"=>$LineMain["CALLPRCDID"]);
	}

	#Print_R($aryReturn["AD3542R-DNCJ7P2.10"]);
	#die();
	echo "DONE.\n";
	return $aryReturn;
}


function ADGTP_ROUTE_GET_V2($iConRLM,$aryFilter) {
	echo __FUNCTION__."...";

	$arySQL = array("ISNULL(NULL)");
	foreach(array_keys($aryFilter) as $strField) {
		$aryTemp = array();
		foreach($aryFilter[$strField] as $strValue) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$strValue);
		}
		$arySQL[] = mysqli_real_escape_string($iConRLM,$strField)." IN ('".implode("','",$aryTemp)."')";
	}

	$strSQL = "
		SELECT
		ROUTE_ID,
		PARTNAME,
		PINSINSTNUM,
		CALLPRCDID,
		STAGE
		FROM ADI_ESLDATA.ADI_ROUTES_GT_PROBE
		WHERE ".implode(" AND ",$arySQL)."
		ORDER BY ROUTE_ID, PINSINSTNUM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryRaw = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRID = $LineMain["ROUTE_ID"];
		#2024-09-03 RM force to decimal (not string)
		$decSeq = $LineMain["PINSINSTNUM"];

		if(!isset($aryRaw[$strRID])) {
			$aryRaw[$strRID] = array(
				"SEQUENCE"=>array(),
				"PARTNAME"=>$LineMain["PARTNAME"]
			);
		}

		$aryRaw[$strRID]["SEQUENCE"][$decSeq] = array(
			"CALLPRCDID"=>$LineMain["CALLPRCDID"],
			"STAGE"=>$LineMain["STAGE"]
		);
	}

	#Print_R($aryRaw);
	#die();

	#2024-09-03 RM seeing duplicate steps (with different pinsinstnum) in adi_routes_gt_probe (see: AD7180-DQCJ9GT)
	#strip duplicates. only preserve the FIRST pinsinstnum per callprcdid
	$aryReturn = array();
	foreach($aryRaw as $strRID => $aryRawTemp) {
		$aryThis = $aryRawTemp["SEQUENCE"];
		$aryThat = array();

		$aryMap = array("NAME_SEQ"=>array());

		foreach($aryThis as $decSeq => $aryThisTemp) {
			$strName = $aryThisTemp["CALLPRCDID"];
			if(!isset($aryMap["NAME_SEQ"][$strName])) {
				$aryMap["NAME_SEQ"][$strName] = $decSeq;
			}
		}

		asort($aryMap["NAME_SEQ"]);

		foreach($aryMap["NAME_SEQ"] as $strName => $decSeq) {
			$aryThat[$decSeq] = array(
				"CALLPRCDID"=>$strName,
				"STAGE"=>$aryRaw[$strRID]["SEQUENCE"][$decSeq]["STAGE"]
			);
		}

		$aryRawTemp["SEQUENCE"] = $aryThat;

		$aryReturn[$strRID] = $aryRawTemp;
	}


	#Print_R($aryReturn);
	#die();
	
	echo "DONE.\n";
	return $aryReturn;
}


function ADI_RID_GET($iConRLM,$aryFilter) {
	echo __FUNCTION__."...";

	$arySQL = array("ISNULL(NULL)");
	foreach(array_keys($aryFilter) as $strField) {
		$aryTemp = array();
		foreach($aryFilter[$strField] as $strValue) {
			$aryTemp[] = mysqli_real_escape_string($iConRLM,$strValue);
		}
		$arySQL[] = mysqli_real_escape_string($iConRLM,$strField)." IN ('".implode("','",$aryTemp)."')";
	}


	$strSQL = "
		SELECT DISTINCT
		ROUTE_ID
		FROM ADI_ESLDATA.ADI_ROUTES
		WHERE ".implode(" AND ",$arySQL)."
		ORDER BY ROUTE_ID;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryReturn[] = $LineMain["ROUTE_ID"];
	}

	echo "DONE.\n";
	return $aryReturn;
}


function PRS_ID_GET($iConRLM,$aryPRR,$bolDebug=false) {
	ksort($aryPRR);
	if(isset($aryPRR["RTE_SEQ_NUM"]) && !is_null($aryPRR["RTE_SEQ_NUM"])) {
		$aryPRR["RTE_SEQ_NUM"] = round($aryPRR["RTE_SEQ_NUM"],3);
	}
	if(isset($aryPRR["TEMP_C"]) && !is_null($aryPRR["TEMP_C"])) {
		$aryPRR["TEMP_C"] = round($aryPRR["TEMP_C"],0);
	}

	if($bolDebug === true) {
		Print_R($aryPRR);
	}
	$strSHA1 = sha1(serialize($aryPRR));

	$strSQL = "
		SELECT
		PRS_ID
		FROM BODS_JDA_ADI.PRS_ID_MASTER
		WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$intPRSID = null;
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		return $LineMain["PRS_ID"];
	}

	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.PRS_ID_MASTER
		(SHA1_VALUE)
		VALUES
		('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	ExecuteIQuery($strSQL,$iConRLM);
	$intPRSID = mysqli_insert_id($iConRLM);


	$aryTry = array('SITE_NUM','MFG_PART_NUM','SAP_RTE_ID','RTE_SEQ_NUM','STEP_NM','QUAL_CD','RES_AREA','RTE_PRIO_CD','EFF_START_DT','EFF_END_DT','PRDN_FLG','TEMP_C');
	
	$aryTemp = array('PRS_ID'=>$intPRSID);
	foreach($aryTry as $strTry) {
		switch (true) {
			case !isset($aryPRR[$strTry]) || trim($aryPRR[$strTry]) == "":
				$aryTemp[$strTry] = "NULL";
				break;
			default:
				$aryTemp[$strTry] = "'".$aryPRR[$strTry]."'";
				break;
		}
	}

	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.PROD_RES_RELATION
		(PRS_ID,".implode(",",$aryTry).")
		VALUES
		(".implode(",",$aryTemp).");";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	ExecuteIQuery($strSQL,$iConRLM);

	return $intPRSID;
}


function PRR_DO($iConRLM,$aryPRRTemp,$bolDebug=false) {
	$aryTry = array('MFG_PART_NUM','SAP_RTE_ID','RTE_PRIO_CD','QUAL_CD','RTE_SEQ_NUM','STEP_NM','SITE_NUM','TEMP_C','RES_AREA');
	$aryTemp = array();
	foreach($aryTry as $strTry) {
		if(isset($aryPRRTemp[$strTry]) && trim($aryPRRTemp[$strTry]) != "") {
			$aryTemp[$strTry] = "'".mysqli_real_escape_string($iConRLM,trim($aryPRRTemp[$strTry]))."'";
		}else{
			$aryTemp[$strTry] = "NULL";
		}
	}

	$strSQL = "
		INSERT INTO
		BODS_JDA_ADI.PROD_RES_RELATION
		(".implode(",",$aryTry).")
		VALUES
		(".implode(",",$aryTemp).");";
	ExecuteIQuery($strSQL,$iConRLM);
	

	return mysqli_insert_id($iConRLM);
}


function ATOM_MASTER_ID_GET($iConRLM,$aryInput,$aryResult,$strDate,$bolDebug=false) {
	$strSHA1 = sha1(serialize($aryInput));

	$strSQL = "
		SELECT
		AM_ID
		FROM BODS_JDA_ADI.ATOM_MASTER
		WHERE SHA1_ROW_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$intAMID = null;
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intAMID = $LineMain["AM_ID"];
	}

	#MASTER, STEP are guaranteed to always match. CHANGE can be different if translation tables, logic, code line numbers, etc. change
	if(is_null($intAMID)) {
		$aryTry = array('PART_NAME','STEP_NAME','SITE_NUM','TESTER','HANDLER','CAT_12','CAT_38','CAT_41','PART_TYPE','PKG_TYPE','LEAD_COUNT','GENERIC');
		$aryTemp = array();

		foreach($aryTry as $strTry) {
			if(isset($aryInput[$strTry])) {
				$aryTemp[$strTry] = "'".mysqli_real_escape_string($iConRLM,$aryInput[$strTry])."'";
			}else{
				$aryTemp[$strTry] = "NULL";
			}
		}

		$aryTemp["SHA1_ROW_VALUE"] = "'".mysqli_real_escape_string($iConRLM,$strSHA1)."'";

		$strSQL = "
			INSERT INTO
			BODS_JDA_ADI.ATOM_MASTER
			(".implode(",",$aryTry).",SHA1_ROW_VALUE)
			VALUES
			(".implode(",",$aryTemp).");";
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);
		$intAMID = mysqli_insert_id($iConRLM);

		$aryThis = array();
		foreach($aryInput["ROUTE_STEPS"] as $strStep) {
			if(!is_null($strStep) && trim($strStep) != "") {
				$aryThis[] = $strStep;
			}
		}

		$strSHA1 = sha1(serialize($aryThis));
		$strSQL = "
			SELECT
			AS_MASTER_ID
			FROM BODS_JDA_ADI.ATOM_STEP_MASTER
			WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$intASMID = null;
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intASMID = $LineMain["AS_MASTER_ID"];
		}

		if(is_null($intASMID)) {
			$strSQL = "
				INSERT INTO
				BODS_JDA_ADI.ATOM_STEP_MASTER
				(SHA1_VALUE)
				VALUES
				('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			ExecuteIQuery($strSQL,$iConRLM);
			$intASMID = mysqli_insert_id($iConRLM);

			$arySQLTemp = array();

			foreach($aryThis as $strStep) {
				$aryTemp = array(
					"AS_MASTER_ID"=>$intASMID,
					"STEP_NAME"=>$strStep
				);

				foreach($aryTemp as $key => $value) {
					$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
				}

				$arySQLTemp[] = "('".implode("','",$aryTemp)."')";
			}

			if(count($arySQLTemp) > 0) {
				$strSQL = "
					INSERT INTO
					BODS_JDA_ADI.ATOM_STEP
					(AS_MASTER_ID,STEP_NAME)
					VALUES
					".implode(",",$arySQLTemp).";";
				if($bolDebug === true) {
					echo $strSQL."\n";
				}
				ExecuteIQuery($strSQL,$iConRLM);
			}
		}


		$strSQL = "
			UPDATE
			BODS_JDA_ADI.ATOM_MASTER
			SET AS_MASTER_ID = ".mysqli_real_escape_string($iConRLM,$intASMID)."
			WHERE AM_ID = ".mysqli_real_escape_string($iConRLM,$intAMID).";";
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);
	}


	$strSHA1 = sha1(serialize($aryResult));

	$strSQL = "
		SELECT
		AC_MASTER_ID
		FROM BODS_JDA_ADI.ATOM_CHANGE_MASTER
		WHERE SHA1_VALUE = '".mysqli_real_escape_string($iConRLM,$strSHA1)."';";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$intACMID = null;
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intACMID = $LineMain["AC_MASTER_ID"];
	}

	$aryTry = array("TESTER","HANDLER");

	if(is_null($intACMID)) {
		$strSQL = "
			INSERT INTO
			BODS_JDA_ADI.ATOM_CHANGE_MASTER
			(SHA1_VALUE)
			VALUES
			('".mysqli_real_escape_string($iConRLM,$strSHA1)."');";
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);
		$intACMID = mysqli_insert_id($iConRLM);

		$arySQLTemp = array();

		foreach($aryTry as $strTry) {
			foreach($aryResult["ATOM"][$strTry] as $intLine => $aryATOMTemp) {
				foreach($aryATOMTemp as $strFrom => $strTo) {
					$aryTemp = array(
						"AC_MASTER_ID"=>(int) $intACMID,
						"RES_TYPE"=>$strTry,
						"RES_NM_FROM"=>$strFrom,
						"RES_NM_TO"=>$strTo,
						"LINE_NUMBER"=>(int) $intLine
					);
					foreach($aryTemp as $key => $value) {
						$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
					}

					$arySQLTemp[] = "('".implode("','",$aryTemp)."')";					
				}
			}
		}

		if(count($arySQLTemp) > 0) {
			$strSQL = "
				INSERT INTO
				BODS_JDA_ADI.ATOM_CHANGE
				(AC_MASTER_ID,RES_TYPE,RES_NM_FROM,RES_NM_TO,LINE_NUMBER)
				VALUES
				".implode(",",$arySQLTemp).";";
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			ExecuteIQuery($strSQL,$iConRLM);
		}
	}

	$strSQL = "
		SELECT
		ATOM_CHANGE.RES_TYPE,
		ATOM_CHANGE.RES_NM_TO
		FROM BODS_JDA_ADI.ATOM_CHANGE
		INNER JOIN 
		(
			SELECT
			MAX(AC_ID) AC_ID
			FROM BODS_JDA_ADI.ATOM_CHANGE
			WHERE AC_MASTER_ID = ".mysqli_real_escape_string($iConRLM,$intACMID)."
			GROUP BY RES_TYPE
		) ATOM_CHANGE_MAX ON ATOM_CHANGE.AC_ID = ATOM_CHANGE_MAX.AC_ID;";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryThis = array(
		"AC_MASTER_ID"=>$intACMID,
		"TESTER_NEW"=>null,
		"HANDLER_NEW"=>null,
		"LAST_CHECKED_DT"=>$strDate
	);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRT = $LineMain["RES_TYPE"];
		$aryThis[$strRT."_NEW"] = $LineMain["RES_NM_TO"];
	}


	#2023-09-21 RM check before writing to make sure its necessary. will always write the first time it's checked per day
	$strSQL = "
		SELECT
		AC_MASTER_ID,
		TESTER_NEW,
		HANDLER_NEW,
		LAST_CHECKED_DT
		FROM BODS_JDA_ADI.ATOM_MASTER
		WHERE AM_ID = ".mysqli_real_escape_string($iConRLM,$intAMID).";";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryThat = $LineMain;
	}

	if($bolDebug === true) {
		Print_R($aryThis);
		Print_R($aryThat);
	}

	$aryTry = array("AC_MASTER_ID","TESTER_NEW","HANDLER_NEW","LAST_CHECKED_DT");
	$arySQLTemp = array();
	foreach($aryTry as $strTry) {
		switch (true) {
			case is_null($aryThis[$strTry]) && !is_null($aryThat[$strTry]):
				$arySQLTemp[] = $strTry." = NULL";
				break;
			case is_null($aryThis[$strTry]) && is_null($aryThat[$strTry]):
				break;
			default:
				$arySQLTemp[] = $strTry." = '".mysqli_real_escape_string($iConRLM,$aryThis[$strTry])."'";
				break;
		}
	}

	if(count($arySQLTemp) > 0) {
		$strSQL = "
			UPDATE
			BODS_JDA_ADI.ATOM_MASTER
			SET ".implode(",",$arySQLTemp)."
			WHERE AM_ID = ".mysqli_real_escape_string($iConRLM,$intAMID).";";
		if($bolDebug === true) {
			echo $strSQL."\n";
		}
		ExecuteIQuery($strSQL,$iConRLM);
	}

	return $intAMID;
}


function RES_CAP_GET_PG($iConRLM,$aryHWTypeAllowed,$aryMR,$bolDebug=false) {
	echo __FUNCTION__."...";
	$aryReturn = array();



	#2023-07-06 RM from KB 
	#2024-03-04 RM disabled . request from KS
	$strSQL = "
		SELECT DISTINCT
		RES_NM
		FROM EQUIP.RESOURCE_CAPACITY
		WHERE (RES_NM LIKE 'TS8%_LT%' OR RES_NM LIKE '%LTX_ADSC%') 
		AND RES_NM NOT LIKE 'TS88_STD_SL_LT%' 
		ORDER BY RES_NM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryHalf = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		#$aryHalf[] = $LineMain["RES_NM"];
	}

	#2023-05-23 RM return structure now SITE_NUM, RES_NM => array(RES_TYPE,RES_CNT,EFF_RES_CNT)
	#forces removal of any ambiguity caused by bad data

	#2024-02-26 RM now use equip.resource_capacity. 
	#but tester_proceff and handler_proceff are not available there, so keep pulling those from original tables
	#2024-03-05 RM SO FUCKING DUMB. BAND-AID SHIT AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN
	#2024-03-05 Rm fixing this upstream. this is bullshit
	$strSQL = "
		SELECT
		RES_TYPE,
		RES_NM,
		SITE_NUM,
		RES_AREA,
		EFF_START_DT,
		EFF_END_DT,
		SUM(RES_CNT) RES_CNT,
		SUM(EFF_RES_CNT) EFF_RES_CNT
		FROM EQUIP.RESOURCE_CAPACITY
		WHERE RES_TYPE IN ('TESTER','HANDLER')
		GROUP BY RES_TYPE, RES_NM, SITE_NUM, RES_AREA, EFF_START_DT, EFF_END_DT
		ORDER BY RES_TYPE, RES_NM, SITE_NUM, RES_AREA, IFNULL(EFF_START_DT,'"._DATE_MIN."'), IFNULL(EFF_END_DT,'"._DATE_MAX."');";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRT = $LineMain["RES_TYPE"];
		$strRN = $LineMain["RES_NM"];
		$strSN = $LineMain["SITE_NUM"];
		$strRA = $LineMain["RES_AREA"];

		#2023-07-06 RM from KB
		$aryTemp = array(
			"EFF_START_DT"=>$LineMain["EFF_START_DT"],
			"EFF_END_DT"=>$LineMain["EFF_END_DT"],
			"RES_CNT"=>$LineMain["RES_CNT"],
			"EFF_RES_CNT"=>$LineMain["EFF_RES_CNT"],
			"PROCEFF"=>1,
		);

		$aryReturn[$strRT][$strRN][$strSN][$strRA][] = $aryTemp;
	}

	#Print_R($aryReturn["HANDLER"]["AETRIUM_RS_HV_HPCS"]);
	#die();


	$arySQL = array();

	$arySQL[] = "
		SELECT
		PLANTSITE,
		'TESTER' RES_TYPE,
		STNFAM2 RES_NM,
		MIN(
			CASE TRUE
				WHEN ISNULL(PROCEFF) THEN 1
				WHEN PROCEFF = 0 THEN 1
				WHEN PROCEFF > 1 THEN 1
				ELSE PROCEFF
			END
		) PROCEFF
		FROM ADI_ESLDATA.ADI_TBL_TESTERS_INVENTORY_GENERATE
		GROUP BY PLANTSITE, RES_NM;";

	$arySQL[] = "
		SELECT
		PLANTSITE,
		'HANDLER' RES_TYPE,
		GENRESFAMSORT RES_NM,
		MIN(
			CASE TRUE
				WHEN ISNULL(PROCEFF) THEN 1
				WHEN PROCEFF = 0 THEN 1
				WHEN PROCEFF > 1 THEN 1
				ELSE PROCEFF
			END
		) PROCEFF
		FROM ADI_ESLDATA.ADI_TBL_HANDLERS_INVENTORY_GENERATE
		GROUP BY PLANTSITE, GENRESFAMSORT;";

	foreach($arySQL as $strSQL) {
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSN = $LineMain["PLANTSITE"];
			$strRT = $LineMain["RES_TYPE"];
			$strRN = $LineMain["RES_NM"];

			if(isset($aryReturn[$strRT][$strRN][$strSN])) {
				foreach(array_keys($aryReturn[$strRT][$strRN][$strSN]) as $strRA) {
					foreach($aryReturn[$strRT][$strRN][$strSN][$strRA] as $intCtr => $aryReturnTemp) {
						$aryReturnTemp["PROCEFF"] = $LineMain["PROCEFF"];
						$aryReturn[$strRT][$strRN][$strSN][$strRA][$intCtr] = $aryReturnTemp;
					}
				}
			}
		}
	}

	#2023-05-23 RM need to restructure this as we are now relying solely upon hardware_name -> eqpt_type from adi_tbl_hardware_inventory_autosched to override data coming from adi_route_param
	#as of today there are 5 hardware_names with different eqpt_types within the list of types that we want, with status in ('avail','spare') and current_location not in (filtered location list) so we need to pick a winner
	#2023-12-11 RM now there are 8
	
	#$bolDebug = true;
	
	#2023-07-05 RM changed structure of mapper and final array
	#determine name -> type relationship first, which is global. then add site_num

	#2024-02-29 RM added TGLTXNPW at KB request

	#2024-03-21 RM fill with ERC 1.0 of every hardware named in params
	$arySQL = array();

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY,
		TEST.TEST_002_TEMPORARY,
		TEST.TEST_003_TEMPORARY;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_001_TEMPORARY
		(
			INDEX IDX1 (ROUTE_ID)
		)
		SELECT DISTINCT
		ROUTE_ID,
		PARMVAL PLANTSITE
		FROM ADI_ESLDATA.ADI_ROUTE_PARAM_PG
		WHERE PARMNAME = '\$PROMIS-SITE';";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_002_TEMPORARY
		(
			INDEX IDX2 (ROUTE_ID)
		)
		SELECT DISTINCT
		ROUTE_ID,
		PARMVAL HARDWARE_TYPE_NAME
		FROM ADI_ESLDATA.ADI_ROUTE_PARAM_PG
		WHERE PARMNAME REGEXP '^[$]P[0-9]+S[0-9]+_HW[0-9]+$'
		AND PARMVAL NOT REGEXP '[-]+$';";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_003_TEMPORARY
		(
			INDEX IDX1 (ROUTE_ID)
		)
		SELECT
		T002.ROUTE_ID,
		T002.HARDWARE_TYPE_NAME,
		IFNULL(T001.PLANTSITE,'ADPG') PLANTSITE
		FROM TEST.TEST_002_TEMPORARY T002
		LEFT JOIN TEST.TEST_001_TEMPORARY T001 ON T002.ROUTE_ID = T001.ROUTE_ID;";

	foreach($arySQL as $strSQL) {
		ExecuteIQuery($strSQL,$iConRLM);
	}

	$strSQL = "
		SELECT
		HARDWARE_TYPE_NAME,
		PLANTSITE,
		COUNT(DISTINCT ROUTE_ID) ROUTE_ID_COUNT
		FROM TEST.TEST_003_TEMPORARY
		GROUP BY HARDWARE_TYPE_NAME, PLANTSITE;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMap["NAME_TYPE"] = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryTemp = explode(":",$LineMain["HARDWARE_TYPE_NAME"]);
		
		if(count($aryTemp) == 2) {
			$strRT = trim($aryTemp[0]);
			$strRN = trim($aryTemp[1]);
			$strSN = $LineMain["PLANTSITE"];


			if(!isset($aryMap["NAME_TYPE"][$strRN][$strRT])) {
				$aryMap["NAME_TYPE"][$strRN][$strRT] = 0;
			}
			$aryMap["NAME_TYPE"][$strRN][$strRT] += $LineMain["ROUTE_ID_COUNT"];

			if(!isset($aryMap["NAME_SN"][$strRN][$strSN])) {
				$aryMap["NAME_SN"][$strRN][$strSN] = 0;
			}
			$aryMap["NAME_SN"][$strRN][$strSN] += $LineMain["ROUTE_ID_COUNT"];
		}
	}

	$aryMain = array();
	foreach(array_keys($aryMap["NAME_TYPE"]) as $strRN) {
		$intRC = 0;
		foreach($aryMap["NAME_TYPE"][$strRN] as $strRT => $intCount) {
			$intRC += $intCount;
		}

		arsort($aryMap["NAME_TYPE"][$strRN]);
		foreach(array_keys($aryMap["NAME_TYPE"][$strRN]) as $strRT) {
			$aryMain[$strRN] = array(
				"RES_TYPE"=>$strRT,
				"SITE_NUM"=>array()
			);
			break;
		}

		foreach($aryMap["NAME_SN"][$strRN] as $strSN => $intCount) {
			#avail as CL, status
			$aryMain[$strRN]["SITE_NUM"][$strSN]["AVAIL"]["AVAIL"] = $intCount;
		}
	}
	#2023-10-20 RM Re: Hardware Repair LTC for pushout modified incoming filters, capturing both current_location and status

	#2023-12-15 RM added detection of complex category. data is currently inconsistent so just look for existence
/*
	$strSQL = "
		SELECT DISTINCT
		UCASE(HARDWARE_NAME) HW_NAME
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		AND ".implode(" AND ",$arySQL["FILTER2"])."
		AND CATEGORY = 'COMPLEX';";
	#echo $strSQL."\n";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMap["NAME_CAT"] = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$aryMap["NAME_CAT"][$strName] = "COMPLEX";
	}

	#Print_R($aryMap["NAME_CAT"]["GTL-50195"]);
	#die();
*//*

	
	#2023-10-26 RM specific enablement of site_num, res_type, res_nm combinations
	$aryTH = array("TESTER","HANDLER");
	#$bolDebug = true;
	foreach($aryMR as $intMRID => $aryMRTemp) {
		$strType = $aryMRTemp["RES_TYPE"];
		$strName = $aryMRTemp["RES_NM"];

		if(!in_array($strType,$aryTH)) {
			#echo $strType." / ".$strName."\n";

			$aryTemp = array();
			switch (true) {
				case !isset($aryMRTemp["SITE_NUM"]):
					break;
				case $aryMRTemp["SITE_NUM"] == "ADGT":
					$aryTemp[] = "LOCATION = 'GTHTML'";
					break;
				case $aryMRTemp["SITE_NUM"] == "ADPI":
					$aryTemp[] = "LOCATION = 'PIHTML'";
					break;
			}
			
			$aryTemp[] = "EQPT_TYPE = '".mysqli_real_escape_string($iConRLM,$strType)."'";
			$aryTemp[] = "HARDWARE_NAME = '".mysqli_real_escape_string($iConRLM,$strName)."'";

			$strSQL = "
				SELECT
				UCASE(
					CASE LOCATION
						WHEN 'GTHTML' THEN 'ADGT'
						WHEN 'PIHTML' THEN 'ADPI'
						WHEN 'LTXHTML' THEN 'LTX'
						ELSE 'UNKNOWN'
					END
				) SITE_NUM,
				UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
				UCASE(STATUS) STATUS,
				COUNT(ITEM_CODE) HW_COUNT
				FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
				WHERE ".implode(" AND ",$arySQL["FILTER"])."
				AND ".implode(" AND ",$aryTemp)."
				GROUP BY SITE_NUM, CURRENT_LOCATION, STATUS
				ORDER BY SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strSN = $LineMain["SITE_NUM"];
				$strCL = $LineMain["CURRENT_LOCATION"];
				$strStatus = $LineMain["STATUS"];

				if(!isset($aryMain[$strName])) {
					$aryMain[$strName] = array(
						"RES_TYPE"=>$strType,
						"SITE_NUM"=>array()
					);
				}

				$aryMain[$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $LineMain["HW_COUNT"];
			}


			#2023-10-27 RM any inv set here as part of override is not important. the existence of the hw_name is what makes the difference.  
			#actual quantity calculation happens in res_cap_do

			if(!isset($aryMain[$strName])) {
				#did not get any kind of inventory, so this is either a dummy or the person who entered it is a dummy
				#either way, create it now
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					"SITE_NUM"=>array()
				);				

				if(isset($aryMRTemp["SITE_NUM"])) {
					$strSN = $aryMRTemp["SITE_NUM"];
					$aryMain[$strName]["SITE_NUM"][$strSN] = array(
						"OVERRIDE"=>array(
							"OVERRIDE"=>$aryMRTemp["INV"]
						)
					);
				}else{
					foreach($arySN as $strSN) {
						$aryMain[$strName]["SITE_NUM"][$strSN] = array(
							"OVERRIDE"=>array(
								"OVERRIDE"=>$aryMRTemp["INV"]
							)
						);
					}
				}
			}
			#Print_R($aryMain[$strName]);
		}
	}
*/
	#Print_R($aryMain["DUMMY_1"]);
	#die();

	foreach(array_keys($aryMain) as $strName) {
		$aryReturn["HARDWARE"][$strName] = $aryMain[$strName];	
	}

	#Print_R(array_keys($aryReturn["HARDWARE"]));
	#Print_R($aryReturn["HARDWARE"]["LTC-00280"]);
	#die();

	echo "DONE.\n";
	return $aryReturn;
}


function RES_CAP_GET_V2($iConRLM,$aryHWTypeAllowed,$aryMR,$bolDebug=false) {
	echo __FUNCTION__."...";
	$aryReturn = array();



	#2023-07-06 RM from KB 
	#2024-03-04 RM disabled . request from KS
	$strSQL = "
		SELECT DISTINCT
		RES_NM
		FROM EQUIP.RESOURCE_CAPACITY
		WHERE (RES_NM LIKE 'TS8%_LT%' OR RES_NM LIKE '%LTX_ADSC%') 
		AND RES_NM NOT LIKE 'TS88_STD_SL_LT%' 
		ORDER BY RES_NM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryHalf = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		#$aryHalf[] = $LineMain["RES_NM"];
	}

	#2023-05-23 RM return structure now SITE_NUM, RES_NM => array(RES_TYPE,RES_CNT,EFF_RES_CNT)
	#forces removal of any ambiguity caused by bad data

	#2024-02-26 RM now use equip.resource_capacity. 
	#but tester_proceff and handler_proceff are not available there, so keep pulling those from original tables
	#2024-03-05 RM SO FUCKING DUMB. BAND-AID SHIT AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN AND AGAIN
	#2024-03-05 Rm fixing this upstream. this is bullshit
	$strSQL = "
		SELECT
		RES_TYPE,
		RES_NM,
		SITE_NUM,
		RES_AREA,
		EFF_START_DT,
		EFF_END_DT,
		SUM(RES_CNT) RES_CNT,
		SUM(EFF_RES_CNT) EFF_RES_CNT
		FROM EQUIP.RESOURCE_CAPACITY
		WHERE RES_TYPE IN ('TESTER','HANDLER')
		GROUP BY RES_TYPE, RES_NM, SITE_NUM, RES_AREA, EFF_START_DT, EFF_END_DT
		ORDER BY RES_TYPE, RES_NM, SITE_NUM, RES_AREA, IFNULL(EFF_START_DT,'"._DATE_MIN."'), IFNULL(EFF_END_DT,'"._DATE_MAX."');";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRT = $LineMain["RES_TYPE"];
		$strRN = $LineMain["RES_NM"];
		$strSN = $LineMain["SITE_NUM"];
		$strRA = $LineMain["RES_AREA"];

		#2023-07-06 RM from KB
		$aryTemp = array(
			"EFF_START_DT"=>$LineMain["EFF_START_DT"],
			"EFF_END_DT"=>$LineMain["EFF_END_DT"],
			"RES_CNT"=>$LineMain["RES_CNT"],
			"EFF_RES_CNT"=>$LineMain["EFF_RES_CNT"],
			"PROCEFF"=>1,
		);

		$aryReturn[$strRT][$strRN][$strSN][$strRA][] = $aryTemp;
	}

	#Print_R($aryReturn["HANDLER"]["AETRIUM_RS_HV_HPCS"]);
	#die();


	$arySQL = array();

	$arySQL[] = "
		SELECT
		PLANTSITE,
		'TESTER' RES_TYPE,
		STNFAM2 RES_NM,
		MIN(
			CASE TRUE
				WHEN ISNULL(PROCEFF) THEN 1
				WHEN PROCEFF = 0 THEN 1
				WHEN PROCEFF > 1 THEN 1
				ELSE PROCEFF
			END
		) PROCEFF
		FROM ADI_ESLDATA.ADI_TBL_TESTERS_INVENTORY_GENERATE
		GROUP BY PLANTSITE, RES_NM;";

	$arySQL[] = "
		SELECT
		PLANTSITE,
		'HANDLER' RES_TYPE,
		GENRESFAMSORT RES_NM,
		MIN(
			CASE TRUE
				WHEN ISNULL(PROCEFF) THEN 1
				WHEN PROCEFF = 0 THEN 1
				WHEN PROCEFF > 1 THEN 1
				ELSE PROCEFF
			END
		) PROCEFF
		FROM ADI_ESLDATA.ADI_TBL_HANDLERS_INVENTORY_GENERATE
		GROUP BY PLANTSITE, GENRESFAMSORT;";

	foreach($arySQL as $strSQL) {
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$strSN = $LineMain["PLANTSITE"];
			$strRT = $LineMain["RES_TYPE"];
			$strRN = $LineMain["RES_NM"];

			if(isset($aryReturn[$strRT][$strRN][$strSN])) {
				foreach(array_keys($aryReturn[$strRT][$strRN][$strSN]) as $strRA) {
					foreach($aryReturn[$strRT][$strRN][$strSN][$strRA] as $intCtr => $aryReturnTemp) {
						$aryReturnTemp["PROCEFF"] = $LineMain["PROCEFF"];
						$aryReturn[$strRT][$strRN][$strSN][$strRA][$intCtr] = $aryReturnTemp;
					}
				}
			}
		}
	}

	#2023-05-23 RM need to restructure this as we are now relying solely upon hardware_name -> eqpt_type from adi_tbl_hardware_inventory_autosched to override data coming from adi_route_param
	#as of today there are 5 hardware_names with different eqpt_types within the list of types that we want, with status in ('avail','spare') and current_location not in (filtered location list) so we need to pick a winner
	#2023-12-11 RM now there are 8
	
	#$bolDebug = true;
	
	#2023-07-05 RM changed structure of mapper and final array
	#determine name -> type relationship first, which is global. then add site_num

	#2024-02-29 RM added TGLTXNPW at KB request

	$arySQL = array(
		"FILTER"=>array(),
		"FILTER2"=>array()
	);

	$aryTemp = array('ENGG','OBSOLETE','PR','QUAL','SCRAP','SPARE','XFER');
	$arySQL["FILTER"][] = "STATUS NOT IN ('".implode("','",$aryTemp)."')";
	$aryTemp = array('PICALB','PIENGG','PIPM','PIQUAL','PIREPR','PIXFER','TGBIPM','TGQUAL','QUARANTINE','TGBIRPR','TGCALB','TGDEBUG','TGFAREL','TGPM','TGPR','TGREWORK','TGSCRAP','TGUNAVAIL','TGXFER','PREXFER','TGPREXFER','FLOATING','TGFLOATING','TGPCSCRAP','TGPCSHIP','TGPCXFER','TGVERF','TGMDOWN','TGNDHWDEF','TGLTXNPW');
	$arySQL["FILTER"][] = "NOT (STATUS = 'AVAIL' AND CURRENT_LOCATION IN ('".implode("','",$aryTemp)."'))";


	$aryTemp = array();
	foreach($aryHWTypeAllowed as $strHWType) {
		$aryTemp[] = mysqli_real_escape_string($iConRLM,$strHWType);
	}
	
	if(count($aryTemp) > 0) {
		$arySQL["FILTER2"][] = "EQPT_TYPE IN ('".implode("','",$aryTemp)."')";
	}
	

	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(EQPT_TYPE) HW_TYPE,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		GROUP BY HW_NAME, HW_TYPE
		ORDER BY HW_NAME, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];

		#2023-10-20 RM applying filters up-front for HW_TYPE assignment. since the winner is picked going forward
		#per NAME, TYPE with the most COUNT wins
		if(!isset($aryMap["NAME_TYPE"][$strName])) {
			$aryMap["NAME_TYPE"][$strName] = $LineMain["HW_TYPE"];
		}
	}
	

	#2024-01-02 RM when a name exists but filters rule it out, making 0 quantity, add into this array so we have a record at least. otherwise it gets dropped, which can unconstrain stuff
	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(EQPT_TYPE) HW_TYPE,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER2"])."
		GROUP BY HW_NAME, HW_TYPE
		ORDER BY HW_NAME, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];

		if(!isset($aryMap["NAME_TYPE"][$strName])) {
			$aryMap["NAME_TYPE"][$strName] = $LineMain["HW_TYPE"];
		}
	}



	#2023-10-20 RM Re: Hardware Repair LTC for pushout modified incoming filters, capturing both current_location and status
	#2023-12-15 RM added detection of complex category. data is currently inconsistent so just look for existence
	#2024-05-30 RM kill repair release, reservation of repair for spare. nothing changes in this function

	$strSQL = "
		SELECT DISTINCT
		UCASE(HARDWARE_NAME) HW_NAME
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		AND ".implode(" AND ",$arySQL["FILTER2"])."
		AND CATEGORY = 'COMPLEX';";
	#echo $strSQL."\n";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMap["NAME_CAT"] = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$aryMap["NAME_CAT"][$strName] = "COMPLEX";
	}

	#Print_R($aryMap["NAME_CAT"]["GTL-50195"]);
	#die();

	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(
			CASE LOCATION
				WHEN 'GTHTML' THEN 'ADGT'
				WHEN 'PIHTML' THEN 'ADPI'
				WHEN 'LTXHTML' THEN 'LTX'
				ELSE 'UNKNOWN'
			END
		) SITE_NUM,
		UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
		UCASE(STATUS) STATUS,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER"])."
		AND ".implode(" AND ",$arySQL["FILTER2"])."
		GROUP BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS
		ORDER BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$arySN = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$strSN = $LineMain["SITE_NUM"];
		$strCL = $LineMain["CURRENT_LOCATION"];
		$strStatus = $LineMain["STATUS"];

		if(isset($aryMap["NAME_TYPE"][$strName])) {
			$strType = $aryMap["NAME_TYPE"][$strName];

			if(!isset($aryMain[$strName])) {
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					#2023-12-14 RM now capturing category. only complex has special treatment
					"CATEGORY"=>null,
					"SITE_NUM"=>array()
				);
			}

			if(isset($aryMap["NAME_CAT"][$strName])) {
				$aryMain[$strName]["CATEGORY"] = $aryMap["NAME_CAT"][$strName];
			}

			$aryMain[$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $LineMain["HW_COUNT"];

			$arySN[] = $strSN;
		}
	}
	$arySN = array_unique($arySN);


	#2024-01-02 RM also needed here, to establish existence so it gets recognized as hardware
	#2024-01-18 RM added exlcusion of scrap 
	$strSQL = "
		SELECT
		UCASE(HARDWARE_NAME) HW_NAME,
		UCASE(
			CASE LOCATION
				WHEN 'GTHTML' THEN 'ADGT'
				WHEN 'PIHTML' THEN 'ADPI'
				WHEN 'LTXHTML' THEN 'LTX'
				ELSE 'UNKNOWN'
			END
		) SITE_NUM,
		UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
		UCASE(STATUS) STATUS,
		COUNT(ITEM_CODE) HW_COUNT
		FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
		WHERE ".implode(" AND ",$arySQL["FILTER2"])."
		AND STATUS NOT IN ('SCRAP')
		GROUP BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS
		ORDER BY HW_NAME, SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strName = $LineMain["HW_NAME"];
		$strSN = $LineMain["SITE_NUM"];
		$strCL = $LineMain["CURRENT_LOCATION"];
		$strStatus = $LineMain["STATUS"];

		if(isset($aryMap["NAME_TYPE"][$strName])) {
			$strType = $aryMap["NAME_TYPE"][$strName];

			if(!isset($aryMain[$strName])) {
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					#2023-12-14 RM now capturing category. only complex has special treatment
					"CATEGORY"=>null,
					"SITE_NUM"=>array()
				);

				if(isset($aryMap["NAME_CAT"][$strName])) {
					$aryMain[$strName]["CATEGORY"] = $aryMap["NAME_CAT"][$strName];
				}

				#ONLY write initial entry. do not write any kind of quantity
			}
		}
	}

	
	#2023-10-26 RM specific enablement of site_num, res_type, res_nm combinations
	$aryTH = array("TESTER","HANDLER");
	#$bolDebug = true;
	foreach($aryMR as $intMRID => $aryMRTemp) {
		$strType = $aryMRTemp["RES_TYPE"];
		$strName = $aryMRTemp["RES_NM"];

		if(!in_array($strType,$aryTH)) {
			#echo $strType." / ".$strName."\n";

			$aryTemp = array();
			switch (true) {
				case !isset($aryMRTemp["SITE_NUM"]):
					break;
				case $aryMRTemp["SITE_NUM"] == "ADGT":
					$aryTemp[] = "LOCATION = 'GTHTML'";
					break;
				case $aryMRTemp["SITE_NUM"] == "ADPI":
					$aryTemp[] = "LOCATION = 'PIHTML'";
					break;
			}
			
			$aryTemp[] = "EQPT_TYPE = '".mysqli_real_escape_string($iConRLM,$strType)."'";
			$aryTemp[] = "HARDWARE_NAME = '".mysqli_real_escape_string($iConRLM,$strName)."'";

			$strSQL = "
				SELECT
				UCASE(
					CASE LOCATION
						WHEN 'GTHTML' THEN 'ADGT'
						WHEN 'PIHTML' THEN 'ADPI'
						WHEN 'LTXHTML' THEN 'LTX'
						ELSE 'UNKNOWN'
					END
				) SITE_NUM,
				UCASE(CURRENT_LOCATION) CURRENT_LOCATION,
				UCASE(STATUS) STATUS,
				COUNT(ITEM_CODE) HW_COUNT
				FROM ADI_ESLDATA.ADI_TBL_HARDWARE_INVENTORY_AUTOSCHED
				WHERE ".implode(" AND ",$arySQL["FILTER"])."
				AND ".implode(" AND ",$aryTemp)."
				GROUP BY SITE_NUM, CURRENT_LOCATION, STATUS
				ORDER BY SITE_NUM, CURRENT_LOCATION, STATUS, HW_COUNT DESC";
			if($bolDebug === true) {
				echo $strSQL."\n";
			}
			$RSMain = ExecuteIQuery($strSQL,$iConRLM);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$strSN = $LineMain["SITE_NUM"];
				$strCL = $LineMain["CURRENT_LOCATION"];
				$strStatus = $LineMain["STATUS"];

				if(!isset($aryMain[$strName])) {
					$aryMain[$strName] = array(
						"RES_TYPE"=>$strType,
						"SITE_NUM"=>array()
					);
				}

				$aryMain[$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $LineMain["HW_COUNT"];
			}


			#2023-10-27 RM any inv set here as part of override is not important. the existence of the hw_name is what makes the difference.  
			#actual quantity calculation happens in res_cap_do

			if(!isset($aryMain[$strName])) {
				#did not get any kind of inventory, so this is either a dummy or the person who entered it is a dummy
				#either way, create it now
				$aryMain[$strName] = array(
					"RES_TYPE"=>$strType,
					"SITE_NUM"=>array()
				);				

				if(isset($aryMRTemp["SITE_NUM"])) {
					$strSN = $aryMRTemp["SITE_NUM"];
					$aryMain[$strName]["SITE_NUM"][$strSN] = array(
						"OVERRIDE"=>array(
							"OVERRIDE"=>$aryMRTemp["INV"]
						)
					);
				}else{
					foreach($arySN as $strSN) {
						$aryMain[$strName]["SITE_NUM"][$strSN] = array(
							"OVERRIDE"=>array(
								"OVERRIDE"=>$aryMRTemp["INV"]
							)
						);
					}
				}
			}
			#Print_R($aryMain[$strName]);
		}
	}

	#Print_R($aryMain["L-01755"]);
	#die();

	#Print_R($aryMain["DUMMY_1"]);
	#die();

	foreach(array_keys($aryMain) as $strName) {
		$aryReturn["HARDWARE"][$strName] = $aryMain[$strName];	
	}

	#Print_R(array_keys($aryReturn["HARDWARE"]));
	#Print_R($aryReturn["HARDWARE"]["LTC-00280"]);
	#die();

	echo "DONE.\n";
	return $aryReturn;
}


function MANUAL_RESOURCE_GET($iConRLM,$aryHWTypeAllowed,$bolDebug=false) {
	$aryTemp = array("TESTER","HANDLER");
	foreach($aryHWTypeAllowed as $strHWType) {
		$aryTemp[] = $strHWType;
	}
	foreach($aryTemp as $key => $value) {
		$aryTemp[$key] = mysqli_real_escape_string($iConRLM,$value);
	}

	$strSQL = "
		SELECT
		UCASE(MFG_PART_NUM) MFG_PART_NUM,
		UCASE(STEP_NM) STEP_NM,
		UCASE(SITE_NUM) SITE_NUM,
		UCASE(RES_TYPE) RES_TYPE,
		UCASE(RES_NM) RES_NM,
		INV,
		DATE_FORMAT(
			CASE TRUE
				WHEN START_DATE IS NULL THEN '"._DATE_MIN."'
				WHEN START_DATE < NOW() THEN '"._DATE_MIN."'
				ELSE START_DATE
			END,
			'%Y-%m-%d'
		) START_DATE,
		DATE_FORMAT(
			CASE TRUE
				WHEN END_DATE IS NULL THEN '"._DATE_MAX."'
				WHEN END_DATE < NOW() THEN NOW()
				ELSE END_DATE
			END,
			'%Y-%m-%d'
		) END_DATE
		FROM ADI_ESLDATA.ADI_RESOURCE_OVERRIDE
		WHERE RES_TYPE IS NOT NULL AND RES_NM IS NOT NULL;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	$aryWeight = array("SITE_NUM"=>10,"STEP_NM"=>100);
		
	while ($LineMain= mysqli_fetch_assoc($RSMain)) {
		$aryTemp = $LineMain;

		foreach($aryTemp as $key => $value) {
			$value = trim($value);

			switch (true) {
				case is_null($value):
					break;
				case $value == "":
					break;
				default:
					$aryTemp[$key] = $value;
					break;
			}
		}

		#more specific is heavier than less specific. step_nm is heavier than site_num
		$intTotal = 0;
		foreach($aryWeight as $strField => $intWeight) {
			if(isset($aryTemp[$strField])) {
				$intTotal += $intWeight;
			}
		}
		$aryTemp["WEIGHT"] = $intTotal;

		$aryReturn[] = $aryTemp;
	}

	#Print_R($aryReturn);
	#die();

	return $aryReturn;
}


function MFRPN_MR_MAP($aryMR,$bolDebug=false) {
	#dates are actually not important here
	$aryReturn = array();
	$aryMap["MFRPN_SHA1"] = array();

	$aryTry = array("SITE_NUM","STEP_NM");
	foreach($aryMR as $intMRID => $aryMRTemp) {
		if(isset($aryMRTemp["MFG_PART_NUM"])) {
			$strMFRPN = $aryMRTemp["MFG_PART_NUM"];

			$aryFilter = array();
			foreach($aryTry as $strTry) {
				if(isset($aryMRTemp[$strTry])) {
					$aryFilter[$strTry] = $aryMRTemp[$strTry];
				}
			}

			$aryChange = array(
				"RES_TYPE"=>$aryMRTemp["RES_TYPE"],
				"RES_NM"=>$aryMRTemp["RES_NM"]
			);


			$aryTemp = array("FILTER"=>$aryFilter,"CHANGE"=>$aryChange);
			$strSHA1 = sha1(serialize($aryTemp));

			$aryMap["MFRPN_SHA1"][$strMFRPN][$strSHA1] = $aryTemp;
		}
	}

	foreach(array_keys($aryMap["MFRPN_SHA1"]) as $strMFRPN) {
		foreach($aryMap["MFRPN_SHA1"][$strMFRPN] as $strSHA1 => $aryTemp) {
			$aryReturn[$strMFRPN][] = $aryTemp;
		}
	}

	#Print_R($aryReturn);
	return $aryReturn;
}


function HW_TYPE_ALLOWED_GET() {
	#2023-05-23 RM dont need mapping anymore. relying solely on eqpt_type from adi_tbl_hardware_inventory_autosched
	/*
	$aryMap = array();
	$aryMap["CNTCR"] = array("CNTCR","CNTCTOR","CNTCR2");
	$aryMap["DGHTRBD"] = array("DGHTRBD","DGHTBRD1","DGHTBRD2");
	$aryMap["DUTBD"] = array("DUTBD","DUT_BRD");
	$aryMap["FPLATE"] = array("FPLATE","FPLATE2");
	$aryMap["HNDLINSRT"] = array("HNDLINSRT");
	$aryMap["LOADBD"] = array("LOADBD","LOADBRD");
	$aryMap["PERFBD"] = array("PERFBD","PERFBRD","PERF_BRD","PPERFBD","PERFRBRD");
	$aryMap["TESTCARD"] = array("TESTCARD","TCARD");

	#2023-05-22 RM at KB request
	$aryMap["CASSETTE"] = array("CASSETTE");
	$aryMap["ALIPLATE"] = array("ALIPLATE");
	$aryMap["CONVKIT"] = array("CONVKIT");
	$aryMap["CUH"] = array("CUH");
	$aryMap["DPLATE"] = array("DPLATE");
	$aryMap["FMYBD"] = array("FMYBD");

	$aryRMap = array();
	foreach(array_keys($aryMap) as $strTypeThis) {
		foreach($aryMap[$strTypeThis] as $strTypeThat) {
			$aryRMap[$strTypeThat] = $strTypeThis;
		}
	}

	return array("MAP"=>$aryMap,"RMAP"=>$aryRMap);
	*/

	#$aryReturn = array('CNTCR','DGHTRBD','DUTBD','FPLATE','HNDLINSRT','LOADBD','PERFBD','TESTCARD','CASSETTE','ALIPLATE','CONVKIT','CUH','DPLATE','FMYBD');

	#2023-10-20 RM RE: HW Part Level Exception
	/*1.	We will have a final list of HW TYPES that will be our default to consider like example below: 
	CNTCR
	DUTBD
	TESTCARD
	LOADBD
	DGHTRBD
	PERFBD
	*/

	$aryReturn = array('CNTCR','DUTBD','TESTCARD','LOADBD','DGHTRBD','PERFBD');

	#2023-12-11 RM requested by KB
	$aryAdd = array('FPLATE','FMYBD','CONVKIT');

	#2024-04-19 requested by KB
	#2024-04-213 RM now replacing spaces with underscore _ for any downstream tables that use hardware types as column names
	#$aryAdd[] = "TCVOS_BOARD";
	#actually should be this:
	$aryAdd[] = "TCVOSBD";
	$aryAdd[] = "BURNIN_BOARD";

	foreach($aryAdd as $strType) {
		$aryReturn[] = $strType;
	}
	$aryReturn = array_unique($aryReturn);


	return $aryReturn;
}


function AR_GET_PG($iConRLM,$bolDebug=false) {
	$aryReturn = array(
		"RES_DEDICATION"=>array(),
		"RES_EXCEPTION"=>array(),
		"RES_EXCEPTION_ADD"=>array(),
		"RES_DEF_CONV"=>array(),
		"RES_TT_COLD"=>array()
	);
	
	return $aryReturn;

}


function AR_GET($iConRLM,$bolDebug=false) {
	$aryReturn = array(
		"RES_DEDICATION"=>array(),
		"RES_EXCEPTION"=>array(),
		"RES_EXCEPTION_ADD"=>array(),
		"RES_DEF_CONV"=>array(),
		"RES_TT_COLD"=>array()
	);

	$strSQL = "
		SELECT
		SERIES,
		STAGE,
		OLDTESTER,
		NEWTESTER,
		OLDHANDLER,
		NEWHANDLER
		FROM ADI_ESLDATA.ADI_RES_DEDICATION
		ORDER BY SERIES, STAGE, OLDTESTER, OLDHANDLER;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strPN = $LineMain["SERIES"];
		$strStepName = $LineMain["STAGE"];
		$strOT = $LineMain["OLDTESTER"];
		$strOH = $LineMain["OLDHANDLER"];

		$aryTemp = array(
			"TESTER"=>$LineMain["NEWTESTER"],
			"HANDLER"=>$LineMain["NEWHANDLER"]
		);

		$aryReturn["RES_DEDICATION"][$strPN][$strStepName][$strOT][$strOH] = $aryTemp;
	}


	$strSQL = "
		SELECT DISTINCT
		UCASE(RESOURCETYPE) RESOURCE_TYPE,
		DEFAULTNAME,
		ATOMNAME
		FROM ADI_ESLDATA.ADI_RES_EXCPTN;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strRT = $LineMain["RESOURCE_TYPE"];
		$strFrom = $LineMain["DEFAULTNAME"];
		$aryReturn["RES_EXCEPTION"][$strRT][$strFrom] = $LineMain["ATOMNAME"];
	}


	$strSQL = "
		SELECT
		ATOMTESTER,
		ATOMHANDLER,
		NEWTESTER,
		NEWHANDLER
		FROM ADI_ESLDATA.ADI_RES_EXCPTN_ADD;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strTester = $LineMain["ATOMTESTER"];
		$strHandler = $LineMain["ATOMHANDLER"];

		$aryTemp = array(
			"TESTER"=>$LineMain["NEWTESTER"],
			"HANDLER"=>$LineMain["NEWHANDLER"]
		);

		$aryReturn["RES_EXCEPTION_ADD"][$strTester][$strHandler] = $aryTemp;
	}


	$strSQL = "
		SELECT
		UCASE(RESOURCETYPE) RESOURCETYPE,
		RESOURCENAME
		FROM ADI_ESLDATA.ADI_RES_DEF_CONV;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		#handled differently from DEDICATION (pattern match)

		$strRT = $LineMain["RESOURCETYPE"];
		$aryReturn["RES_DEF_CONV"][$strRT][] = $LineMain["RESOURCENAME"];
	}


	$strSQL = "
		SELECT DISTINCT
		PARTNAME,
		HANDLER
		FROM ADI_ESLDATA.ADI_RES_TRITEMP_CLD
		ORDER BY PARTNAME, HANDLER;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strPN = $LineMain["PARTNAME"];
		$aryReturn["RES_TT_COLD"][$strPN][] = $LineMain["HANDLER"];
	}

	return $aryReturn;

}


function ATOM_NAME_GET($aryAR,$aryInput,$bolDebug=false) {
	$strCat12 = $aryInput["CAT_12"];
	$strCat38 = $aryInput["CAT_38"];
	$strCat41 = $aryInput["CAT_41"];
	$strTesterOriginal = $aryInput["TESTER"];
	$strTester = $aryInput["TESTER"];
	$strTesterNew = $strTester;

	$aryRouteSteps = $aryInput["ROUTE_STEPS"];
	$aryFlag = array();


	$aryATOM = array("TESTER"=>array(),"HANDLER"=>array());
	switch (true) {
		case $strCat41 == "STR":
			$strCat41New = "STRIP";
			$aryATOM["CAT_41"][__LINE__] = array($strCat41=>$strCat41New);
			$strCat41 = $strCat41New;
			break;
		case $strCat41 == "SOF":
			$strCat41New = "SOF_WLT";
			$aryATOM["CAT_41"][__LINE__] = array($strCat41=>$strCat41New);
			$strCat41 = $strCat41New;
			break;
	}

	/*
	UPDATE padis_uph_stage
	SET pk_tester = 'LTS2020'
	WHERE pk_tester = 'LTS'
	*/
	switch (true) {
		case $strTester == "LTS":
			$strTesterNew = "LTS2020";
			$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
			$strTester = $strTesterNew;
			#so dumb
			$strTesterOriginal = $strTesterNew;
			break;
	}


	/*
	UPDATE padis_uph_stage
	set AtomTester = Rtrim(pk_Tester) + '_' + isnull(Rtrim(ProdEntry),'') + CASE WHEN TestGroup IS NULL THEN  '' ELSE '_' + Rtrim(replace(TestGroup,'_STD1','')) END 
	Where pk_Tester in (SELECT ResourceName FROM padis_resource_default_convention where ResourceType = 'Tester')
	and Plant_site = 'adgt'
	and PartType = 'T'
	and stage_procedure not in ('TFTCVOS_M1','TQTCVOS_M1')  
	#2023-07-26 RM request by KB to include ADPI
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	#2023-05-10 RM double-check if they mean the setup step or the test step. based on nomenclature it appears to be the test, so this is probably ok
	$aryTemp = array();
	switch (true) {
		case isset($aryAR["RES_DEF_CONV"]["TESTER"]) && in_array($strTesterOriginal,$aryAR["RES_DEF_CONV"]["TESTER"]):
			break;
		case $aryInput["SITE_NUM"] != "ADGT" && $aryInput["SITE_NUM"] != "ADPI":
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		case $aryInput["STEP_NAME"] == "TFTCVOS_M1" || $aryInput["STEP_NAME"] == "TQTCVOS_M1" || strpos($aryInput["STEP_NAME"],"TMFT") !== false || strpos($aryInput["STEP_NAME"],"TMQC") !== false:
			break;
		default:
			if($strCat41 != "") {
				$aryTemp[] = $strCat41;
			}
			if($strCat38 != "") {
				$aryTemp[] = str_replace("_STD1","",$strCat38);
			}
			
			if(count($aryTemp) > 0) {
				$strTesterNew = $strTesterOriginal."_".implode("_",$aryTemp);
			}

			break;
	}

	
	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}



	/*
	Update padis_uph_stage
	Set AtomTester = Rtrim(pk_Tester) + '_' + isnull(Rtrim(ProdEntry),'')
	Where pk_Tester not in (SELECT ResourceName FROM padis_resource_default_convention where ResourceType = 'Tester')
	and rtrim(pk_tester) not  in ('SING','2DMARK','2DMARK_SBRAND','ISO',
				'TRPRCON','THSDIP','TRBURNW','TBURNDYNA',
				'TFBUSSA','TBURNW','BRDLOAD','BURN IN OVEN',
				'EJ OVEN','EJ_OVEN','SBAKEW','TBAKEW','TCVOS',''
				,'STATS','ADWIL','ADLIM')
	and stage_procedure not in ('TFTCVOS_M1','TQTCVOS_M1')                                     
	and Plant_site = 'adgt'
	and PartType = 'T'
	#2023-07-26 RM request by KB to include ADPI
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	#2023-05-10 RM double-check if they mean the setup step or the test step. based on nomenclature it appears to be the test, so this is probably ok
	$aryIgnore = array('SING','2DMARK','2DMARK_SBRAND','ISO','TRPRCON','THSDIP','TRBURNW','TBURNDYNA','TFBUSSA','TBURNW','BRDLOAD','BURN IN OVEN','EJ OVEN','EJ_OVEN','SBAKEW','BAKEW','TCVOS','','STATS','ADWIL','ADLIM');
	$aryTemp = array();
	switch (true) {
		case isset($aryAR["RES_DEF_CONV"]["TESTER"]) && in_array($strTesterOriginal,$aryAR["RES_DEF_CONV"]["TESTER"]):
			break;
		case in_array($strTesterOriginal,$aryIgnore):
			break;
		case $aryInput["STEP_NAME"] == "TFTCVOS_M1" || $aryInput["STEP_NAME"] == "TQTCVOS_M1" || strpos($aryInput["STEP_NAME"],"TMFT") !== false || strpos($aryInput["STEP_NAME"],"TMQC") !== false:
			break;
		case $aryInput["SITE_NUM"] != "ADGT" && $aryInput["SITE_NUM"] != "ADPI":
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		case $aryInput["CAT_41"] == "":
			break;
		default:
			$aryTemp[] = $strCat41;
			break;
	}

	if(count($aryTemp) > 0) {
		$strTesterNew = $strTesterOriginal."_".implode("_",$aryTemp);
	}

	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}



	/*
	-- To update TCVOS_R ----
	UPDATE PADIS_UPH_STAGE
	SET atomTester = 'TCVOS_R'
	WHERE pk_tester = 'TCVOS'
	and pkg = 'SOIC_N'
	and PartType = 'T'
	#2024-09-27 RM did not add part_type D as this filters for specific package codes already
	*/

	#2023-05-11 RM note that PKG = PKG_TYPE, which is differnt from PKGCODE, which is likely a part category not currently being fed to me
	switch (true) {
		case $strTesterOriginal != "TCVOS":
			break;
		case $aryInput["PKG_TYPE"] != "SOIC_N":
			break;
		case $aryInput["PART_TYPE"] != "T":
			break;
		default:
			$strTesterNew = "TCVOS_R";
			break;
	}

	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}


	/*
	Update stage
	set Stage.AtomTester = rtrim(exception.AtomName)
	From Padis_upH_stage stage
	INNER JOIN padis_resource_exception exception
	on rtrim(stage.atomTester) = rtrim(exception.defaultname)
	and Stage.PartType = 'T'
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	switch (true) {
		case !isset($aryAR["RES_EXCEPTION"]["TESTER"][$strTester]):
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		default:
			$strTesterNew = $aryAR["RES_EXCEPTION"]["TESTER"][$strTester];

			break;
	}
	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}


	/*
	Update PADIS_UPH_STAGE
	SET AtomTester = 'LTS2020_SCANNER_COM'
	WHERE AtomTester in ('LTS2020_COM')
	and stage_procedure like   ('%SCANNER%')
	and PartType = 'T'
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/
	$aryTemp = array("LTS2020_COM");
	switch (true) {
		case !in_array($strTester,$aryTemp):
			break;
		case strpos($aryInput["STEP_NAME"],"SCANNER") === false:
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		default:
			$strTesterNew = "LTS2020_SCANNER_COM";
			break;
	}
	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}


	/*
	UPDATE PADIS_UPH_STAGE
	SET AtomTester = 'CTS5400_A_HPCC' 
	WHERE AtomTester = 'CTS5400_HPCC'

	UPDATE PADIS_UPH_STAGE
	SET AtomTester = 'CTS5400_A_HPCS' 
	WHERE AtomTester = 'CTS5400_HPCS'

	UPDATE PADIS_UPH_STAGE
	SET AtomTester = 'CTS5400_A_HPCA' 
	WHERE AtomTester = 'CTS5400_HPCA'
	*/
	switch (true) {
		case $strTester == "CTS5400_HPCC":
			$strTesterNew = "CTS5400_A_HPCC";
			break;
		case $strTester == "CTS5400_HPCS":
			$strTesterNew = "CTS5400_A_HPCS";
			break;
		case $strTester == "CTS5400_HPCA":
			$strTesterNew = "CTS5400_A_HPCA";
			break;
	}

	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}


	/*
	UPDATE PADIS_UPH_STAGE
	SET AtomTester = 'HARRIS_TUVEY_9464_SSOP_' + Rtrim(ProdEntry)
	WHERE AtomTester in ('HARRIS_TUVEY_9464','HARRIS_TUVEY_9464')
	and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
				WHERE pk_Tester like 'HARRIS%')
	and PRODENTRY  IN ('INT', 'HPCS')
	and  PKG_CODE  in ('RS')
	*/

	# why is %HARRIS% necessary????
	$aryCat41 = array("INT","HPCS");
	switch (true) {
		case $strTester != "HARRIS_TUVEY_9464":
			break;
		case strpos($strTesterOriginal,"HARRIS") !== 0:
			break;
		case !in_array($strCat41,$aryCat41):
			break;
		case $strCat12 != "RS":
			break;
		default:
			$strTesterNew = "HARRIS_TUVEY_9464_SSOP_".$strCat41;
			break;
	}
	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}

	/*
	UPDATE PADIS_UPH_STAGE
	SET ATOMTESTER = 'TS88_EXP_VOS_LTI'
	WHERE PK_PART_NAME IN (
		Select PK_PART_NAME from PADIS_UPH_STAGE  
		WHERE (stage_procedure LIKE 'TFTCVOS%' or stage_procedure LIKE 'TQTCVOS%' )                                    
		AND PRODENTRY like 'LT%'
	)
	AND ATOMTESTER = 'TS88_EXP_LTI'

	UPDATE PADIS_UPH_STAGE
	SET ATOMTESTER = 'TS88_STD_VOS_LTI'
	WHERE PK_PART_NAME IN (
		Select PK_PART_NAME from PADIS_UPH_STAGE  
		WHERE (stage_procedure LIKE 'TFTCVOS%' or stage_procedure LIKE 'TQTCVOS%' )                                    
		AND PRODENTRY like 'LT%'
	)
	AND ATOMTESTER = 'TS88_STD_LTI'

	#2024-02-14 RM TFTCVOS = TMFT, TQTCVOS = TMQA
	*/

	#2023-05-10 RM wont be match on step name because the STAGE is not the same as the TEST
	$bolMatch = false;
	foreach($aryRouteSteps as $strStepName) {
		switch (true) {
			case strpos($strStepName,"TFTCVOS") === 0 || strpos($strStepName,"TMFT") === 0:
				$bolMatch = true;
				#echo "1 / ".$strStepName."\n";
				break;
			case strpos($strStepName,"TQTCVOS") === 0 || strpos($strStepName,"TMQA") === 0 || strpos($strStepName,"TMQC") === 0:
				#2024-07-26 RM added OPC step names to make it still work when prods created in opc and not in pp
				$bolMatch = true;
				#echo "2 / ".$strStepName."\n";
				break;
			case strpos($strStepName,"TQCLASS") === 0:
				#2024-08-01 RM make sure you dont include TQCLASS since TQC is checked after this
				#do nothing, this is not a vos step
				break;
			case strpos($strStepName,"TQC") === 0:
				$bolMatch = true;
				break;
		}
	}
	
	switch (true) {
		case $bolMatch !== true:
			break;
		case strpos($strCat41,"LT") !== 0:
			break;
		case $strTester == "TS88_EXP_LTI":
			$strTesterNew = "TS88_EXP_VOS_LTI";
			break;
		case $strTester == "TS88_STD_LTI":
			$strTesterNew = "TS88_STD_VOS_LTI";
			break;
	}

	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}


	/*
	Update PADIS_UPH_STAGE
	SET AtomTester =  'CTS5340_HSC_HYBRID_HPCS'
	Where  AtomTester = 'CTS5340_HSC_HPCS'
	*/

	if($strTester == "CTS5340_HSC_HPCS") {
		$strTesterNew = "CTS5340_HSC_HYBRID_HPCS";
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}



#*****************************************************************
#HANDLER HANDLER HANDLER HANDLER HANDLER HANDLER HANDLER HANDLER 
#*****************************************************************

	$strHandler = $aryInput["HANDLER"];
	$strHandlerOriginal = $strHandler;
	$strHandlerNew = $strHandler;

	/*
	UPDATE padis_uph_stage
	set AtomHandler = rtrim(pk_handler)
	UPDATE padis_uph_stage
	set AtomHandler = Rtrim(pk_Handler) + '_' + Rtrim(convert(varchar(3),LeadCount)) + '_' + Rtrim(Pkg_code) + '_' + isnull(Rtrim(ProdEntry),'')
	Where pk_Handler in (SELECT ResourceName FROM padis_resource_default_convention where ResourceType = 'Handler')
	and Plant_site = 'adgt'
	and PartType = 'T'
	and stage_procedure not in ('TFTCVOS_M1','TQTCVOS_M1')  
	#2023-07-26 RM request by KB to include ADPI
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	#CAT12 is PKG_GROUP aka PKG_CODE as referred to above. its not package code btw. its package group, really
	$aryTemp = array();
	switch (true) {
		case isset($aryAR["RES_DEF_CONV"]["HANDLER"]) &&!in_array($strHandlerOriginal,$aryAR["RES_DEF_CONV"]["HANDLER"]):
			break;
		case $aryInput["SITE_NUM"] != "ADGT" && $aryInput["SITE_NUM"] != "ADPI":
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		default:
			if($aryInput["LEAD_COUNT"] != "") {
				$aryTemp[] = $aryInput["LEAD_COUNT"];
			}
			if($strCat12 != "") {
				$aryTemp[] = $strCat12;
			}
			if($strCat41 != "") {
				$aryTemp[] = $strCat41;
			}
			break;
	}

	if(count($aryTemp) > 0) {
		$strHandlerNew = $strHandlerOriginal."_".implode("_",$aryTemp);
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;
	}


	/*
	UPDATE PADIS_UPH_STAGE
	SET atomHandler = Rtrim(pk_handler) + '_' + isnull(Rtrim(ProdEntry),'')
	Where  pk_Handler NOT in (SELECT ResourceName FROM padis_resource_default_convention where ResourceType = 'Handler')
	and RTRIM(pk_HANDLER) not in ('','HANDTEST')
	and Plant_site = 'ADGT'
	and PartType = 'T'
	and stage_procedure not in ('TFTCVOS_M1','TQTCVOS_M1')  
	#2023-07-26 RM request by KB to include ADPI
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	switch (true) {
		case isset($aryAR["RES_DEF_CONV"]["HANDLER"]) &&in_array($strHandlerOriginal,$aryAR["RES_DEF_CONV"]["HANDLER"]):
			break;
		case $strHandlerOriginal == "" || $strHandlerOriginal == "HANDTEST":
			break;
		case $aryInput["SITE_NUM"] != "ADGT" && $aryInput["SITE_NUM"] != "ADPI":
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		case $aryInput["STEP_NAME"] == "TFTCVOS_M1" || $aryInput["STEP_NAME"] == "TQTCVOS_M1" || strpos($aryInput["STEP_NAME"],"TMFT") !== false || strpos($aryInput["STEP_NAME"],"TMQC") !== false:
			break;
		default:
			if($strCat41 != "") {
				$strHandlerNew = $strHandlerOriginal."_".$strCat41;
			}

			break;
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;	
	}


	/*
	Update stage
	set Stage.AtomHandler = rtrim(exception.AtomName)
	From Padis_upH_stage stage
	INNER JOIN padis_resource_exception exception
	on rtrim(stage.atomHandler) =  rtrim(exception.defaultname)
	and stage.PartType = 'T'
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	switch (true) {
		case !isset($aryAR["RES_EXCEPTION"]["HANDLER"][$strHandler]):
			break;
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		default:
			$strHandlerNew = $aryAR["RES_EXCEPTION"]["HANDLER"][$strHandler];
			if($strHandlerNew != $strHandler) {
				$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
				$strHandler = $strHandlerNew;
			}
			break;
	}

	/*
	UPDATE PADIS_UPH_STAGE --Added 6/16/2011 
	Set AtomHandler = 'MT9918_PB_NS_HT_' + Rtrim(ProdEntry)
	Where AtomHandler  IN ('MT9918_PB_NS_INT') --, 'MT9918_PB_NS_HPCS') -- Update #3: Removed 2/16/2023
	and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
				WHERE pk_Tester like 'HARRIS%')
	------and pk_Tester not like 'FLEX% Update # 2

	-- Update #3: Removed 2/16/2023
	UPDATE PADIS_UPH_STAGE
	Set AtomHandler = 'MT9918_PB_R_HT_' + Rtrim(ProdEntry)
	Where AtomHandler IN  ('MT9918_PB_R_INT') --, 'MT9918_PB_R_HPCS') -- Update #3: Removed 2/16/2023   
	and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
				WHERE pk_Tester like 'HARRIS%')
	--and pk_Tester not like 'FLEX%'  Update # 2

	-- Update #3: Removed 2/16/2023
	--UPDATE PADIS_UPH_STAGE
	--Set AtomHandler = 'MT9918_PB_RW_HT_' + Rtrim(ProdEntry)
	--Where AtomHandler  IN ('MT9918_PB_RW_HPCS')
	--and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
	--			WHERE pk_Tester like 'HARRIS%')
	--and pk_Tester not like 'FLEX%'   Update # 2

	-- Added June 27, 2012 for MT308_PB_RW_HPCC and MT9918_PB_RW_HPCC
	UPDATE PADIS_UPH_STAGE
	Set AtomHandler = 'MT9918_PB_RW_HT_HPCC'
	Where AtomHandler IN ('MT9918_PB_RW_HPCC')
	and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
				WHERE pk_Tester like 'HARRIS%')

	-- Update #3: Removed 2/16/2023
	----UPDATE PADIS_UPH_STAGE
	----Set AtomHandler = 'MT9308_PB_RW_HT_HPCC'
	----Where AtomHandler IN ('MT9308_PB_RW_HPCC')
	----and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
	----			WHERE pk_Tester like 'HARRIS%')
	*/

	switch (true) {
		case strpos($strTesterOriginal,"HARRIS") !== 0:
			break;
		case $strHandler == "MT9918_PB_NS_INT":
			if($strCat41 != "") {
				$strHandlerNew = "MT9918_PB_NS_INT_".$strCat41;
			}
			break;
		case $strHandler == "MT9918_PB_R_INT":
			if($strCat41 != "") {
				$strHandlerNew = "MT9918_PB_R_HT_".$strCat41;
			}
			break;
		case $strHandler == "MT9918_PB_RW_HPCC":
			if($strCat41 != "") {
				$strHandlerNew = "MT9918_PB_RW_HT_HPCC";
			}
			break;
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;
	}


	/*
	-- TRITEMP COLD--
	Update  PADIS_UPH_STAGE
	set AtomHandler = 'MT9308_PB_RW_COLD_INT'
	Where rtrim(pk_part_name) in (SELECT Rtrim(partname) from PADIS_RESOURCE_TRITEMP_COLD)
	and PartType = 'T'
	and prodentry = 'INT' --Added 2/2/2011
	and AtomHandler like 'MT9308_PB%' --Added tin Reyes 9/8/2011

	--Added by Tin Reyes 2/2/2011 as Per RREbong Request to map COM parts with Cold to MT9308_PB_RW_COLD_COM
	Update  PADIS_UPH_STAGE
	set AtomHandler = 'MT9308_PB_RW_COLD_COM'
	Where rtrim(pk_part_name) in (SELECT Rtrim(partname) from PADIS_RESOURCE_TRITEMP_COLD)
	and PartType = 'T'
	and prodentry = 'COM'
	and AtomHandler like 'MT9308_PB%' --Added tin Reyes 9/8/2011

	Update  PADIS_UPH_STAGE
	set AtomHandler = 'MT9308_PB_RW_COLD_HPCC'
	Where rtrim(pk_part_name) in (SELECT Rtrim(partname) from PADIS_RESOURCE_TRITEMP_COLD)
	and PartType = 'T'
	and prodentry = 'HPCC' --Added 2/2/2011
	and AtomHandler like 'MT9308_PB%' --Added tin Reyes 2/9/2012

	Update  PADIS_UPH_STAGE
	set AtomHandler = 'MT9918_PB_RW_COLD_HPCC'
	Where rtrim(pk_part_name) in (SELECT Rtrim(partname) from PADIS_RESOURCE_TRITEMP_COLD)
	and PartType = 'T'
	and prodentry = 'HPCC' 
	and AtomHandler like 'MT9918_PB%' --Added tin Reyes 2/9/2012
	#2024-09-27 RM did not add part_type = D as these are specific package handlers
	*/

	switch (true) {
		case $aryInput["PART_TYPE"] != "T":
			break;
		case !in_array($aryInput["PART_NAME"],$aryAR["RES_TT_COLD"]):
			break;
		case strpos($strHandler,"MT9308_PB") === 0 && $strCat41 == "INT":
			$strHandlerNew = "MT9308_PB_RW_COLD_INT";
			break;
		case strpos($strHandler,"MT9308_PB") === 0 && $strCat41 == "COM":
			$strHandlerNew = "MT9308_PB_RW_COLD_COM";
			break;
		case strpos($strHandler,"MT9308_PB") === 0 && $strCat41 == "HPCC":
			$strHandlerNew = "MT9308_PB_RW_COLD_HPCC";
			break;
		case strpos($strHandler,"MT9918_PB") === 0 && $strCat41 == "HPCC":
			$strHandlerNew = "MT9918_PB_RW_COLD_HPCC";
			break;
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;
	}


	/*
	UPDATE PADIS_UPH_STAGE
	SET AtomHandler ='AETRIUM_RW_HV_INT'
	WHERE AtomHandler = 'AETRIUM_RW_HV_ADLK_INT'
	and pk_part_name in (Select pk_part_name from PADIS_UPH_STAGE
				WHERE pk_Tester like 'HARRIS%')
	and PRODENTRY = 'INT'
	*/

	switch (true) {
		case strpos($strTesterOriginal,"HARRIS") !== 0:
			break;
		case $aryInput["CAT_41"] != "INT":
			break;
		case $strHandler != "AETRIUM_RW_HV_ADLK_INT":
			break;
		default:
			$strHandlerNew = "AETRIUM_RW_HV_INT";
			break;
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;
	}


	/*
	UPDATE stage
	SET stage.atomTester = RTRIM(ex.NewTester),
	stage.atomHandler = RTRIM(ex.NewHandler)
	FROM PADIS_UPH_STAGE stage 
	Inner Join   PADIS_RESOURCE_EXCEPTION3 ex
	on RTRIM(ex.OldTester) = RTRIM(stage.atomTester)
	and RTRIM(ex.OldHandler) = RTRIM(stage.atomHandler)
	and stage.PartType = 'T'
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/

	switch (true) {
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		case !isset($aryAR["RES_EXCEPTION_ADD"][$strTester][$strHandler]):
			break;
		default:
			$aryARTemp = $aryAR["RES_EXCEPTION_ADD"][$strTester][$strHandler];
			$strTesterNew = $aryARTemp["TESTER"];
			$strHandlerNew = $aryARTemp["HANDLER"];
			break;
	}

	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;
	}



	/*
	Update stage
	set stage.AtomTester = rtrim(replace(dedication.NewTester, char(160), char(32))), --rtrim(dedication.NewTester),
	stage.AtomHandler = rtrim(replace(dedication.NewHandler, char(160), char(32))) --rtrim(dedication.NewHandler)
	--pk_stage_sequence = Rtrim(replace(pk_stage_sequence, '.5','')),
	--alternate = 0
	  --Added tin Reyes 3/10/2015
	From PADIS_UPH_STAGE stage 
	Inner Join PADIS_RESOURCE_DEDICATION Dedication
	--on stage.pk_part_name like '%'+rtrim(dedication.series) +'%'
	on stage.pk_part_name  = rtrim(dedication.series)
	AND rtrim(replace(stage.stage_procedure, char(160), char(32))) = rtrim(dedication.stage)
	and rtrim(replace(dedication.OldTester, char(160), char(32)))= stage.AtomTester
	and rtrim(replace(dedication.OldHandler, char(160), char(32)))= stage.AtomHandler
	and stage.PartType = 'T'
	AND Dedication.deletedflag = 0
	#2024-09-27 RM added PART_TYPE D for GT probe
	*/
	
	#$aryReturn["RES_DEDICATION"][$strPN][$strStepName][$strTester][$strHandler] = $aryTemp;
	$strPNTemp = substr($aryInput["PART_NAME"],0,32);
	$strStepNameTemp = substr($aryInput["STEP_NAME"],0,32);
	$strTesterTemp = substr($strTester,0,32);
	$strHandlerTemp = substr($strHandler,0,32);

	#2024-07-11 RM
	$strPNTempFake = str_replace("^",".",$strPNTemp);
	
	#2023-11-01 RM old version
	switch (true) {
		case $aryInput["PART_TYPE"] != "T" && $aryInput["PART_TYPE"] != "D":
			break;
		case isset($aryAR["RES_DEDICATION"][$strPNTemp][$strStepNameTemp][$strTesterTemp][$strHandlerTemp]):
			$aryARTemp = $aryAR["RES_DEDICATION"][$strPNTemp][$strStepNameTemp][$strTesterTemp][$strHandlerTemp];
			$strTesterNew = $aryARTemp["TESTER"];
			$strHandlerNew = $aryARTemp["HANDLER"];

			$aryFlag[] = "RES_DED";
			break;
		case isset($aryAR["RES_DEDICATION"][$strPNTempFake][$strStepNameTemp][$strTesterTemp][$strHandlerTemp]):
			#2024-07-11 RM second check for f#cked up parts with ^ in OPC and . in PP. so fing stupid

			$aryARTemp = $aryAR["RES_DEDICATION"][$strPNTempFake][$strStepNameTemp][$strTesterTemp][$strHandlerTemp];
			$strTesterNew = $aryARTemp["TESTER"];
			$strHandlerNew = $aryARTemp["HANDLER"];

			$aryFlag[] = "RES_DED";
			break;
	}

	if($strTesterNew != $strTester) {
		$aryATOM["TESTER"][__LINE__] = array($strTester=>$strTesterNew);
		$strTester = $strTesterNew;
	}
	if($strHandlerNew != $strHandler) {
		$aryATOM["HANDLER"][__LINE__] = array($strHandler=>$strHandlerNew);
		$strHandler = $strHandlerNew;
	}

	#2023-11-01 RM (move this up) now 2 check layers. first check for exact match on step. if not found, check w/out step filter
	/*
	switch (true) {
		case $aryInput["PART_TYPE"] != "T":
			break;
		case isset($aryAR["RES_DEDICATION"][$strPNTemp][$strStepNameTemp][$strTesterTemp][$strHandlerTemp]):
			$aryARTemp = $aryAR["RES_DEDICATION"][$strPNTemp][$strStepNameTemp][$strTesterTemp][$strHandlerTemp];
			$strTesterNew = $aryARTemp["TESTER"];
			$strHandlerNew = $aryARTemp["HANDLER"];

			$aryFlag[] = "RES_DED";
			break;
		case isset($aryAR["RES_DEDICATION"][$strPNTemp]):
			$aryMap["TH_SN"] = array();
			foreach(array_keys($aryAR["RES_DEDICATION"][$strPNTemp]) as $strStepNamePool) {
				foreach(array_keys($aryAR["RES_DEDICATION"][$strPNTemp][$strStepNamePool]) as $strTesterPool) {
					foreach(array_keys($aryAR["RES_DEDICATION"][$strPNTemp][$strStepNamePool][$strTesterPool]) as $strHandlerPool) {
						$aryMap["TH_SN"][$strTesterPool][$strHandlerPool][] = $strStepNamePool;
					}
				}
			}

			if(isset($aryMap["TH_SN"][$strTesterTemp][$strHandlerTemp])) {
				#exists, so go ahead and use it
				foreach($aryMap["TH_SN"][$strTesterTemp][$strHandlerTemp] as $strStepNamePool) {
					$aryARTemp = $aryAR["RES_DEDICATION"][$strPNTemp][$strStepNamePool][$strTesterTemp][$strHandlerTemp];

					$strTesterNew = $aryARTemp["TESTER"];
					$strHandlerNew = $aryARTemp["HANDLER"];

					$aryFlag[] = "RES_DED";
				}
			}
			break;
	}
	*/

	#skipping these weird ded/gen items

	#preserve the ability to send back more than one tester/handler. just in case	
	$aryReturn = array(
		"TESTER"=>array($strTesterNew),
		"HANDLER"=>array($strHandlerNew),
		"ATOM"=>$aryATOM,
		"FLAG"=>$aryFlag
	);

	return $aryReturn;
}


function RX_GET_PG() {
	#2023-03-31 RM dropped ending $ for TEST.FIND for -MA, wahtever that means
	#2023-06-15 RM added (_P) for hv test, presumably?
	#2023-12-01 RM added TSIS
	$aryRX = array(
		"ROUTE"=>array(
			"TEST"=>array(
				"FIND"=>array(
					"^T",
					"(F)?",
					"(Q)?",
					"(AUTOAC)?",
					"(ACTIMING)?",
					"(BUSSACCESS)?",
					"(CLASS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRIFTOILBATH)?",
					"(DRIFT)?",
					"(FLUIDBATH)?",
					"(LAT|LATCH)?",
					"(NOISE)?",
					"(OILBATH)?",
					"(PREBURN)?",
					"(PREBIOILBATH)?",
					"(PRESCRN)?",
					"(POSTBURN)?",
					"(POSTBIOILBATH)?",
					"(SCANNER)?",
					"(STRIP)?",
					"(VOLTAGE)?",
					"(TCVOS)?",
					"(INERTIAL_ISENSOR)?",
					"(BAROMETER_ISENSOR)?",
					"(BENCH)?",
					"(MAGNETOMETER_ISENSOR)?",
					"_",
					"[Q]?",
					"([A|H|C|F|M])",
					"([0-9])+",
					"(_P$)?"
				),
				"FIELD"=>array(
					"",
					"IS_TEST",
					"IS_QA",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_FLU",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRBOBT",
					"IS_PRSC",
					"IS_PSB",
					"IS_PSBOBT",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"TEMP_CLASS",
					"STEP_TYPE_NUM",
					"IS_HV"
				)
			)
		),
		"PARAM"=>array(
			"HANDLER"=>array(
				"FIND"=>array(
					"^TS",
					"([0-9]+)",
					"(AAC)?",
					"(ACT)?",
					"(BUS)?",
					"(CLS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRFOB)?",
					"(DRF)?",
					"(FLU)?",
					"(LAT)?",
					"(NSE)?",
					"(OBT)?",
					"(PRB)?",
					"(PRSC|PRSCA)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOL)?",
					"(VOS)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"([0-9]+)",
					"H",
					"([0-9]+)",
					"(_P)?",
					"$"
				),
				"FIELD"=>array(
					"",
					#2023-07-06 RM removed IS_SETUP
					"TESTER_NUM",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_FLU",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"SETUP_NUM",
					"HANDLER_NUM",
					"IS_HV"
				)
			),
			"HARDWARE"=>array(
				"FIND"=>array(
					"^T",
					"(S)?",
					"(F)?",
					"(Q)?",
					"([0-9]+)",
					"(AAC)?",
					"(ACT)?",
					"(BUS)?",
					"(CLS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRFOB)?",
					"(DRF)?",
					"(FLU)?",
					"(LAT)?",
					"(NSE)?",
					"(OBT)?",
					"(PRB)?",
					"(PRSC|PRSCA)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOL)?",
					"(VOS)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"([0-9]+)",
					"H",
					"([0-9]+)",
					"(_P)?",
					"E",
					"([0-9]+)",
					"(O)?",
					"([0-9]+)?",
					"$"
				),
				"FIELD"=>array(
					"",
					"IS_SETUP",
					"IS_TEST",
					"IS_QA",
					"TESTER_NUM",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_FLU",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"SETUP_NUM",
					"HANDLER_NUM",
					"IS_HV",
					"HW_PRI_NUM",
					"HAS_HW_ALT",
					"HW_ALT_NUM"
				)
			),
			"PKG_TYPE"=>array(
				"FIND"=>array("^(PACKAGETYPE)$"),
				"FIELD"=>array("","PKG_TYPE")
			),
			"PART_TYPE"=>array(
				"FIND"=>array("^(PARTTYPE)$"),
				"FIELD"=>array("","PART_TYPE")
			),
			"LEAD_COUNT"=>array(
				"FIND"=>array("^(LEADCOUNT)$"),
				"FIELD"=>array("","LEAD_COUNT")
			),
			"GENERIC"=>array(
				"FIND"=>array(
					"^",
					"(GENERIC[0-9]?)",
					"$"
				),
				"FIELD"=>array(
					"",
					"GENERIC",
					""
				)
			),
			"YLD"=>array(
				"FIND"=>array(
					"^YLD_",
					"(AAC)?",
					"(ACT)?",
					"(CLS)?",
					"(DRF)?",
					"(LAT)?",
					"(PRB)?",
					"(PRS)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOS)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"([0-9]+)",
					"(_P)?",
					"$"
				),
				"FIELD"=>array(
					"",
					"IS_AAC",
					"IS_ACT",
					"IS_CLS",
					"IS_DRF",
					"IS_LAT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"STEP_TYPE_NUM",
					"IS_HV"
				)
			),
			"TESTER_BASE"=>array(
				"FIND"=>array(
					"^",
					"S([0-9]+)",
					"$"
				),
				"FIELD"=>array(
					"",
					"TESTER_NUM",
					""
				)
			),					
			"TESTER_CONFIG"=>array(
				"FIND"=>array(
					"^",
					"S([0-9]+)",
					"_CFG[0-9]?$"
				),
				"FIELD"=>array(
					"",
					"TESTER_NUM",
					""
				)
			),
			"HANDLER_BASE"=>array(
				"FIND"=>array(
					"^",
					"P([0-9]+)",
					"S([0-9]+)",
					"H([0-9]+)",
					"($)"
				),
				"FIELD"=>array(
					"",
					"PASS_NUM",
					"TESTER_NUM",
					"HANDLER_NUM",
					""
				)
			),
			"HANDLER_CONFIG"=>array(
				"FIND"=>array(
					"^",
					"H([0-9]+)",
					"_CFG$"
				),
				"FIELD"=>array(
					"",
					"HANDLER_NUM",
					""
				)
			),
			"HARDWARE"=>array(
				"FIND"=>array(
					"^",
					"P([0-9]+)",
					"S([0-9]+)",
					"_HW([0-9]+)",
					"($)"
				),
				"FIELD"=>array(
					"",
					"PASS_NUM",
					"TESTER_NUM",
					"HW_NUM",
					""
				)
			),			
			"UTPI"=>array(
				"NAME"=>"UTPI",
				"FIND"=>array(
					"^(ACTIVESITE|ACTIVE)",
					"T([0-9]+)",
					"H([0-9]+)",
					"(_P)?",
					"(_BAR)?",
					"(_BCH)?",
					"(_MAG)?",
					"($)"
				),
				"FIELD"=>array(
					"",
					"",
					"TESTER_NUM",
					"HANDLER_NUM",
					"IS_HV",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_UTPI"
				)
			),
			"TEMP_C"=>array(
				"FIND"=>array(
					"^",
					"P([0-9]+)",
					"_TEMP$"
				),
				#weird stuff for inertial sensor QA, list it 2x so it gets counted as IS but also as a QA
				"FIELD"=>array(
					"",
					"PASS_NUM",
					""
				)
			)
		)
	);
					
			
	foreach($aryRX["ROUTE"] as $strStepType => $aryRXTemp) {
		$aryRX["ROUTE"][$strStepType]["RX"] = "/".implode("",$aryRXTemp["FIND"])."/";
	}
	foreach($aryRX["PARAM"] as $strGroup => $aryRXTemp) {
		$aryRX["PARAM"][$strGroup]["RX"] = "/".implode("",$aryRXTemp["FIND"])."/";
	}
#TQ1CLS1T

	#one-time add delimiters, recursive for n-level
	#$aryRX = RX_DELIMITER_DO($aryRX);

	return $aryRX;
}


function RX_GET() {
	#2023-03-31 RM dropped ending $ for TEST.FIND for -MA, wahtever that means
	#2023-06-15 RM added (_P) for hv test, presumably?
	#2023-12-01 RM added TSIS
	$aryRX = array(
		"ROUTE"=>array(
			"SETUP"=>array(
				"FIND"=>array(
					"^T",
					"(S)?",
					"(AUTOAC)?",
					"(ACTIMING)?",
					"(BUSSACCESS)?",
					"(CLASS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRIFTOILBATH)?",
					"(DRIFT)?",
					"(FLUIDBATH)?",
					"([_]?LAT|LATCH)?",
					"(NOISE)?",
					"(OILBATH|OILBATH_SPL)?",
					"(PREBURN)?",
					"(PRESCRN)?",
					"(POSTBURN)?",
					"(SCANNER)?",
					"(STRIP)?",
					"(VOLTAGE)?",
					"(TCVOS)?",
					"(INERTIAL_ISENSOR)?",
					"(BAROMETER_ISENSOR)?",
					"(BENCH)?",
					"(MAGNETOMETER_ISENSOR)?",
					"(RESPONSE)?",
					"(_CAL)?",
					"_STD",
					"([0-9])+",
					"(_P$)?"
				),
				"FIELD"=>array(
					"",
					"IS_SETUP",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_BTH",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"",
					"SETUP_NUM",
					"IS_HV"
				)
			),
			#2024-05-03 RM dont add a "" for [Q]?, its correct
			"TEST"=>array(
				"FIND"=>array(
					"^T",
					"(F)?",
					"(Q)?",
					"(AUTOAC)?",
					"(ACTIMING)?",
					"(BUSSACCESS)?",
					"(CLASS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRIFTOILBATH)?",
					"(DRIFT)?",
					"(FLUIDBATH)?",
					"([_]LAT|LATCH)?",
					"(NOISE)?",
					"(OILBATH)?",
					"(PREBURN)?",
					"(PREBIOILBATH)?",
					"(PRESCRN)?",
					"(POSTBURN)?",
					"(POSTBIOILBATH)?",
					"(SCANNER)?",
					"(STRIP)?",
					"(VOLTAGE)?",
					"(TCVOS)?",
					"(INERTIAL_ISENSOR)?",
					"(BAROMETER_ISENSOR)?",
					"(BENCH)?",
					"(MAGNETOMETER_ISENSOR)?",
					"(RESPONSE)?",
					"_",
					"[Q]?",
					"([A|H|C|F|M])",
					"([0-9])+",
					"(_P$)?"
				),
				"FIELD"=>array(
					"",
					"IS_TEST",
					"IS_QA",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_BTH",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRBOBT",
					"IS_PRSC",
					"IS_PSB",
					"IS_PSBOBT",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					#2024-05-03 RM dont insert a "" here for [Q]?. this is correct
					"TEMP_CLASS",
					"STEP_TYPE_NUM",
					"IS_HV"
				)
			)
		),
		"PARAM"=>array(
			"SITE_NUM"=>array(
				"FIND"=>array(
					"^",
					"(PRIMARYTSTSITE)?",
					"(A[0-9]+MFGPLANT)?",
					"?"
				),
				"FIELD"=>array(
					"",
					"SITE_NUM",
					"SITE_NUM"
				)
			),
			"HANDLER"=>array(
				"FIND"=>array(
					"^TS",
					"([0-9]+)",
					"(AAC)?",
					"(ACT)?",
					"(BUS)?",
					"(CLS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRFOB)?",
					"(DRF)?",
					"(FLU)?",
					"(LAT)?",
					"(NSE)?",
					"(OBT)?",
					"(PRB)?",
					"(PRSC|PRSCA)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOL)?",
					"(VOS)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"(RES)?",
					"([0-9]+)",
					"H",
					"([0-9]+)",
					"(_P)?",
					"$"
				),
				"FIELD"=>array(
					"",
					#2023-07-06 RM removed IS_SETUP
					"TESTER_NUM",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_BTH",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"SETUP_NUM",
					"HANDLER_NUM",
					"IS_HV"
				)
			),
			"HARDWARE"=>array(
				"FIND"=>array(
					"^T",
					"(S)?",
					"(F)?",
					"(Q)?",
					"([0-9]+)",
					"(AAC)?",
					"(ACT)?",
					"(BUS)?",
					"(CLS)?",
					#driftoilbath must be before DRIFT to support regex capture
					"(DRFOB)?",
					"(DRF)?",
					"(FLU)?",
					"(LAT)?",
					"(NSE)?",
					"(OBT)?",
					"(PRB)?",
					"(PRSC|PRSCA)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOL)?",
					"(VOS)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"(RES)?",
					"([0-9]+)",
					"H",
					"([0-9]+)",
					"(_P)?",
					"E",
					"([0-9]+)",
					"(O)?",
					"([0-9]+)?",
					"$"
				),
				"FIELD"=>array(
					"",
					"IS_SETUP",
					"IS_TEST",
					"IS_QA",
					"TESTER_NUM",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_DRFOBT",
					"IS_DRF",
					"IS_BTH",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"SETUP_NUM",
					"HANDLER_NUM",
					"IS_HV",
					"HW_PRI_NUM",
					"HAS_HW_ALT",
					"HW_ALT_NUM"
				)
			),
			"PKG_TYPE"=>array(
				"FIND"=>array("^(PACKAGETYPE)$"),
				"FIELD"=>array("","PKG_TYPE")
			),
			"PART_TYPE"=>array(
				"FIND"=>array("^(PARTTYPE)$"),
				"FIELD"=>array("","PART_TYPE")
			),
			"LEAD_COUNT"=>array(
				"FIND"=>array("^(LEADCOUNT)$"),
				"FIELD"=>array("","LEAD_COUNT")
			),
			"GENERIC"=>array(
				"FIND"=>array("^(GENERIC)$"),
				"FIELD"=>array("","GENERIC")
			),
			"YLD"=>array(
				"FIND"=>array(
					"^YLD_",
					"(AAC)?",
					"(ACT)?",
					"(CLS)?",
					"(DRF)?",
					"(LAT)?",
					"(PRB)?",
					"(PRS)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOS)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"(RES)?",
					"([0-9]+)",
					"(_P)?",
					"$"
				),
				"FIELD"=>array(
					"",
					"IS_AAC",
					"IS_ACT",
					"IS_CLS",
					"IS_DRF",
					"IS_LAT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOS",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"STEP_TYPE_NUM",
					"IS_HV"
				)
			),
			#ISQ 2X
			"PROGRAM"=>array(
				"FIND"=>array(
					"^T",
					"(S)?",
					"(F)?",
					"(Q)?",
					"([0-9]+)",
					"(AAC)?",
					"(ACT)?",
					"(CLS)?",
					"(DRF)?",
					"(LAT)?",
					"(PRB)?",
					"(PRSC|PRSCA)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOL)?",
					"(VOS)?",
					"(ISN)?",
					"(ISQ)?",
					"(ISQ)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"(RES)?",
					"([0-9]+)",
					"P",
					"([0-9]+)",
					"(_P)?"
				),
				"FIELD"=>array(
					"",
					"IS_SETUP",
					"IS_TEST",
					"IS_QA",
					"TESTER_NUM",
					"IS_AAC",
					"IS_ACT",
					"IS_CLS",
					"IS_DRF",
					"IS_LAT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_ISN",
					"IS_QA",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"SETUP_NUM",
					"HANDLER_NUM",
					"IS_HV"
				)
			),
			#'^\\$(TEST|STR|PRB|PSB)?(ACT)?SYS[0-9]+(_P)?$'
			"TESTER"=>array(
				"FIND"=>array(
					"^",
					"(AAC)?",
					"(ACT)?",
					"(BUS)?",
					"(TEST)?",
					"(LAT)?",
					"(NSE)?",
					"(OILBT)?",
					"(SCN)?",
					"(SET)?",
					"(TCVOS)?",
					"(VOL)?",
					"(ISN)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"(RES)?",
					"SYS",
					"([0-9]+)",
					"(_P)?",
					"$"
				),
				#2023-07-06 changed IS_SETUP to IS_CLASS be careful
				"FIELD"=>array(
					"",
					"IS_AAC",
					"IS_ACT",
					"IS_BUS",
					"IS_CLS",
					"IS_LAT",
					"IS_NSE",
					"IS_OBT",
					"IS_SCN",
					"IS_SET",
					"IS_VOS",
					"IS_VOL",
					"IS_ISN",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"TESTER_NUM",
					"IS_HV"
				)
			),
			"CNTCR_REQ"=>array(
				"NAME"=>"CNTCR_REQ",
				"FIND"=>array(
					"^Q",
					"(CONTACTOR|CNTCTR)",
					"T([0-9]+)",
					"H([0-9]+)",
					"(_P)?"
				),
				"FIELD"=>array(
					"",
					"",
					"TESTER_NUM",
					"HANDLER_NUM",
					"IS_HV"
				)
			),
			#2023-06-20 RM yet another inconsistent dataset, surprise surprise...?
			#TS2CLS2H1AS
			"UTPI_OTHER"=>array(
				"NAME"=>"UTPI",
				"FIND"=>array(
					"^T",
					"(S)?",
					"(F)?",
					"(Q)?",
					"([0-9]+)",
					"(AAC)?",
					"(ACT)?",
					"(CLS)?",
					"(DRF)?",
					"(LAT)?",
					"(NSE)?",
					"(PRB)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"([0-9]+)",
					"H",
					"([0-9]+)",
					"AS$"
				),
				"FIELD"=>array(
					"",
					"IS_SETUP",
					"IS_TEST",
					"IS_QA",
					"TESTER_NUM",
					"IS_AAC",
					"IS_ACT",
					"IS_CLS",
					"IS_DRF",
					"IS_LAT",
					"IS_NSE",
					"IS_PRB",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"SETUP_NUM",
					"HANDLER_NUM"					
				)
			),
			"UTPI"=>array(
				"NAME"=>"UTPI",
				"FIND"=>array(
					"^(ACTIVESITE|ACTIVE)",
					"T([0-9]+)",
					"H([0-9]+)",
					"(_P)?",
					"(_BAR)?",
					"(_BCH)?",
					"(_MAG)?",
					"($)"
				),
				"FIELD"=>array(
					"",
					"",
					"TESTER_NUM",
					"HANDLER_NUM",
					"IS_HV",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_UTPI"
				)
			),
			"TEMP_C"=>array(
				"FIND"=>array(
					"^T",
					"(F)?",
					"(Q)?",
					"[0-9]?",
					"(AAC)?",
					"(ACT)?",
					"(CLS)?",
					"(DRF)?",
					"(FLU)?",
					"(LAT)?",
					"(NSE)?",
					"(PRBOB)?",
					"(PRB)?",
					"(PRSC|PRSCA)?",
					#PSBOB must be before PSB
					"(PSBOB)?",
					"(PSB)?",
					"(SCN)?",
					"(STR)?",
					"(VOL)?",
					"(VOS)?",
					"(ISN)?",
					"(ISQ)?",
					"(ISQ)?",
					"(BAR)?",
					"(BCH)?",
					"(MAG)?",
					"(RES)?",
					"([0-9]+)",
					"T",
					"(_P)?",
					"$"
				),
				#weird stuff for inertial sensor QA, list it 2x so it gets counted as IS but also as a QA
				"FIELD"=>array(
					"",
					"IS_TEST",
					"IS_QA",
					"IS_AAC",
					"IS_ACT",
					"IS_CLS",
					"IS_DRF",
					"IS_BTH",
					"IS_LAT",
					"IS_NSE",
					"IS_PRBOBT",
					"IS_PRB",
					"IS_PRSC",
					"IS_PSBOBT",
					"IS_PSB",
					"IS_SCN",
					"IS_STR",
					"IS_VOL",
					"IS_VOS",
					"IS_ISN",
					"IS_ISN",
					"IS_QA",
					"IS_BAR",
					"IS_BCH",
					"IS_MAG",
					"IS_RSP",
					"STEP_TYPE_NUM",
					"IS_HV"
				)
			)
		)
	);
					
			
	foreach($aryRX["ROUTE"] as $strStepType => $aryRXTemp) {
		$aryRX["ROUTE"][$strStepType]["RX"] = "/".implode("",$aryRXTemp["FIND"])."/";
	}
	foreach($aryRX["PARAM"] as $strGroup => $aryRXTemp) {
		$aryRX["PARAM"][$strGroup]["RX"] = "/".implode("",$aryRXTemp["FIND"])."/";
	}
#TQ1CLS1T

	#one-time add delimiters, recursive for n-level
	#$aryRX = RX_DELIMITER_DO($aryRX);

	return $aryRX;
}


function ROUTE_STEP_PARSE($aryRX,$strStep,$bolDebug=false) {
	$aryStepData = array();

	foreach(array_keys($aryRX["ROUTE"]) as $strStepType) {
		$strRX = $aryRX["ROUTE"][$strStepType]["RX"];

		#$strRX = "/".implode("",$aryRX["ROUTE"][$strStepType]["FIND"])."/";
		preg_match_all($strRX,$strStep,$aryMatch);

		#items in 0 indicates complete match
		if(isset($aryMatch[0]) && count($aryMatch[0]) > 0) {
			#echo $strRX."\n";
			#Print_R($aryMatch);

			foreach($aryRX["ROUTE"][$strStepType]["FIELD"] as $intCtr => $strField) {
				if($strField != "") {
					foreach($aryMatch[$intCtr] as $strValue) {
						if($strValue != "") {
							$aryStepData[$strField] = $strValue;
						}
					}
				}
			}
		}
		if(count($aryStepData) > 0) {
			break;
		}
	}

	#2024-04-29 RM
	$aryThis = $aryStepData;
	$aryThat = array();
	foreach($aryThis as $strField => $strValue) {
		if(strpos($strField,"^") !== false) {
			$aryTemp = explode("^",$strField);
			foreach($aryTemp as $strTemp) {
				$aryThat[$strTemp] = $strValue;
			}
		}else{
			$aryThat[$strField] = $strValue;
		}
	}
	$aryStepData = $aryThat;


	#$strType = TYPE_GET($aryStepData,"ROUTE");
	#echo $strStep." / ".$strType."\n";
	#added LAT
	$aryTry = array("IS_TEST","IS_QA","IS_LAT");
	foreach($aryTry as $strTry) {
		if(isset($aryStepData[$strTry])) {
			$aryStepData["CONSUMES_CAPACITY"] = 1;
			break;
		}
	}
	if(!isset($aryStepData["CONSUMES_CAPACITY"])) {
		$aryStepData["CONSUMES_CAPACITY"] = 0;
	}

	return $aryStepData;
}


function RES_CAP_DO_V4($iConRLM,$aryResCap,$bolDebug=false) {
	echo __FUNCTION__."\n";
	$decStartTime = time()+microtime();


	#2023-09-21 RM
	echo chr(9)."TESTER+HANDLER...";


	$strSQL = "
		SELECT DISTINCT
		PRR.RES_AREA
		FROM BODS_JDA_ADI.RES_ALTERNATES RA
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES BOR ON RA.RES_SET_ID = BOR.RES_SET_ID AND RES_TYPE IN ('TESTER','HANDLER')
		ORDER BY RES_AREA;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryRA = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryRA[] = $LineMain["RES_AREA"];
	}

	$aryDone = array();
	#2023-07-17 RM at KB request report capacity even if never used by a part
	foreach(array_keys($aryResCap) as $strType) {
		if($strType == "TESTER" || $strType == "HANDLER") {
			foreach(array_keys($aryResCap[$strType]) as $strName) {
				foreach(array_keys($aryResCap[$strType][$strName]) as $strSN) {
					foreach(array_keys($aryResCap[$strType][$strName][$strSN]) as $strRA) {
						foreach($aryResCap[$strType][$strName][$strSN][$strRA] as $aryResCapTemp) {
							$aryDone[$strType][$strName][$strSN][] = $strRA;

							$aryTemp = array(
								"SITE_NUM"=>$strSN,
								"RES_AREA"=>$strRA,
								"RES_TYPE"=>$strType,
								"RES_NM"=>$strName,
								"EFF_START_DT"=>$aryResCapTemp["EFF_START_DT"],
								"EFF_END_DT"=>$aryResCapTemp["EFF_END_DT"],
								"RES_CNT"=>round($aryResCapTemp["RES_CNT"],2),
								"EFF_RES_CNT"=>round($aryResCapTemp["EFF_RES_CNT"],2)
							);

							RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
						}
					}
				}
			}
		}
	}


	$strSQL = "
		SELECT DISTINCT
		TESTER.RES_TYPE,
		TESTER.RES_NM,
		PRR.SITE_NUM,
		PRR.RES_AREA
		FROM BODS_JDA_ADI.RES_ALTERNATES RA 
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES TESTER ON RA.RES_SET_ID = TESTER.RES_SET_ID AND TESTER.RES_TYPE = 'TESTER'
		UNION
		SELECT DISTINCT
		HANDLER.RES_TYPE,
		HANDLER.RES_NM,
		PRR.SITE_NUM,
		PRR.RES_AREA
		FROM BODS_JDA_ADI.RES_ALTERNATES RA
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES HANDLER ON RA.RES_SET_ID = HANDLER.RES_SET_ID AND HANDLER.RES_TYPE = 'HANDLER'";		
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strType = $LineMain["RES_TYPE"];
		$strName = $LineMain["RES_NM"];
		$strSN = $LineMain["SITE_NUM"];
		$strRA = $LineMain["RES_AREA"];
		
		switch (true) {
			case isset($aryDone[$strType][$strName][$strSN]) && in_array($strRA,$aryDone[$strType][$strName][$strSN]):
				#already done, skip it
				break;
			case isset($aryResCap[$strType][$strName][$strSN][$strRA]):
				foreach($aryResCap[$strType][$strName][$strSN][$strRA] as $aryResCapTemp) {
					$aryTemp = array(
						"SITE_NUM"=>$strSN,
						"RES_AREA"=>$strRA,
						"RES_TYPE"=>$strType,
						"RES_NM"=>$strName,
						"EFF_START_DT"=>$aryResCapTemp["EFF_START_DT"],
						"EFF_END_DT"=>$aryResCapTemp["EFF_END_DT"],
						"RES_CNT"=>round($aryResCapTemp["RES_CNT"],2),
						"EFF_RES_CNT"=>round($aryResCapTemp["EFF_RES_CNT"],2)
					);

					RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
				}

				break;
			case !isset($aryResCap[$strType][$strName][$strSN][$strRA]):
				$aryTemp = array(
					"SITE_NUM"=>$strSN,
					"RES_AREA"=>$strRA,
					"RES_TYPE"=>$strType,
					"RES_NM"=>$strName,
					"RES_CNT"=>0,
					"EFF_RES_CNT"=>0
				);

				RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);

				break;
		}
	}


	$strSQL = "
		UPDATE
		BODS_JDA_ADI.RESOURCE_CAPACITY
		SET RES_CNT = 999,
		EFF_RES_CNT = 999
		WHERE RES_NM = 'HANDTEST';";
	ExecuteIQuery($strSQL,$iConRLM);


	echo "DONE.\n";

	#$bolDebug = true;
	

	#2023-12-14 RM added spare calculations for specific hardware types, for non-LT products
	$strSQL = "
		SELECT DISTINCT
		IDEN.CA_VALUE
		FROM ADI_ESLDATA.CUSTOM_ACTION CA
		INNER JOIN ADI_ESLDATA.CUSTOM_ACTION_IDEN IDEN ON CA.CA_ID = IDEN.CA_ID
		WHERE CA.CA_NAME = 'NON_LT_SPARE_ENABLED_HWTYPE'
		ORDER BY CA_VALUE;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryADISpare = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryADISpare[] = $LineMain["CA_VALUE"];
	}

	#die();
	echo chr(9)."HARDWARE.\n";
	#HARDWARE
	$strSQL = "
		SELECT DISTINCT
		PRR.SITE_NUM,
		PRR.RES_AREA,
		HW.HW_TYPE,
		HW.HW_NM,
		IF(CAT_41.ATTR_VAL_CHAR IN ('LT','LTI','LTCL'),1,0) IS_LT
		FROM BODS_JDA_ADI.RES_ALTERNATES RA
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		INNER JOIN BODS_JDA_ADI.RESOURCE_ATTR CAT_41 ON RA.ATTR_SET_ID = CAT_41.ATTR_SET_ID AND CAT_41.ATTR_NM = 'CAT_41'
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES HW_SET_ID ON RA.RES_SET_ID = HW_SET_ID.RES_SET_ID AND HW_SET_ID.RES_TYPE = 'HARDWARE_SET'
		INNER JOIN BODS_JDA_ADI.HARDWARE_SET HW ON HW_SET_ID.RES_NM * 1 = HW.HW_SET_ID
		WHERE PRR.SITE_NUM NOT IN ('ADTH')
		UNION
		SELECT DISTINCT
		PRR.SITE_NUM,
		PRR.RES_AREA,
		HW.HW_TYPE,
		HW.HW_NM,
		IF(CAT_41.ATTR_VAL_CHAR IN ('LT','LTI','LTCL'),1,0) IS_LT
		FROM BODS_JDA_ADI.RES_ALTERNATES RA 
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID
		INNER JOIN BODS_JDA_ADI.RESOURCE_ATTR CAT_41 ON RA.ATTR_SET_ID = CAT_41.ATTR_SET_ID AND CAT_41.ATTR_NM = 'CAT_41'
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES HW_SET_ID ON RA.RES_SET_ID = HW_SET_ID.RES_SET_ID AND HW_SET_ID.RES_TYPE = 'HARDWARE_SET'
		INNER JOIN BODS_JDA_ADI.HARDWARE_ALTERNATES HW ON HW_SET_ID.RES_NM * 1 = HW.HW_SET_ID
		WHERE PRR.SITE_NUM NOT IN ('ADTH')
		ORDER BY SITE_NUM, RES_AREA, HW_TYPE, HW_NM;";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMain = array();
	$aryMap = array(
		"NAME_LT_SN_RA"=>array(),
		"NAME_TYPE"=>array()
	);


	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strSN = $LineMain["SITE_NUM"];
		$strRA = $LineMain["RES_AREA"];
		$strType = $LineMain["HW_TYPE"];
		$strName = $LineMain["HW_NM"];
		$intLT = $LineMain["IS_LT"];

		if($intLT == 1) {
			$aryMap["NAME_LT_SN_RA"][$strName]["LT"][$strSN][] = $strRA;
		}else{
			$aryMap["NAME_LT_SN_RA"][$strName]["NON-LT"][$strSN][] = $strRA;
		}

		$aryMap["NAME_TYPE"][$strName] = $LineMain["HW_TYPE"];
	}

	#2023-10-20 RM calulate every week from current plus to 52.  store incrementally from current week (0+)
	$strSQL = "CALL shared.CHECK_DMCLS_MEMORY();";
	ExecuteIQuery($strSQL,$iConRLM);


	$strSQL = "SELECT DATE_FORMAT(NOW(),'%w') DOW_NUM;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$LineMain = mysqli_fetch_assoc($RSMain);
	$intDOW = $LineMain["DOW_NUM"];


	if($intDOW == 0) {
		$strSQLFilter = "
			SELECT DISTINCT
			MFG_YEAR*100+WEEK_NUMBER FYWW
			FROM shared.DMCLS_MEMORY
			WHERE MFG_DATE >= DATE_FORMAT(NOW(),'%Y-%m-%d')
			LIMIT 0,52";
	}else{
		$strSQLFilter = "
			SELECT DISTINCT
			MFG_YEAR*100+WEEK_NUMBER FYWW
			FROM shared.DMCLS_MEMORY
			WHERE MFG_DATE >= DATE_FORMAT(NOW(),'%Y-%m-%d')
			LIMIT 1,52";
	}

	$strSQL = "
		SELECT
		FYWW,
		MIN(MFG_DATE) MFG_DATE
		FROM shared.DMCLS_MEMORY DMCLS
		INNER JOIN 
		(".$strSQLFilter.") DMCLS_FILTERED ON DMCLS.MFG_YEAR*100+WEEK_NUMBER = DMCLS_FILTERED.FYWW
		GROUP BY FYWW
		ORDER BY FYWW;";	
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryFYWW = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$aryFYWW[] = array("FYWW"=>$LineMain["FYWW"],"DATE"=>$LineMain["MFG_DATE"]);
	}


	$aryLT = array();
	$aryDone = array();
	$aryTry = array("LT","NON-LT");

	foreach(array_keys($aryMap["NAME_LT_SN_RA"]) as $strName) {
		$strRT = $aryMap["NAME_TYPE"][$strName];
		

		$strCat = null;
		if(isset($aryResCap["HARDWARE"][$strName]["CATEGORY"])) {
			$strCat = $aryResCap["HARDWARE"][$strName]["CATEGORY"];
		}

		foreach($aryTry as $strTry) {
			if(isset($aryMap["NAME_LT_SN_RA"][$strName][$strTry])) {
				foreach(array_keys($aryMap["NAME_LT_SN_RA"][$strName][$strTry]) as $strSN) {
					#2023-10-20 RM new structure of $aryResCap (CL = current_location)
					#$aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $intCount;

					$intRC = 0;
					$intERC = 0;

					if(isset($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN])) {
						$aryIden = array(
							"HW_NM"=>array($strName),
							"HW_TYPE"=>array($strRT),
							"SITE_NUM"=>array($strSN)
						);
						$aryInfo = array();

						foreach(array_keys($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN]) as $strCL) {
							foreach($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN][$strCL] as $strStatus => $intCount) {
								switch (true) {
									case $strTry == "LT" && ($strCL == "TGREPAIR" || $strCL == "TGBREPR"):
										#2023-10-20 RM dont count these two as available YET.  they come in later
										#2023-10-23 RM count these as spare only (for deducting spares required)

										$aryInfo["CL+STATUS AS SPARE"][] = $strCL." + ".$strStatus;
										$intRC += $intCount;

										break;
									default:
										#2023-10-23 RM everything else: LT with current location other than the above two, any non-LT hardware
										$aryInfo["CL+STATUS"][] = $strCL." + ".$strStatus;

										$intRC += $intCount;
										$intERC += $intCount;

										break;
								}
							}
						}

						$aryInfo["RES_CNT"] = array($intRC);

						switch (true) {
							case $strTry == "LT":
								#calculate spares only for LT
								#2023-10-23 RM im now calculating spares from RES_CNT, not EFF_RES_CNT. as RES_CNT represents the total pool. this may be right but maybe wont agree with business users?
								$intSpareReq = ceil($intRC/5);
								$aryInfo["LT SPARES NEEDED"] = array($intSpareReq);
								
								#2023-10-23 RM
								#subtract spares from repair first, then from eff_res_cnt
								
								$intSparePool = $intRC - $intERC;
								if($intSparePool > 0) {
									$aryInfo["LT REPAIR"] = array($intSparePool);
								}

								if($intSparePool > $intSpareReq) {
									$aryInfo["LT DEDUCT SPARE FROM REPAIR"] = array($intSpareReq);
									$intSpareReq = 0;
								}else{
									$aryInfo["LT DEDUCT SPARE FROM REPAIR"] = array($intSparePool);
									$intSpareReq -= $intSparePool;
								}

								$aryInfo["LT SPARES STILL NEEDED"] = array($intSpareReq);

								if($intSpareReq > 0) {
									$intRem = $intERC - $intSpareReq;

									if($intRem > 0) {
										$intERC = $intRem;
									}else{
										$intERC = 1;
									}
								}
								#otherwise dont touch EFF_RES_CNT

								$aryInfo["RES_CNT"] = array($intRC);
								$aryInfo["EFF_RES_CNT"] = array($intERC);

								$aryX = array(
									"FUNCTION"=>__FUNCTION__,
									"MESSAGE"=>"LT DEDUCT SPARES",
									"IDEN"=>$aryIden,
									"INFO"=>$aryInfo
								);
								$intXID = EXCEPTION_ID_GET($iConRLM,$aryX,$bolDebug);
								EXCEPTION_SESSION_DO($iConRLM,$intXID,$bolDebug);

								if($bolDebug === true) {
									echo "LT DEDUCT SPARE/S: ".$strName." / ".$strRT." / ".$strSN." / ".$intRC." / ".$intERC."\n";
								}

								#2023-10-20 RM if LT, store these values for use later
								$aryLT[$strName][$strSN] = array("RES_CNT"=>$intRC,"EFF_RES_CNT"=>$intERC);
								
								break;
							case in_array($strRT,$aryADISpare) && $strCat == "COMPLEX":
								#2024-01-09 RM i completely read this wrong, wow!
								#original request from KB:
								#Spare = total available inventory – (roundup( total available inventory / 1.5))
								#Eff_res_cnt = total available inv – spare
								#i completely mis-interpreted this

								$intSpareReq = $intRC - ceil($intRC/1.5);
								$aryInfo["COMPLEX SPARES NEEDED"] = array($intSpareReq);

								switch (true) {
									case $intRC == 0:
										#do nothing
										break;
									case $intRC > $intSpareReq:
										#if removing spares leaves at least 1, then remove spares
										$intERC = $intRC - $intSpareReq;
										break;
									default:
										#leave 1
										$intERC = 1;
										break;
								}

								$aryInfo["RES_CNT"] = array($intRC);
								$aryInfo["EFF_RES_CNT"] = array($intERC);

								$aryX = array(
									"FUNCTION"=>__FUNCTION__,
									"MESSAGE"=>"COMPLEX DEDUCT SPARES",
									"IDEN"=>$aryIden,
									"INFO"=>$aryInfo
								);
								$intXID = EXCEPTION_ID_GET($iConRLM,$aryX,$bolDebug);
								EXCEPTION_SESSION_DO($iConRLM,$intXID,$bolDebug);

								if($bolDebug === true) {
									echo "COMPLEX DEDUCT SPARE/S: ".$strName." / ".$strRT." / ".$strSN." / ".$intRC." / ".$intERC."\n";
								}

								break;
							case in_array($strRT,$aryADISpare):
								$intSpareReq = ceil($intRC/5);
								$aryInfo["SIMPLE SPARES NEEDED"] = array($intSpareReq);

								switch (true) {
									case $intRC == 0:
										#do nothing
										break;
									case $intRC > $intSpareReq:
										#if removing spares leaves at least 1, then remove spares
										$intERC = $intRC - $intSpareReq;
										break;
									default:
										#leave 1
										$intERC = 1;
										break;
								}

								$aryInfo["RES_CNT"] = array($intRC);
								$aryInfo["EFF_RES_CNT"] = array($intERC);

								$aryX = array(
									"FUNCTION"=>__FUNCTION__,
									"MESSAGE"=>"SIMPLE DEDUCT SPARES",
									"IDEN"=>$aryIden,
									"INFO"=>$aryInfo
								);
								$intXID = EXCEPTION_ID_GET($iConRLM,$aryX,$bolDebug);
								EXCEPTION_SESSION_DO($iConRLM,$intXID,$bolDebug);

								if($bolDebug === true) {
									echo "SIMPLE DEDUCT SPARE/S: ".$strName." / ".$strRT." / ".$strSN." / ".$intRC." / ".$intERC."\n";
								}

								break;
							default:
								break;
						}


						#res_cap does not have res_area. peanut-butter whatever quantities you have across res_area
						foreach($aryMap["NAME_LT_SN_RA"][$strName][$strTry][$strSN] as $strRA) {
							$aryTemp = array(
								"SITE_NUM"=>$strSN,
								"RES_TYPE"=>$strRT,
								"RES_NM"=>$strName,
								"RES_AREA"=>$strRA,
								"RES_CNT"=>$intRC,
								"EFF_RES_CNT"=>$intERC
							);

							RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
						}
						$aryDone[$strName][$strSN] = 1;
						

						#if LT, run through one more time to pick up the repair stuff to be released +2 weeks
						#2023-10-20 RM new structure of $aryResCap (CL = current_location)
						#$aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN][$strCL][$strStatus] = $intCount;

						if(isset($aryLT[$strName][$strSN])) {
							$intAdd = 0;
							foreach(array_keys($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN]) as $strCL) {
								foreach($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN][$strCL] as $strStatus => $intCount) {
									switch (true) {
										case $strTry == "LT" && ($strCL == "TGREPAIR" || $strCL == "TGBREPR"):
											$intAdd += $intCount;
											break;
									}
								}
							}

							$aryIden = array(
								"HW_NM"=>array($strName),
								"HW_TYPE"=>array($strRT),
								"SITE_NUM"=>array($strSN)
							);
							$aryInfo = array();

							$aryLTTemp = $aryLT[$strName][$strSN];
							$intRC = $aryLTTemp["RES_CNT"];
							$intERCThat = $aryLTTemp["EFF_RES_CNT"];

							$intSpareReq = ceil($intRC/5);
							$intERCThis = $intRC - $intSpareReq;

							if($intERCThis <= 0) {
								$intERCThis = 1;
							}

							#echo $intERCThis." / ".$intERCThat."\n";

							if($intERCThis != $intERCThat) {
								#2023-10-20 RM 2 is the sunday 2 weeks from now. if today is a sunday it is the sunday after next.  if today is not sunday then +1 week
								$strDate = $aryFYWW[2]["DATE"];
								$aryInfo["RELEASE_DATE"] = array($strDate);
								
								#2023-10-23 RM check for change. if none then skip changes
								if($bolDebug === true) {
									echo "LT RELEASE REPAIR/S: ".$strDate." / ".$strName." / ".$strRT." / ".$strSN." / +".$intAdd."\n";
								}

								$strSQL = "
									UPDATE
									BODS_JDA_ADI.RESOURCE_CAPACITY
									SET EFF_END_DT = '".mysqli_real_escape_string($iConRLM,$strDate)."'
									WHERE SITE_NUM = '".mysqli_real_escape_string($iConRLM,$strSN)."'
									AND RES_TYPE = '".mysqli_real_escape_string($iConRLM,$strRT)."'
									AND RES_NM = '".mysqli_real_escape_string($iConRLM,$strName)."';";
								ExecuteIQuery($strSQL,$iConRLM);

								$aryInfo["RES_CNT"] = array($intRC);
								$aryInfo["RELEASED FROM REPAIR"] = array($intAdd);
								$aryInfo["SPARE/S NEEDED"] = array($intSpareReq);
								$aryInfo["EFF_RES_CNT"] = array($intERCThis);
							
								$aryX = array(
									"FUNCTION"=>__FUNCTION__,
									"MESSAGE"=>"LT RELEASE OF TGREPAIR/TGBREPR",
									"IDEN"=>$aryIden,
									"INFO"=>$aryInfo
								);
								$intXID = EXCEPTION_ID_GET($iConRLM,$aryX,$bolDebug);
								EXCEPTION_SESSION_DO($iConRLM,$intXID,$bolDebug);

								foreach($aryMap["NAME_LT_SN_RA"][$strName][$strTry][$strSN] as $strRA) {
									$aryTemp = array(
										"SITE_NUM"=>$strSN,
										"RES_TYPE"=>$strRT,
										"RES_NM"=>$strName,
										"RES_AREA"=>$strRA,
										"EFF_START_DT"=>$strDate,
										"RES_CNT"=>$intRC,
										"EFF_RES_CNT"=>$intERCThis
									);

									RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
								}

							}else{
								if($bolDebug === true) {
									echo "NO NET CHANGE IN EFF_RES_CNT\n";
								}
							}
						}
					}else{
						#2023-10-23 RM nevermind
						#2023-10-23 RM oops force to max?
						#if($strName == "PERFBD_FAKE") {
						#	$intRC = 998;
						#	$intERC = 998;
						#}

						#ensure coverage (0 for stuff without cap)
						foreach($aryMap["NAME_LT_SN_RA"][$strName][$strTry][$strSN] as $strRA) {
							$aryTemp = array(
								"SITE_NUM"=>$strSN,
								"RES_TYPE"=>$strRT,
								"RES_NM"=>$strName,
								"RES_AREA"=>$strRA,
								"RES_CNT"=>$intRC,
								"EFF_RES_CNT"=>$intERC
							);

							RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
						}
					}

					$aryDone[$strName][$strSN] = 1;
				}

				#2023-10-20 RM stop after first found (LT is checked first. so if LT is found, dont process as non-LT)
				break;
			}
		}
	}
	
	#everything not already written, write (from rescap without tie to parts)
	#2023-10-20 RM modified for new $aryResCap structure
	#foreach($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN][$strCL] as $strStatus => $intCount) {
	$arySQL = array();

	if(isset($aryResCap["HARDWARE"])) {
		foreach(array_keys($aryResCap["HARDWARE"]) as $strName) {
			if(isset($aryResCap["HARDWARE"][$strName]["RES_TYPE"])) {
				$strRT = $aryResCap["HARDWARE"][$strName]["RES_TYPE"];

				foreach(array_keys($aryResCap["HARDWARE"][$strName]["SITE_NUM"]) as $strSN) {
					if(isset($aryDone[$strName][$strSN])) {
						#we already wrote it, so dont write it again
					}else{
						$intAvail = 0;
						foreach(array_keys($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN]) as $strCL) {
							foreach($aryResCap["HARDWARE"][$strName]["SITE_NUM"][$strSN][$strCL] as $strStatus => $intCount) {
								$intAvail += $intCount;
							}
						}

						if($bolDebug === true) {
							echo "UNUSED HW ADD: ".$strSN." / ".$strRA." / ".$strRT." / ".$strName." (".round($intAvail,2).")\n";
						}

						foreach($aryRA as $strRA) {
							$aryTemp = array(
								"SITE_NUM"=>$strSN,
								"RES_AREA"=>$strRA,
								"RES_TYPE"=>$strRT,
								"RES_NM"=>$strName,
								"RES_CNT"=>$intAvail,
								"EFF_RES_CNT"=>$intAvail
							);

							RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
						}
					}
				}				
			}
		}
	}
	
//For TH
$strSQL = "
		SELECT DISTINCT
		PRR.SITE_NUM,
		PRR.RES_AREA,		
		HW.HW_TYPE,
		HW.HW_NM		
		FROM BODS_JDA_ADI.RES_ALTERNATES RA
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID		
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES HW_SET_ID ON RA.RES_SET_ID = HW_SET_ID.RES_SET_ID AND HW_SET_ID.RES_TYPE = 'HARDWARE_SET'
		INNER JOIN BODS_JDA_ADI.HARDWARE_SET HW ON HW_SET_ID.RES_NM * 1 = HW.HW_SET_ID
		WHERE PRR.SITE_NUM = 'ADTH'
		UNION
		SELECT DISTINCT
		PRR.SITE_NUM,
		PRR.RES_AREA,		
		HW.HW_TYPE,
		HW.HW_NM		
		FROM BODS_JDA_ADI.RES_ALTERNATES RA 
		INNER JOIN BODS_JDA_ADI.PROD_RES_RELATION PRR ON RA.PRS_ID = PRR.PRS_ID		
		INNER JOIN BODS_JDA_ADI.BILL_OF_RESOURCES HW_SET_ID ON RA.RES_SET_ID = HW_SET_ID.RES_SET_ID AND HW_SET_ID.RES_TYPE = 'HARDWARE_SET'
		INNER JOIN BODS_JDA_ADI.HARDWARE_ALTERNATES HW ON HW_SET_ID.RES_NM * 1 = HW.HW_SET_ID
		WHERE PRR.SITE_NUM = 'ADTH'
		ORDER BY SITE_NUM, HW_TYPE, HW_NM;";
	if($bolDebug === true) {
		echo $strSQL."\n";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryMain = array();
	$aryMap = array(
		"NAME_SN_RA"=>array(),
		"NAME_TYPE"=>array()
	);


	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$strSN = $LineMain["SITE_NUM"];
		$strRA = $LineMain["RES_AREA"];
		$strType = $LineMain["HW_TYPE"];
		$strName = $LineMain["HW_NM"];
		
		$aryMap["NAME_SN_RA"][$strName][$strSN][] = $strRA;
		

		$aryMap["NAME_TYPE"][$strName] = $LineMain["HW_TYPE"];
	}



	#print_r($aryMap["NAME_TYPE"]["LT3652/LT3652HVEMSE/UMAX/12/AL L-60642"]);
	#Print_r($aryResCap["HWC"]["LT3652/LT3652HVEMSE/UMAX/12/AL L-60642"]);
	#die();


	$arySQL = array();
	foreach(array_keys($aryMap["NAME_TYPE"]) as $strName) {
		#echo $strName."\n";

		if(isset($aryResCap["HWC"][$strName])) {
			$strRT = $aryMap["NAME_TYPE"][$strName];
			if(isset($aryResCap["HWC"][$strName]["RES_TYPE"])) {
				$strRT = $aryResCap["HWC"][$strName]["RES_TYPE"];
			}
			#echo $strRT."\n";

			#Print_R($aryResCap["HWC"][$strName]["SITE_NUM"]);

			foreach(array_keys($aryResCap["HWC"][$strName]["SITE_NUM"]) as $strSN) {
				#echo $strSN."\n";
				$intAvail = 0;
				foreach(array_keys($aryResCap["HWC"][$strName]["SITE_NUM"][$strSN]) as $strCL) {
					#echo $strCL."\n";

					foreach($aryResCap["HWC"][$strName]["SITE_NUM"][$strSN][$strCL] as $strStatus => $intCount) {
						#echo $strStatus." / ".$intCount."\n";

						$intAvail += $intCount;
					}
				}

				#if($bolDebug === true) {
				#echo $strName." / ".$strRT." / ".$strSN." / ".$intAvail."\n";
				#Print_R($aryMap["NAME_SN_RA"][$strName][$strSN]);
				#}

				$intRC = $intAvail;
				$intSpares = ceil($intRC/6);
				$intERC = $intRC - $intSpares;
				
				if($intRC > 0 && $intERC <= 0) {
					$intERC = 1;
				}

				foreach($aryMap["NAME_SN_RA"][$strName][$strSN] as $strRA) {

					$aryTemp = array(
						"SITE_NUM"=>$strSN,
						"RES_AREA"=>$strRA,
						"RES_TYPE"=>$strRT,
						"RES_NM"=>$strName,
						"RES_CNT"=>$intRC,
						"EFF_RES_CNT"=>$intERC
					);

					#echo $strRA."\n";
					#Print_R($aryTemp);

					RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
				}				
			}
		}else{
			foreach(array_keys($aryMap["NAME_SN_RA"][$strName]) as $strSN) {
				foreach(array_keys($aryMap["NAME_SN_RA"][$strName][$strSN]) as $strRA) {
		
					$aryTemp = array(
						"SITE_NUM"=>$strSN,
						"RES_AREA"=>$strRA,
						"RES_TYPE"=>$strRT,
						"RES_NM"=>$strName,
						"RES_CNT"=>0,
						"EFF_RES_CNT"=>0
					);

						#print_r($aryTemp);
						#Die();
					RESOURCE_CAPACITY_PUT($iConRLM,$aryTemp,$bolDebug);
					
				}	
			}
		}
	}

	$decEndTime = time()+microtime();
	echo "DONE. (".number_format($decEndTime-$decStartTime,1)." s.).\n";
}


function SRS_GET($iConSUS,$aryRCID,$bolQuick,$bolDebug=false) {
	$aryMap["SRSID_RCID_FN_SEQ"] = array();
	$arySRSM = array();

	$aryTemp = array();
	while ($intRCID = array_pop($aryRCID)) {
		$aryTemp[] = mysqli_real_escape_string($iConSUS,$intRCID);

		if(count($aryTemp) >= 100 || count($aryRCID) == 0) {
			$strSQL = "
				SELECT
				RC_ID,
				FLOW_NAME,
				STEP_SEQ,
				STEP_NAME,
				SRS_ID
				FROM SUS_ROUTE_ADI.SRS_MASTER
				WHERE SRS_STATUS = 'LINKED'
				AND STEP_NAME REGEXP '^((B|BI)?|(BI|FB|LAT|TM|TS|REL)?(FT|WS)[0-9]?|(FB|LAT|N|R|TM|T|REL)?Q[A|M][0-9]?)$'
				AND RC_ID IN (".implode(",",$aryTemp).")
				ORDER BY IF(FLOW_NAME LIKE 'PRIMARY%',0,1), FLOW_NAME, STEP_SEQ;";
			$RSMain = ExecuteIQuery($strSQL,$iConSUS);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intRCID = $LineMain["RC_ID"];
				$strFN = $LineMain["FLOW_NAME"];
				$intSeq = $LineMain["STEP_SEQ"];
				$intSRSID = $LineMain["SRS_ID"];

				$arySRSM[$intRCID][$strFN][$intSeq] = array(
					"SRS_ID"=>$intSRSID,
					"STEP_NAME"=>$LineMain["STEP_NAME"]
				);

				$aryMap["SRSID_RCID_FN_SEQ"][$intSRSID][$intRCID][$strFN][] = $intSeq;
			}

			$aryTemp = array();
		}		
	}


	if($bolQuick === true) {
		return array("SRSM"=>$arySRSM);
	}

	$arySRSS = array();
	$aryMap["EGID_SRSID_SRSSID"] = array();
	$aryMap["HWSID_SRSID_SRSSID"] = array();
	$aryMap["DFGIID_SRSID_SRSSID"] = array();

	$arySRSID = array_keys($aryMap["SRSID_RCID_FN_SEQ"]);
	$aryTemp = array();

	while ($intSRSID = array_pop($arySRSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConSUS,$intSRSID);

		if(count($aryTemp) >= 100 || count($arySRSID) == 0) {
			$strSQL = "
				SELECT
				SRS_SETUP_ID,
				SRS_ID,
				TEMPERATURE_C,
				REF_DOC_ID,
				REF_STEP_ID,
				REF_SETUP_ID,
				EQUIP_GROUP_ID,
				HWS_ID,
				UTPI,
				TTPI,
				INDEX_TIME
				FROM SUS_ROUTE_ADI.SRS_SETUP
				WHERE SRS_ID IN (".implode(",",$aryTemp).");";
			$RSMain = ExecuteIQuery($strSQL,$iConSUS);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intSRSID = $LineMain["SRS_ID"];
				$intSRSSID = $LineMain["SRS_SETUP_ID"];
				$intDFGIID = $LineMain["REF_DOC_ID"];
				$intEGID = $LineMain["EQUIP_GROUP_ID"];
				$intHWSID = $LineMain["HWS_ID"];

				$aryTemp = $LineMain;
				$aryTemp["STEPINST"] = array();
				unset($aryTemp["SRS_ID"]);
				unset($aryTemp["SRS_SETUP_ID"]);

				$arySRSS[$intSRSID][$intSRSSID] = $aryTemp;

				if(!is_null($intEGID)) {
					$aryMap["EGID_SRSID_SRSSID"][$intEGID][$intSRSID][] = $intSRSSID;
				}
				if(!is_null($intHWSID)) {
					$aryMap["HWSID_SRSID_SRSSID"][$intHWSID][$intSRSID][] = $intSRSSID;
				}

				$aryMap["DFGIID_SRSID_SRSSID"][$intDFGIID][$intSRSID][] = $intSRSSID;
			}

			$aryTemp = array();
		}
	}


	$aryKeep = array("PACKAGE","LEADCOUNT","TESTSITE","GDPW");
	$arySUS = array();
	
	$aryDFGIID = array_keys($aryMap["DFGIID_SRSID_SRSSID"]);
	$aryTemp = array();

	while ($intDFGIID = array_pop($aryDFGIID)) {
		$aryTemp[] = mysqli_real_escape_string($iConSUS,$intDFGIID);

		if(count($aryTemp) >= 100 || count($aryDFGIID) == 0) {
			$strSQL = "
				SELECT
				GENPARTINFO.DOCUMENTFGIID DOCUMENTFGIID,
				IF(GENPARTNAME.LONGVALUE != '',GENPARTNAME.LONGVALUE,GENPARTNAME.SHORTVALUE) GENPARTNAME,
				IF(GENPARTVALUE.LONGVALUE != '',GENPARTVALUE.LONGVALUE,GENPARTVALUE.SHORTVALUE) GENPARTVALUE
				FROM FTSSE.FIELD_GROUP_INSTANCE GENPARTINFO 
				INNER JOIN FTSSE.FIELD_INSTANCE GENPARTNAME ON GENPARTINFO.FGIID = GENPARTNAME.CONTAININGFGIID AND GENPARTNAME.FID = 'GENPARTNAME'
				INNER JOIN FTSSE.FIELD_INSTANCE GENPARTVALUE ON GENPARTINFO.FGIID = GENPARTVALUE.CONTAININGFGIID AND GENPARTVALUE.FID = 'GENPARTVALUE'
				WHERE GENPARTINFO.FGID = 'GENPARTINFO'
				AND GENPARTINFO.CONTAININGFGIID IN (".implode(",",$aryTemp).")
				ORDER BY DOCUMENTFGIID,FGISEQUENCENUM;";
			$RSMain = ExecuteIQuery($strSQL,$iConSUS);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intDFGIID = $LineMain["DOCUMENTFGIID"];
				$strName = strtoupper($LineMain["GENPARTNAME"]);

				if(in_array($strName,$aryKeep)) {
					$arySUS[$intDFGIID]["GENPARTINFO"][$strName] = $LineMain["GENPARTVALUE"];
				}
			}

			$strSQL = "
				SELECT
				PARTINFO.DOCUMENTFGIID DOCUMENTFGIID,
				IF(GENERIC.LONGVALUE != '',GENERIC.LONGVALUE,GENERIC.SHORTVALUE) GENERIC
				FROM FTSSE.FIELD_GROUP_INSTANCE PARTINFO
				INNER JOIN FTSSE.FIELD_INSTANCE GENERIC ON PARTINFO.FGIID = GENERIC.CONTAININGFGIID AND GENERIC.FID = 'GENERIC'
				WHERE PARTINFO.FGID = 'PARTINFO'
				AND PARTINFO.CONTAININGFGIID IN (".implode(",",$aryTemp).")
				ORDER BY DOCUMENTFGIID, FGISEQUENCENUM;";
			$RSMain = ExecuteIQuery($strSQL,$iConSUS);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intDFGIID = $LineMain["DOCUMENTFGIID"];
				$arySUS[$intDFGIID]["PARTINFO"]["GENERIC"] = $LineMain["GENERIC"];
			}

			$aryTemp = array();
		}
	}

	$aryEquip = array();
	$aryEGID = array_keys($aryMap["EGID_SRSID_SRSSID"]);
	$aryTemp = array();

	while ($intEGID = array_pop($aryEGID)) {
		$aryTemp[] = mysqli_real_escape_string($iConSUS,$intEGID);

		if(count($aryTemp) >= 100 || count($aryEGID) == 0) {
			$strSQL = "
				SELECT
				EQUIP_GROUP_ID,
				EQUIP_TYPE,
				EQUIP_NAME,
				QUANTITY_TO_USE
				FROM SUS_ROUTE_ADI.SRS_EQUIP
				WHERE EQUIP_GROUP_ID IN (".implode(",",$aryTemp).");";
			$RSMain = ExecuteIQuery($strSQL,$iConSUS);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intEGID = $LineMain["EQUIP_GROUP_ID"];
				$strType = $LineMain["EQUIP_TYPE"];
				$strName = $LineMain["EQUIP_NAME"];
				$aryEquip[$intEGID][$strType][$strName] = (int) $LineMain["QUANTITY_TO_USE"];
			}
			$aryTemp = array();
		}
	}
		


	$aryHW = array();	
	$aryTemp = array();
	$aryHWSID = array_keys($aryMap["HWSID_SRSID_SRSSID"]);
	while ($intHWSID = array_pop($aryHWSID)) {
		$aryTemp[] = mysqli_real_escape_string($iConSUS,$intHWSID);
		if(count($aryTemp) >= 100 || count($aryHWSID) == 0) {
			$strSQL = "
				SELECT
				HWS_ID,
				UCASE(HARDWARE_TYPE) HARDWARE_TYPE,
				UCASE(HARDWARE_NAME) HARDWARE_NAME,
				QUANTITY_TO_USE
				FROM SUS_ROUTE_ADI.SRS_HARDWARE
				WHERE HWS_ID IN (".implode(",",$aryTemp).");";
			$RSMain = ExecuteIQuery($strSQL,$iConSUS);
			while ($LineMain = mysqli_fetch_assoc($RSMain)) {
				$intHWSID = $LineMain["HWS_ID"];
				$strType = $LineMain["HARDWARE_TYPE"];
				$strName = $LineMain["HARDWARE_NAME"];

				$aryHW[$intHWSID][$strType][$strName] = (int) $LineMain["QUANTITY_TO_USE"];
			}
			$aryTemp = array();
		}
	}

	#Print_R($arySRSM);
	#Print_R($arySRSS);
	#Print_R($aryEquip);
	#Print_R($aryHW);
	#die();

	return array(
		"SRS_MASTER"=>$arySRSM,
		"SRS_SETUP"=>$arySRSS,
		"SRS_EQUIP"=>$aryEquip,
		"SRS_HARDWARE"=>$aryHW,
		"SUS"=>$arySUS
	);
}


function SRS_SUPPLEMENT($iConSUS,$arySRS,$bolDebug=false) {
	#Print_R($aryMapRCID);
	#die();
	
	$aryMap = array(
		"STEPID_SRSSID_SRSID"=>array(),
		"DOCID_SRSSID_SRSID"=>array()
	);

	if(isset($arySRS["SRS_SETUP"])) {
		foreach(array_keys($arySRS["SRS_SETUP"]) as $intSRSID) {
			foreach($arySRS["SRS_SETUP"][$intSRSID] as $intSRSSID => $arySRSSTemp) {
				switch (true) {
					case !isset($arySRSSTemp["REF_STEP_ID"]):
						break;
					case is_null($arySRSSTemp["REF_STEP_ID"]) || trim($arySRSSTemp["REF_STEP_ID"]) == "":
						break;
					default:
						$intRSID = $arySRSSTemp["REF_STEP_ID"];
						$aryMap["STEPID_SRSSID_SRSID"][$intRSID][$intSRSSID] = $intSRSID;
						break;
				}

				switch (true) {
					case !isset($arySRSSTemp["REF_DOC_ID"]):
						break;
					case is_null($arySRSSTemp["REF_DOC_ID"]) || trim($arySRSSTemp["REF_DOC_ID"]) == "":
						break;
					default:
						$intDFGIID = $arySRSSTemp["REF_DOC_ID"];
						$aryMap["DOCID_SRSSID_SRSID"][$intDFGIID][$intSRSSID] = $intSRSID;
						break;
				}				
			}
		}
	}

	$aryTemp = array();
	foreach(array_keys($aryMap["STEPID_SRSSID_SRSID"]) as $intRSID) {
		$aryTemp[] = mysqli_real_escape_string($iConSUS,$intRSID);
	}
	if(count($aryTemp) > 0) {
		$strSQL = "
			SELECT
			TESTINFO.FGIID FGIID,
			UCASE(IF(STEPINST.LONGVALUE != '',STEPINST.LONGVALUE,STEPINST.SHORTVALUE)) STEPINST
			FROM FTSSE.FIELD_GROUP_INSTANCE TESTINFO
			INNER JOIN FTSSE.FIELD_INSTANCE STEPINST ON TESTINFO.FGIID = STEPINST.CONTAININGFGIID AND STEPINST.FID = 'STEPINST'
			WHERE TESTINFO.FGIID IN (".implode(",",$aryTemp).");";
		$RSMain = ExecuteIQuery($strSQL,$iConSUS);
		while ($LineMain = mysqli_fetch_assoc($RSMain)) {
			$intStepID = $LineMain["FGIID"];
			$strSI = $LineMain["STEPINST"];
			$aryTemp = explode("\n",$strSI);

			$arySI = array();
			foreach($aryTemp as $strTemp) {
				$aryTemp2 = explode(":",$strTemp);
				if(count($aryTemp2) == 2) {
					$strField = trim($aryTemp2[0]);
					$strValue = trim($aryTemp2[1]);
					$arySI[$strField] = $strValue;
				}
			}

			if(count($arySI) > 0) {
				foreach($aryMap["STEPID_SRSSID_SRSID"][$intStepID] as $intSRSSID => $intSRSID) {
					$arySRSSTemp = $arySRS["SRS_SETUP"][$intSRSID][$intSRSSID];
					$arySRSSTemp["STEPINST"] = $arySI;
					$arySRS["SRS_SETUP"][$intSRSID][$intSRSSID] = $arySRSSTemp;
				}
			}
		}
	}
	

	#Print_R($arySRS);
	#die();

	return $arySRS;
}


?>

