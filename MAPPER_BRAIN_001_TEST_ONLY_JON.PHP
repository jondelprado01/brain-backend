<?php
header('Access-Control-Allow-Origin: *'); //JON
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
	"OUTPUT_TYPE"=>array("TP_INSTANCE_CLONE")
);

$aryIO[] = array(
	"INPUT_TYPE"=>array("SAP_RTE_ID"),
	"OUTPUT_TYPE"=>array("TP_INSTANCE_GET_ALL","TP_INSTANCE_SUMMARY_GET_ALL","TP_INSTANCE_SUMMARY_COMPARE_GET_ALL")
);

$bolDebug = false;
#$bolDebug = true;
if(isset($_REQUEST["DEBUG"]) && $_REQUEST["DEBUG"] != "") {
	$bolDebug = true;
}

if($bolDebug === true) {
	print_r2($_REQUEST);
}

$strInputType = $_REQUEST["INPUT_TYPE"];
$strOutputType = $_REQUEST["OUTPUT_TYPE"];
$aryInput = $_REQUEST["INPUT"];

switch (true) {
	case $strOutputType == "TP_INSTANCE_SUMMARY_GET_ALL" || $strOutputType == "TP_INSTANCE_DATA_GET_ALL" || $strOutputType == "TP_INSTANCE_SUMMARY_COMPARE_GET_ALL":
		$iConRLM = Open_ILink("localhost");

		switch ($strOutputType) {
			case "TP_INSTANCE_SUMMARY_GET_ALL":
				$aryNeed = array("SUMMARY");
				break;
			case "TP_INSTANCE_DATA_GET_ALL":
				$aryNeed = array("DATA");
				break;
			case "TP_INSTANCE_SUMMARY_COMPARE_GET_ALL":
				$aryNeed = array("SUMMARY","DATA","COMPARE");
				break;
		}

		$strStatus = "SUCCESS";
		$strMessage = "";
		$aryData = array();		
		foreach($aryInput as $intCtr => $strSRI) {
			try{
				$aryResponse = TP_INSTANCE_GET_ALL($iConRLM,$aryNeed,$strSRI,$bolDebug);
				$aryData[$intCtr] = $aryResponse;
			} catch (exception $e) {
				$strStatus = "FAILURE";
				$strMessage = $e->getMessage();
				$aryData = array();
			}
		}
	

		mysqli_close($iConRLM);
		break;	
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

if(isset($_REQUEST["NO_JSON"])) {
	print_r2($aryReturn);
}else{
	echo json_encode($aryReturn);
}


function TP_INSTANCE_GET_ALL($iConRLM,$aryNeed,$strSRI,$bolDebug=false) {
	$strSQL = "
		SELECT DISTINCT
		TPI_ID,
		INSTANCE,
		FYWW,
		EFF_START_DT
		FROM BRAIN.TP_INSTANCE_MAIN
		WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strSRI)."'
		ORDER BY FYWW;";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	$aryReturn[0] = array(
		"INSTANCE"=>0,
		"FYWW"=>null,
		"EFF_START_DT"=>null,
		"DATA"=>array(),
		"SUMMARY"=>array(),
		"COMPARE"=>array()
	);

	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intTPIID = $LineMain["TPI_ID"];
		$aryReturn[$intTPIID] = array(
			"INSTANCE"=>$LineMain["INSTANCE"],
			"FYWW"=>$LineMain["FYWW"],
			"EFF_START_DT"=>$LineMain["EFF_START_DT"],
			"DATA"=>array(),
			"SUMMARY"=>array(),
			"COMPARE"=>array()
		);
	}

	#$bolDebug = true;
	if(in_array("DATA",$aryNeed)) {
		foreach($aryReturn as $intTPIID => $aryReturnTemp) {
			if($intTPIID == 0) {
				$aryReturnTemp["DATA"] = TPI_INSTANCE_DATA_GET($iConRLM,"ALL","SAP_RTE_ID",$strSRI,$bolDebug);
			}else{
				$aryReturnTemp["DATA"] = TPI_INSTANCE_DATA_GET($iConRLM,"ALL","TPI_ID",$intTPIID,$bolDebug);
			}			
		}
	}

	if(in_array("SUMMARY",$aryNeed) || in_array("COMPARE",$aryNeed)) {
		#pull only once per instance (0 or otherwise)
		$aryCompareData = array();

		foreach(array_keys($aryReturn) as $intTPIID) {
			if($intTPIID == 0) {
				$aryCompareData[$intTPIID] = TPI_INSTANCE_DATA_GET($iConRLM,"COMPARE","SAP_RTE_ID",$strSRI,$bolDebug);
			}else{
				$aryCompareData[$intTPIID] = TPI_INSTANCE_DATA_GET($iConRLM,"COMPARE","TPI_ID",$intTPIID,$bolDebug);
			}
		}
	}

	

	if(in_array("COMPARE",$aryNeed)) {
		$intTPIIDThat = null;

		foreach(array_keys($aryCompareData) as $intTPIIDThis) {
			$aryReturn[$intTPIIDThis]["SUMMARY"] = TP_INSTANCE_SUMMARIZE($aryCompareData[$intTPIID]);

			if(!is_null($intTPIIDThat)) {
				#echo $intTPIIDThis." vs ".$intTPIIDThat."<BR>";
				$aryCDTemp = array(
					"THIS"=>$aryCompareData[$intTPIIDThis],
					"THAT"=>$aryCompareData[$intTPIIDThat]
				);
				$arySummaryTemp = array(
					"THIS"=>$aryReturn[$intTPIIDThis]["SUMMARY"],
					"THAT"=>$aryReturn[$intTPIIDThat]["SUMMARY"]
				);

				$aryReturn[$intTPIIDThis]["COMPARE"] = TP_INSTANCE_COMPARE($aryCDTemp,$arySummaryTemp,$bolDebug);
			}

			$intTPIIDThat = $intTPIIDThis;
		}
	}
	#die();
	return $aryReturn;
}



