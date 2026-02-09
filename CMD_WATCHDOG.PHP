<?PHP
include_once("/var/www/html/MYSQLI_COMMON_FUNCTIONS.PHP");
include_once("/var/www/html/SCRIPTS/MAXCIM_CONVERSION/REFRESH_FUNCTIONS.PHP");
Include_Once("/var/www/html/SCRIPTS/MAXCIM_CONVERSION/CONVERT_CONSOLIDATE_FUNCTIONS.PHP");
include_once("/var/www/html/SCRIPTS/MAXCIM_CONVERSION/VALIDATE_TRANSFER_FUNCTIONS.PHP");


$aryRequest = Array();
switch (true) {
	case isset($_REQUEST["AUTH_KEY"]) && $_REQUEST["AUTH_KEY"] == "PENTESTWEBSCRAPERSARESTUPIDANDUSELESS":
		$aryRequest = array();

		$aryRequest["INPUT_TYPE"] = $_REQUEST["INPUT_TYPE"];
		$aryRequest["OUTPUT_TYPE"] = $_REQUEST["OUTPUT_TYPE"];	
		$aryRequest["INPUT"] = json_decode($_REQUEST["INPUT"],true);

		break;
	case count($argv) > 0:
		$aryRequest["INPUT_TYPE"] = $argv[0];
		$aryRequest["OUTPUT_TYPE"] = $argv[1];
		$aryRequest["INPUT"] = $argv[2];
		break;
}

$iConRLM = Open_ILink("AFOTCOSJ004");
$aryWFM = WATCHDOG_WORKFLOW_MASTER_GET($iConRLM);
#Print_R2($aryWFM);
#die();