function TP_INSTANCE_SUMMARIZE($aryMain,$bolDebug=false) {
	
	$aryFlow = array(
		"TEST_PERC"=>0,
		"STEP_NM"=>array()
	);

	foreach($aryMain["RTE_SEQ_NUM"] as $arySeqTemp) {
		$aryFlow["TEST_PERC"] += $arySeqTemp["TEST_PERC"];
		$aryFlow["STEP_NM"][] = $arySeqTemp["STEP_NM"];
	}

	$aryMap = array(
		"SHA1_SETUP"=>array(),
		"SHA1_STEPNM"=>array()
	);

	foreach(array_keys($aryMain["RTE_SEQ_NUM"]) as $decSeq) {
		$strStepName = $aryMain["RTE_SEQ_NUM"][$decSeq]["STEP_NM"];

		if(isset($aryMain["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"][1])) {
			$aryMainTemp = $aryMain["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"][1];

			$aryTemp = array(
				"TESTER"=>$aryMainTemp["TESTER"],
				"HANDLER"=>$aryMainTemp["HANDLER"],
				"UTPI"=>$aryMainTemp["UTPI"]
			);
			$strSHA1 = sha1(serialize($aryTemp));
			$aryMap["SHA1_SETUP"][$strSHA1] = $aryTemp;
			$aryMap["SHA1_STEPNM"][$strSHA1][] = $strStepName;
		}
	}

	foreach($aryMap["SHA1_SETUP"] as $strSHA1 => $aryMapTemp) {
		$strDesc = $aryMapTemp["TESTER"].", ".$aryMapTemp["HANDLER"].", x".$aryMapTemp["UTPI"];
		$aryPriSetup[$strDesc] = $aryMap["SHA1_STEPNM"][$strSHA1];
	}	

	$aryReturn = array(
		"FLOW"=>$aryFlow,
		"PRIMARY_SETUP"=>$aryPriSetup
	);

	return $aryReturn;
}


function TP_INSTANCE_COMPARE($aryMain,$arySummary,$bolDebug=false) {
	$aryReturn = array();

	#compare flow, both in terms of step_nm and numerical representation
	#pull flow from summary and re-use. do not calculate anything other than differences
	$aryFlow = array(
		"TEST_PERC"=>array(
			"THIS"=>$arySummary["THIS"]["FLOW"]["TEST_PERC"],
			"THAT"=>$arySummary["THAT"]["FLOW"]["TEST_PERC"]
		),
		"STEP_NM"=>array(
			"THIS"=>$arySummary["THIS"]["FLOW"]["STEP_NM"],
			"THAT"=>$arySummary["THAT"]["FLOW"]["STEP_NM"]
		)
	);

	switch (true) {
		case $aryFlow["TEST_PERC"]["THIS"] > $aryFlow["TEST_PERC"]["THAT"]:
			$aryReturn["FLOW_NUMERIC"]["MORE"] = array(
				"THIS"=>round($aryFlow["TEST_PERC"]["THIS"],3),
				"THAT"=>round($aryFlow["TEST_PERC"]["THAT"],3),
				"DIFF"=>round($aryFlow["TEST_PERC"]["THIS"] - $aryFlow["TEST_PERC"]["THAT"],3)
			);
			break;
		case $aryFlow["TEST_PERC"]["THIS"] < $aryFlow["TEST_PERC"]["THAT"]:
			$aryReturn["FLOW_NUMERIC"]["LESS"] = array(
				"THIS"=>round($aryFlow["TEST_PERC"]["THIS"],3),
				"THAT"=>round($aryFlow["TEST_PERC"]["THAT"],3),
				"DIFF"=>round($aryFlow["TEST_PERC"]["THAT"] - $aryFlow["TEST_PERC"]["THIS"],3)
			);
			break;
	}


	$aryDiff1 = array_diff($aryFlow["STEP_NM"]["THIS"],$aryFlow["STEP_NM"]["THAT"]);
	$aryDiff2 = array_diff($aryFlow["STEP_NM"]["THAT"],$aryFlow["STEP_NM"]["THIS"]);

	if(count($aryDiff1) > 0) {
		$aryReturn["COMPARE"]["FLOW"]["MORE"][] = array(
			"THIS"=>implode(",",$aryFlow["STEP_NM"]["THIS"]),
			"THAT"=>implode(",",$aryFlow["STEP_NM"]["THAT"]),
			"DIFF"=>$aryDiff1
		);	
	}

	if(count($aryDiff2) > 0) {
		$aryReturn["COMPARE"]["FLOW"]["LESS"][] = array(
			"THIS"=>implode(",",$aryFlow["STEP_NM"]["THIS"]),
			"THAT"=>implode(",",$aryFlow["STEP_NM"]["THAT"]),
			"DIFF"=>$aryDiff2
		);
	}

	#end flow. everything below is only for when steps line up
	
	#first look for new testers, new handlers. keep these separate from new setups, which is existing tester and existing handler in new combination
	$aryTH = array("THIS"=>array(),"THAT"=>array());
	foreach(array_keys($aryMain) as $strTT) {
		foreach(array_keys($aryMain[$strTT]["RTE_SEQ_NUM"]) as $decSeq) {
			foreach($aryMain[$strTT]["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"] as $aryPriTemp) {
				$aryTH[$strTT]["TESTER"][] = $aryPriTemp["TESTER"];
				$aryTH[$strTT]["HANDLER"][] = $aryPriTemp["HANDLER"];
			}
		}
	}

	$aryResType = array("TESTER","HANDLER");
	foreach($aryResType as $strResType) {
		$aryTH["THIS"][$strResType] = array_unique($aryTH["THIS"][$strResType]);
		sort($aryTH["THIS"][$strResType]);

		$aryDiff1 = array_diff($aryTH["THIS"][$strResType],$aryTH["THAT"][$strResType]);
		$aryDiff2 = array_diff($aryTH["THAT"][$strResType],$aryTH["THIS"][$strResType]);
		
		if(count($aryDiff1) > 0) {
			#exists in THIS not in THAT
			$aryReturn[$strResType]["MORE"][] = array(
				"THIS"=>implode(",",$aryTH["THIS"][$strResType]),
				"THAT"=>implode(",",$aryTH["THAT"][$strResType]),
				"DIFF"=>$aryDiff1
			);
		}

		if(count($aryDiff2) > 0) {
			#exists in THAT not in THIS
			$aryReturn[$strResType]["LESS"][] = array(
				"THIS"=>implode(",",$aryTH["THIS"][$strResType]),
				"THAT"=>implode(",",$aryTH["THAT"][$strResType]),
				"DIFF"=>$aryDiff2
			);
		}
	}

	#done tester, handler. everything below is for when flow matches, and no new testers or handlers (but possibly different setup combinations)
	#$add new setups to aryProcessed so we dont try and compare runrate data

	$aryProcessed = array();
	$aryMap["SHA1_TH"] = array();
	$aryMap["SHA1_TT_STEPNM"] = array();
	$aryMap["STEPNM_TT_SEQ"] = array();

	foreach(array_keys($aryMain["THIS"]["RTE_SEQ_NUM"]) as $decSeqThis) {
		$strStepName = $aryMain["THIS"]["RTE_SEQ_NUM"][$decSeqThis]["STEP_NM"];
		#must exactly match step_nm, not necesarily sequence

		$bolFound = false;
		foreach(array_keys($aryMain["THAT"]["RTE_SEQ_NUM"]) as $decSeqThat) {
			if($strStepName == $aryMain["THAT"]["RTE_SEQ_NUM"][$decSeqThat]["STEP_NM"]) {
				$bolFound = true;
				break;
			}
		}

		#only proceed if you found at least one step from THAT which has identical STEP_NM as THIS. and yes, i said at least one. 
		if($bolFound === true) {
			$aryMap["STEPNM_TT_SEQ"][$strStepName]["THIS"] = $decSeqThis;
			$aryMap["STEPNM_TT_SEQ"][$strStepName]["THAT"] = $decSeqThat;

			$aryPri = array(
				"THIS"=>$aryMain["THIS"]["RTE_SEQ_NUM"][$decSeqThis]["RES_PRIO_CD"],
				"THAT"=>$aryMain["THAT"]["RTE_SEQ_NUM"][$decSeqThat]["RES_PRIO_CD"]
			);

			$aryMap["SHA1_TT_PRI"] = array();
			foreach(array_keys($aryPri) as $strTT) {
				foreach($aryPri[$strTT] as $intPri => $aryPriTemp) {
					$aryTemp = array(
						"TESTER"=>$aryPriTemp["TESTER"],
						"HANDLER"=>$aryPriTemp["HANDLER"]
					);
					$strSHA1 = sha1(serialize($aryTemp));

					if(!isset($aryMap["SHA1_TH"][$strSHA1])) {
						$aryMap["SHA1_TH"][$strSHA1] = $aryTemp;
					}
					$arySetup[$strSHA1][$strStepName][$strTT][] = $intPri;

					$aryMap["SHA1_TT_STEPNM"][$strSHA1][$strTT][] = $strStepName;
				}
			}
		}
	}

	#missing setup (NOT DIFFERENT PRI)
	$aryMap["THAT_NOT_THIS"] = array();
	$aryMap["THIS_NOT_THAT"] = array();
	foreach($aryMap["SHA1_TT_STEPNM"] as $strSHA1 => $aryMapTemp) {
		if(!isset($aryMapTemp["THIS"])) {
			#exists in THAT not THIS
			foreach($aryMapTemp["THAT"] as $strStepName) {
				$aryMap["THAT_NOT_THIS"][$strSHA1][] = $strStepName;
			}
		}
		if(!isset($aryMapTemp["THAT"])) {
			foreach($aryMapTemp["THIS"] as $strStepName) {
				$aryMap["THIS_NOT_THAT"][$strSHA1][] = $strStepName;
			}
		}
	}

	foreach(array_keys($aryMap["THAT_NOT_THIS"]) as $strSHA1) {
		$strTester = $aryMap["SHA1_TH"][$strSHA1]["TESTER"];
		$strHandler = $aryMap["SHA1_TH"][$strSHA1]["HANDLER"];

		#RTE_SEQ_NUM is already sorted by mysql
		$aryTemp = array();
		foreach($aryMap["THAT_NOT_THIS"][$strSHA1] as $strStepName) {
			$aryTemp[] = $strStepName;

			foreach($arySetup[$strSHA1][$strStepName]["THAT"] as $intPriThat) {
				$aryProcessed["THAT"][$strStepName][] = $intPriThat;
			}
		}

		$aryReturn["SETUP"]["LESS"][] = array(
			"THIS"=>"",
			"THAT"=>$strTester."+".$strHandler,
			"DIFF"=>$aryTemp
		);
	}

	foreach(array_keys($aryMap["THIS_NOT_THAT"]) as $strSHA1) {
		$strTester = $aryMap["SHA1_TH"][$strSHA1]["TESTER"];
		$strHandler = $aryMap["SHA1_TH"][$strSHA1]["HANDLER"];

		#RTE_SEQ_NUM is already sorted by mysql
		$aryTemp = array();
		foreach($aryMap["THIS_NOT_THAT"][$strSHA1] as $strStepName) {
			$aryTemp[] = $strStepName;
			
			foreach($arySetup[$strSHA1][$strStepName]["THIS"] as $intPriThis) {
				$aryProcessed["THIS"][$strStepName][] = $intPriThis;
			}
		}

		$aryReturn["SETUP"]["MORE"][] = array(
			"THIS"=>$strTester."+".$strHandler,
			"THAT"=>"",
			"DIFF"=>$aryTemp
		);
	}

	#print_r2($arySetup);
	#die();

	#UTPI, TTPI, OEE, UTPH
	$aryField = array("UTPI","TTPI","INDX","OEE","UTPH");
	$aryMap["SHA1_DIFF"] = array();
	foreach(array_keys($arySetup) as $strSHA1) {
		foreach(array_keys($arySetup[$strSHA1]) as $strStepName) {
			if(isset($arySetup[$strSHA1][$strStepName]["THIS"]) && isset($arySetup[$strSHA1][$strStepName]["THAT"])) {
				#step name matches, tester+handler combo matches

				$aryRR = array();
				foreach(array_keys($arySetup[$strSHA1][$strStepName]) as $strTT) {
					$decSeq = $aryMap["STEPNM_TT_SEQ"][$strStepName][$strTT];
					foreach($arySetup[$strSHA1][$strStepName][$strTT] as $intPri) {
						#RR = runrate BTW
						$aryRR[$strTT][$intPri] = $aryMain[$strTT]["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"][$intPri];
					}
				}
				
				
				$aryDiff = array();

				foreach($aryRR["THAT"] as $intPriThat => $aryRRThat) {
					foreach($aryRR["THIS"] as $intPriThis => $aryRRThis) {
						foreach($aryField as $strField) {
							$decDiff = round(abs($aryRRThis[$strField] - $aryRRThat[$strField]),3);
							$pctDiff = 2;

							if($pctDiff > 0.01) {
								$intKDiff = (int) ($decDiff * 1000);
								#echo $decDiff." / ".$pctDiff." / ".$intKDiff."<BR>";
								switch (true) {
									case $aryRRThis[$strField] > $aryRRThat[$strField]:
										$aryDiff[$strField]["MORE"][$intKDiff][] = array(
											"THIS"=>$aryRRThis[$strField],
											"THAT"=>$aryRRThat[$strField],
											"DIFF"=>$decDiff
										);
										break;
									case $aryRRThis[$strField] < $aryRRThat[$strField]:
										$aryDiff[$strField]["LESS"][$intKDiff][] = array(
											"THIS"=>$aryRRThis[$strField],
											"THAT"=>$aryRRThat[$strField],
											"DIFF"=>$decDiff
										);
										break;
								}
							}
						}
					}
				}

				#print_r2($aryDiff);
				foreach(array_keys($aryDiff) as $strField) {
					#isolate largest diff within this field and hold onto that one (can be others but dont care)
					foreach(array_keys($aryDiff[$strField]) as $strML) {
						krsort($aryDiff[$strField][$strML]);
						foreach(array_keys($aryDiff[$strField][$strML]) as $intKDiff) {
							foreach($aryDiff[$strField][$strML][$intKDiff] as $aryDiffTemp) {
								$aryMap["SHA1_DIFF"][$strSHA1][$strField][$strML] = $aryDiffTemp;
								#only want the biggest increase and biggest decrease. absolute values, you can figure it out
								break;
							}
							break;
						}
					}
				}
			}
		}
	}

	foreach(array_keys($aryMap["SHA1_DIFF"]) as $strSHA1) {
		$strTester = $aryMap["SHA1_TH"][$strSHA1]["TESTER"];
		$strHandler = $aryMap["SHA1_TH"][$strSHA1]["HANDLER"];

		if($bolDebug === true) {
			echo "\n".$strTester.", ".$strHandler."\n";
		}
		foreach(array_keys($aryMap["SHA1_DIFF"][$strSHA1]) as $strField) {
			foreach($aryMap["SHA1_DIFF"][$strSHA1][$strField] as $strML => $aryDiffTemp) {
				if($bolDebug === true) {
					echo chr(9).$strField." / ".$strML."\n";
					echo chr(9).chr(9).$aryDiffTemp["THIS"].", ".$aryDiffTemp["THAT"].", ".$aryDiffTemp["DIFF"]."\n";
				}


				$aryReturn[$strField][$strML][] = array(
					"THIS"=>$aryDiffTemp["THIS"],
					"THAT"=>$aryDiffTemp["THAT"],
					"DIFF"=>$aryDiffTemp["DIFF"],
					"INFO"=>array(
						"TESTER"=>$strTester,
						"HANDLER"=>$strHandler
					)
				);
			}
		}
	}
	
	#print_r2($aryReturn);
	#die();
	return $aryReturn;
}