#Print_R2($aryRequest);
if(JSON_LAST_ERROR() == JSON_ERROR_NONE) {
	#print_r2($aryRequest);
	#die();

	if(REQUEST_VALIDATE($aryRequest,$aryWFM) === true) {
		switch (true) {
			case isset($aryWFM[$aryRequest["OUTPUT_TYPE"]]):

				$arySCM = ACTION_VALIDATE($iConRLM,$aryRequest["INPUT"],$aryRequest["OUTPUT_TYPE"],$aryWFM);
				#Print_R2($arySCM);
				#die();

				if($arySCM !== false && count($arySCM) > 0) {
					#echo "if i were enabled, i would process this/these SCM_ID/s<BR>";
					#print_r2($arySCM);

					#echo "<DIV style=\"position:absolute;top:40px;left:40px;\">";
					echo "<SPAN CLASS=B2>Processing ".(count($arySCM)>1?"these items":"this item")."</SPAN><BR>";
					echo "<TABLE WIDTH=400 BORDER=1 CELLSPACING=0 CELLPADDING=1>";
					echo "<THEAD CLASS=B1 BGCOLOR=SILVER><TR><TH>SCM ID</TH><TH>DOCNUM</TH><TH>REV</TH><TH>DOCUMENTFGIID</TH></TR></THEAD>";
					echo "<TBODY CLASS=B1 BGCOLOR=#FFFFFF>";

					foreach($arySCM as $keySCMID => $arySCMTemp) {
						echo "<TR>";
						echo "<TD>".$keySCMID."</TD>";
						echo "<TD>".$arySCMTemp["DOCNUM"]."</TD>";
						echo "<TD>".$arySCMTemp["REV"]."</TD>";
						echo "<TD>".$arySCMTemp["DFGIID_PROD"]."</TD>";
						echo "</TR>";
					}

					echo "</TBODY></TABLE><BR><BR>";


					$iConSUSProd = Open_ILink("TESTWEB2");
					$iConSJ004 = Open_ILink("AFOTCOSJ004");

					switch (true) {
						case $aryRequest["OUTPUT_TYPE"] == "SCM_VALIDATE":
							SCM_VALIDATE($iConRLM,$arySCM);
							break;
						case $aryRequest["OUTPUT_TYPE"] == "DEV_TO_PROD_PUT":
							DEV_TO_PROD_PUT($iConRLM,$arySCM,"SCM_VALIDATE");
							break;
						case $aryRequest["OUTPUT_TYPE"] == "ENGINEER_DO":
							ENGINEER_DO($iConRLM,$iConSUSProd,$arySCM,"DEV_TO_PROD_PUT");
							break;
						case $aryRequest["OUTPUT_TYPE"] == "CHANGEDESC_DO":
							$aryFIDDesc = FID_DESC_GET($iConSUSProd);
							CHANGEDESC_DO($iConRLM,$iConSUSProd,$aryFIDDesc,$arySCM,"ENGINEER_DO");
							break;
						case $aryRequest["OUTPUT_TYPE"] == "DOCCO_SEND":
							DOCCO_SEND($iConSUSProd,$iConRLM,$iConSJ004,$arySCM,false);
							break;
					}


					mysqli_close($iConSUSProd);
					mysqli_close($iConSJ004);

					echo "<BR>Processing Complete.<BR>";
				}else{
					die("The action specified is not valid for the item/s selected");
				}
				

				#echo "</DIV>";
				break;
			case $aryRequest["OUTPUT_TYPE"] == "WORKFLOW_REMOVE":

				#previous completed step MUST be docco_send
				$arySCM = ACTION_VALIDATE($iConRLM,$aryRequest["INPUT"],$aryRequest["OUTPUT_TYPE"],$aryWFM);
				#Print_R2($arySCM);

				if($arySCM !== false && count($arySCM) > 0) {
					#echo "if i were enabled, i would process this/these SCM_ID/s<BR>";
					#print_r2($arySCM);


					#echo "<DIV style=\"position:absolute;top:40px;left:40px;\">";
					echo "<SPAN CLASS=B2>Processing ".(count($arySCM)>1?"these items":"this item")."</SPAN><BR>";
					echo "<TABLE WIDTH=400 BORDER=1 CELLSPACING=0 CELLPADDING=1>";
					echo "<THEAD CLASS=B1 BGCOLOR=SILVER><TR><TH>SCM ID</TH><TH>DOCNUM</TH><TH>REV</TH><TH>DOCUMENTFGIID</TH></TR></THEAD>";
					echo "<TBODY CLASS=B1 BGCOLOR=#FFFFFF>";

					foreach($arySCM as $keySCMID => $arySCMTemp) {
						echo "<TR>";
						echo "<TD>".$keySCMID."</TD>";
						echo "<TD>".$arySCMTemp["DOCNUM"]."</TD>";
						echo "<TD>".$arySCMTemp["REV"]."</TD>";
						echo "<TD>".$arySCMTemp["DFGIID_PROD"]."</TD>";
						echo "</TR>";
						$strStepName = $arySCMTemp["STEP_NAME"];
					}

					echo "</TBODY></TABLE><BR><BR>";

					#die();

					

					foreach(array_keys($arySCM) as $keySCMID) {
						#echo "hello:".$keySCMID."<BR>";
						WORKFLOW_REMOVE($iConRLM,$keySCMID,$strStepName,false);
					}

					echo "<BR>Processing Complete.<BR>";
				}else{
					die("The action specified is not valid for the item/s selected");
				}
				

				#echo "</DIV>";
				break;
			case $aryRequest["OUTPUT_TYPE"] == "LATEST_LOG_CONTENTS":

				$strPath = "/var/www/html/SCRIPTS/MAXCIM_CONVERSION/LOGS";
				if(is_dir($strPath) !== false) {
					if(($dirLog = opendir($strPath)) !== false) {
						if(is_resource($dirLog) !== false) {
							while (($strFN = readdir($dirLog)) !== false) {
								if(strpos($strFN,"CONSOL_CHANGE_DETECT_") !== false) {
									$aryFN[] = $strFN;
								}
							}
							closedir($dirLog);

							#echo "contents of ".$strPath."<BR>";
							#echo implode("<BR>",$aryFN);

							$aryThis = $aryFN;
							$aryThat = array();

							foreach($aryThis as $keyThis => $valueThis) {
								$aryTemp = explode("_",str_replace(".log","",$valueThis));
								$strDate = array_pop($aryTemp);
								$intTime = strtotime($strDate);

								$aryThat[$intTime] = $keyThis;
							}

							#place latest date at end of array, pop that date off and use it
							KSort($aryThat);
							$keyThis = array_pop($aryThat);
							$strFN = $strPath."/".$aryThis[$keyThis];

							if(file_exists($strFN)) {
								$strLog = file_get_contents($strFN);
								echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",$strLog)));
								#echo $strLog;
							}
						}else{
							echo "not a resource";
						}
					}else{
						echo "could not open dir";
					}
				}else{
					echo "not a dir";
				}
				
				break;					
		}
	}else{
		die("invalid arguments");
	}
}else{
	die("invalid JSON request");
}