function TPI_INSTANCE_DATA_GET($iConRLM,$strNeed,$strInputType,$strInput,$bolDebug=false) {
	if($bolDebug === true) {
		echo $strNeed." / ".$strInputType." / ".$strInput."<BR>";
	}

	switch ($strNeed) {
		case "ALL":
			$aryField = array('MFG_PART_NUM','GENERIC','RTE_SEQ_NUM','STEP_NM','SITE_NUM','RES_AREA','TEMP_C','TEMP_CLASS','RES_SET_ID','RES_PRIO_CD','TESTER','HANDLER','HW_SET_ID','UTPI','TEST_PERC','REF_DOC_ID','REF_STEP_ID','REF_SETUP_ID','TD_EFF','WFR_IDX_PCT','GROSS_DPW','TTPI','INDX','OEE','UTPH','ATOM_MASTER_ID','ATTR_SET_ID');
			break;
		case "COMPARE":
			$aryField = array('RTE_SEQ_NUM','STEP_NM','TEST_PERC','RES_PRIO_CD','TESTER','HANDLER','UTPI','TTPI','INDX','OEE','UTPH');
			break;
	}

	switch ($strInputType) {
		case "SAP_RTE_ID":
			$strSQL = "
				SELECT
				".implode(",",$aryField)."
				FROM BODS_JDA_STAGE.ADI_ALLDB_ALL
				WHERE SAP_RTE_ID = '".mysqli_real_escape_string($iConRLM,$strInput)."'
				AND EFF_START_DT IS NULL
				ORDER BY RTE_SEQ_NUM, RES_PRIO_CD;";
			break;
		case "TPI_ID":
			$strSQL = "
				SELECT
				".implode(",",$aryField)."
				FROM BRAIN.TP_INSTANCE_DATA
				WHERE TPI_ID = ".mysqli_real_escape_string($iConRLM,$strInput)."
				ORDER BY RTE_SEQ_NUM, RES_PRIO_CD;";
			break;
		default:
			throw new exception("INVALID INPUT_TYPE FOR ".__FUNCTION__);
			break;
	}

	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$decSeq = $LineMain["RTE_SEQ_NUM"];
		$intPri = $LineMain["RES_PRIO_CD"];

		if($strNeed == "ALL" && !isset($aryReturn["RTE_SEQ_NUM"])) {
			$aryAdd = array('MFG_PART_NUM','GENERIC');
			if(count(array_diff($aryAdd,$aryField)) > 0) {
				throw new exception("UNDEFINED FIELD SPECIFIED");
			}

			foreach($aryAdd as $strField) {
				$aryReturn[$strField] = $LineMain[$strField];
			}
		}

		if(!isset($aryReturn["RTE_SEQ_NUM"][$decSeq])) {
			$aryReturn["RTE_SEQ_NUM"][$decSeq] = array(
				"STEP_NM"=>$LineMain["STEP_NM"],
				"TEST_PERC"=>$LineMain["TEST_PERC"],
				"RES_PRIO_CD"=>array()
			);

			if($strNeed == "ALL") {
				$aryAdd = array('SITE_NUM','RES_AREA','TEMP_C','TEMP_CLASS');
				if(count(array_diff($aryAdd,$aryField)) > 0) {
					throw new exception("UNDEFINED FIELD SPECIFIED");
				}

				foreach($aryAdd as $strField) {
					$aryReturn["RTE_SEQ_NUM"][$decSeq][$strField] = $LineMain[$strField];
				}
			}
		}

		if(!isset($aryReturn["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"][$intPri])) {
			$aryReturn["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"][$intPri] = array(
				"TESTER"=>$LineMain["TESTER"],
				"HANDLER"=>$LineMain["HANDLER"],
				"UTPI"=>$LineMain["UTPI"],
				"TTPI"=>$LineMain["TTPI"],
				"INDX"=>$LineMain["INDX"],
				"OEE"=>$LineMain["OEE"],
				"UTPH"=>$LineMain["UTPH"]
			);

			if($strNeed == "ALL") {
				$aryAdd = array('RES_SET_ID','HW_SET_ID','REF_DOC_ID','REF_STEP_ID','REF_SETUP_ID','TD_EFF','WFR_IDX_PCT','GROSS_DPW','ATOM_MASTER_ID','ATTR_SET_ID');
				if(count(array_diff($aryAdd,$aryField)) > 0) {
					throw new exception("UNDEFINED FIELD SPECIFIED");
				}

				foreach($aryAdd as $strField) {
					$aryReturn["RTE_SEQ_NUM"][$decSeq]["RES_PRIO_CD"][$intPri][$strField] = $LineMain[$strField];
				}
			}
		}
	}

	return $aryReturn;
}


function TP_INSTANCE_CLONE($iConRLM,$aryInputTemp,$bolDebug=false) {
	#2025-06-26 RM two possible ways: TPI_ID or SAP_RTE_ID. TPI_ID will clone an existing instance, SAP_RTE_ID will clone the EFF_START_DT = NULL entry from adi_alldb_all

	#basic error checking
	switch (true) {
		case !isset($aryInputTemp["EFF_START_DT"]) && !isset($aryInputTemp["FYWW"]):
			throw new exception("MUST HAVE EITHER EFF_START_DT OR FYWW");
			break;
		case !isset($aryInputTemp["TPI_ID"]) && !isset($aryInputTemp["SAP_RTE_ID"]):
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
			$intTPIIDThis = TP_INSTANCE_CREATE($iConRLM,$strSRI,$strESD,$intFYWW,$aryInputTemp["PROCESS_TYPE"],$aryInputTemp["NOTES"],$bolDebug);

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
			$intTPIIDThis = TP_INSTANCE_CREATE($iConRLM,$strSRI,$strESD,$intFYWW,$aryInputTemp["PROCESS_TYPE"],$aryInputTemp["NOTES"],$bolDebug);

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


function TP_INSTANCE_CREATE($iConRLM,$strSRI,$strESD,$intFYWW,$strPT,$strNotes,$bolDebug=false) {
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

function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}