mysqli_close($iConRLM);


Function REQUEST_VALIDATE($aryRequest,$aryWFM) {
	$aryTemp 	= array_keys($aryWFM);
	$aryTemp[]	= "WORKFLOW_REMOVE";

	$aryIO 		= array();
	$aryIO[] 	= array("INPUT_TYPE"=>array("SCM_ID"),"OUTPUT_TYPE"=>$aryTemp);
	$aryIO[] 	= array("INPUT_TYPE"=>array("APP"),"OUTPUT_TYPE"=>array("LATEST_LOG_CONTENTS"));

	foreach($aryIO as $aryIOTemp) {
		switch (true) {
			case !in_array($aryRequest["INPUT_TYPE"],$aryIOTemp["INPUT_TYPE"]):
				break;
			case !in_array($aryRequest["OUTPUT_TYPE"],$aryIOTemp["OUTPUT_TYPE"]):
				break;
			case !is_array($aryRequest["INPUT"]) || count($aryRequest["INPUT"]) == 0:
				break;
			default:
				return true;
				break;
		}
	}

	return false;
}


Function ACTION_VALIDATE($iConRLM,$aryInput,$strAction,$aryWFM) {
	switch ($strAction) {
		case "WORKFLOW_REMOVE":
			foreach($aryWFM as $keyStepName => $aryWFMTemp) {
				if($aryWFMTemp["CAN_WF_REMOVE"] == 1) {
					$aryTemp = SCM_BY_WORKFLOW_GET($iConRLM,$keyStepName);
					$aryMatch = array_intersect($aryInput,array_keys($aryTemp));
					if(count($aryMatch) > 0) {
						$aryThis = array();
						foreach($aryMatch as $intSCMID) {
							$aryThis[$intSCMID] = $aryTemp[$intSCMID];
						}	
						return $aryThis;
					}
				}
			}
			
			break;
		default:
			#find the previous step
			$aryPrev = array();
			foreach($aryWFM as $keyStep => $aryWFMTemp) {
				if($aryWFMTemp["NEXT"] == $strAction) {
					$aryPrev[] = $keyStep;
				}
			}

			$aryThis = array();
			foreach($aryPrev as $strPrev) {
				$aryTemp = SCM_BY_WORKFLOW_GET($iConRLM,$strPrev);
				foreach(array_keys($aryTemp) as $intSCMID) {
					$aryThis[$intSCMID] = $aryTemp[$intSCMID];
				}
			}

			$aryThat = array();
			$aryMatch = array_intersect($aryInput,array_keys($aryThis));

			#Print_R($aryThis);
			#Print_R2($aryMatch);
			if(count(array_unique($aryMatch)) == count(array_unique($aryInput))) {
				foreach($aryMatch as $valueSCMID) {
					$aryThat[$valueSCMID] = $aryThis[$valueSCMID];
				}
			}

			return $aryThat;

			break;
	}


	return false;
}


function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}