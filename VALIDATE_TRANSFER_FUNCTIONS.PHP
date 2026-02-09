<?PHP


Function SUS_SPLICE_LONG_BY_FGIID($iConSUS,$arySUS) {
	
	If(Count($arySUS) > 0) {

		$arySQL = Array();

		$arySQL[] = "SET AUTOCOMMIT = 0;";

		$arySQL[] = "BEGIN;";

		ForEach(Array_Keys($arySUS) as $keyFGID) {
			ForEach(Array_Keys($arySUS[$keyFGID]) as $keyFGIID) {
				ForEach(Array_keys($arySUS[$keyFGID][$keyFGIID]) as $keyFID) {
					$arySQL[] = "
						DELETE FROM 
						FTSSE.FIELD_INSTANCE
						WHERE CONTAININGFGIID = ".MySQLI_Real_Escape_String($iConSUS,$keyFGIID)."
						AND FID = '".MySQLI_ReaL_Escape_String($iConSUS,$keyFID)."'";

					$intCtr = 1;
					ForEach($arySUS[$keyFGID][$keyFGIID][$keyFID] as $valueLong) {
						$arySQL[] = "
							INSERT INTO
							FTSSE.FIELD_INSTANCE
							(CONTAININGFGIID,FID,FISEQUENCENUM,SHORTVALUE,LONGVALUE)
							VALUES
							(
								".MySQLI_Real_Escape_String($iConSUS,$keyFGIID).",
								'".MySQLI_Real_Escape_String($iConSUS,$keyFID)."',
								".$intCtr.",
								'',
								'".MySQLI_Real_Escape_String($iConSUS,$valueLong)."'
							)";
						++$intCtr;
					}
				}
			}			
		}

		$arySQL[] = "COMMIT;";

		$arySQL[] = "SET AUTOCOMMIT = 1;";

		ForEach($arySQL as $valueSQL) {
			#Echo $valueSQL."<BR>";
			ExecuteIQuery($valueSQL,$iConSUS);
		}

	}

	#die();
	#Print_R($arySQL);
	#die();
}


Function SUS_SPLICE($iConSUS,$varDFGIID,$arySUS) {
	$arySQL = Array();

	$arySQL[] = "SET AUTOCOMMIT = 0;";

	$arySQL[] = "BEGIN;";

	ForEach(Array_Keys($arySUS) as $keyFGID) {
		#single FGIID only, single value per FID only!

		$varFGIID = FGIID_BY_FGID_GET($iConSUS,$varDFGIID,$keyFGID);
		If($varFGIID !== False) {
			ForEach(Array_Keys($arySUS[$keyFGID]) as $keyFID) {
				ForEach($arySUS[$keyFGID][$keyFID] as $valueShort) {
					$arySQL[] = "
						INSERT INTO
						FTSSE.FIELD_INSTANCE
						(CONTAININGFGIID,FID,FISEQUENCENUM,SHORTVALUE,LONGVALUE)
						VALUES
						(
							".MySQLI_Real_Escape_String($iConSUS,$varFGIID).",
							'".MySQLI_Real_Escape_String($iConSUS,$keyFID)."',
							1,
							'".MySQLI_Real_Escape_String($iConSUS,$valueShort)."',
							''
						)
						ON DUPLICATE KEY UPDATE
						SHORTVALUE = '".MySQLI_Real_Escape_String($iConSUS,$valueShort)."',
						LONGVALUE = '';";
					Break;
				}
			}
		}
	}

	$arySQL[] = "COMMIT;";

	$arySQL[] = "SET AUTOCOMMIT = 1;";

	ForEach($arySQL as $valueSQL) {
		#Echo $valueSQL."\n";
		ExecuteIQuery($valueSQL,$iConSUS);
	}

	#Print_R($arySQL);
	#die();
}


Function FGIID_BY_FGID_GET($iConSUS,$varDFGIID,$varFGID) {
	$varSQL = "
		SELECT
		FGIID
		FROM FTSSE.FIELD_GROUP_INSTANCE
		WHERE DOCUMENTFGIID = ".MySQLI_Real_Escape_String($iConSUS,$varDFGIID)."
		AND FGID = '".MySQLI_Real_Escape_String($iConSUS,$varFGID)."'";
	$RSMain = ExecuteIQuery($varSQL,$iConSUS);
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		REturn $LineMain["FGIID"];
	}
	Return False;
}


function WATCHDOG_WORKFLOW_MASTER_GET($iConRLM,$bolDebug=false) {
	$strSQL = "
		SELECT
		STEP_NAME,
		NEXT,
		CAN_WF_REMOVE
		FROM TEST.SUS_CONSOL_WORKFLOW_MASTER
		WHERE !ISNULL(STEP_NAME)
		AND TRIM(STEP_NAME) != '';";
	if($bolDebug === true) {
		echo $strSQL."<BR>";
	}
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryWFM = array();
	WHile ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		$strStepName = $LineMain["STEP_NAME"];

		$aryTemp = $LineMain;
		unset($aryTemp["STEP_NAME"]);

		$aryWFM[$strStepName] = $aryTemp;
	}
	return $aryWFM;
}


Function ENGINEER_DO($iConRLM,$iConSUSProd,$arySCM,$varStepPrev) {
	Echo __FUNCTION__."\n";

	#Echo "\n".Implode(",",Array_keys($arySCM))."\n";
	#die();
	#Echo Count($arySCM)."\n";
	#die();

	$varCtr = 0;
	ForEach($arySCM as $keySCMID => $valueSCM) {
		If(WORKFLOW_OK($iConRLM,$keySCMID,$varStepPrev,"COMPLETE")) {
			WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"START");
			try {

				$varDFGIIDProd = $valueSCM["DFGIID_PROD"];

				Echo Chr(9).$keySCMID." / ".$varDFGIIDProd."...";
				

				$varURL = "http://afotcosj004.maxim-ic.com/API/SUS_RESPONSIBILITY_001.PHP?DOCUMENTFGIID=".URLEncode($varDFGIIDProd);
				#echo $varURL."\n";
				$jsnEng = file_get_contents($varURL);
				$aryEng = JSON_Decode($jsnEng,True);

				#2020-01-24 RM must have CORE_DIE (domain for internal is not sufficient) fail validation if so
				#2020-01-24 RM disabled this
				/*
				If(!IsSet($aryEng["CORE_DIE"])) {
					throw new exception("CORE_DIE not found in engineer assignment");
				}
				*/

				#Print_R($aryEng);
				#die();
				#Echo $varCommand."\n";
				#2019-12-06 RM need to empty our $arySUS if processing > 1 SUS (which you will)
				#otherwise moncif gets attached to a bunch of SUS he should not
				$arySUS = Array();

				#Print_R($aryEng["TDE"]);
				#die();

				$aryTry = Array("TDE","TDD","DE");
				ForEach($aryTry as $valueTry) {
					If(IsSet($aryEng[$varDFGIIDProd][$valueTry])) {
						ForEach($aryEng[$varDFGIIDProd][$valueTry] as $keyEN => $valueEng) {
							Switch (True) {
								Case $valueTry == "TDE":
									$arySUS["Eng"]["TestEng"][] = $valueEng["EMPLOYEE_NAME"];
									$arySUS["Eng"]["TestEngPhone"][] = $valueEng["WORKPHONE"];
									Break;
								Case $valueTry == "TDD":
									$arySUS["Eng"]["TestEngDir"][] = $valueEng["EMPLOYEE_NAME"];
									Break;
								Case $valueTry == "DE":
									$arySUS["Eng"]["Designer"][] = $valueEng["EMPLOYEE_NAME"];
									Break;
							}		
						}
					}else{
						throw new exception($valueTry." not found for ".$varDFGIIDProd);
					}
				}

				#Print_R($arySUS["Eng"]);
				#die();

				$aryDefault = Array();
				$aryDefault["Eng"] = Array("TestEng"=>"Unknown","TestEngPhone"=>"000-0000","TestEngDir"=>"Unknown","Designer"=>"Unknown");
				ForEach(Array_keys($aryDefault) as $keyFGID) {
					ForEach($aryDefault[$keyFGID] as $keyFID => $valueDefault) {
						Switch (True) {
							Case IsSet($arySUS[$keyFGID][$keyFID]) && Count($arySUS[$keyFGID][$keyFID]) == 1:
								Break;
							Case !IsSet($arySUS[$keyFGID][$keyFID]) || Count($arySUS[$keyFGID][$keyFID]) == 0:
								$arySUS[$keyFID][] = $valueDefault;
								Break;
							Default:
								$aryThis = $arySUS[$keyFGID][$keyFID];
								$aryThat = Array();
								ForEach($aryThis as $valueThis) {
									$aryThat[] = $valueThis;
									Break;
								}
								$arySUS[$keyFGID][$keyFID] = $aryThat;
								Break;
						}
					}
				}

				#Print_R($arySUS);
				#die();
				SUS_SPLICE($iConSUSProd,$varDFGIIDProd,$arySUS);

				Echo "DONE.\n";
				#Break;
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"COMPLETE");

				++$varCtr;
				#If($varCtr >= 20) {
				#	Break;
				#}
			} catch (exception $e) {
				$varMessage = $e->getMessage();
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"FAILURE",$varMessage);
				Echo $varMessage."\n";
				#ErrorHandler($e,True);
			}

		}Else{
			Echo "DID NOT PASS PREVIOUS STEP (".$varStepPrev.")";
		}
	}

	
	Echo "DONE.\n";
}


Function CHANGEDESC_DO($iConRLM,$iConSUSProd,$aryFIDDesc,$arySCM,$strStepPrev) {
	Echo __FUNCTION__." START.\n";

	#Echo "\n".Implode(",",Array_keys($arySCM))."\n";
	#die();
	#Echo Count($arySCM)."\n";
	#die();

	#Print_R($arySCM);
	#die();

	$varCtr = 0;
	ForEach($arySCM as $keySCMID => $valueSCM) {
		If(WORKFLOW_OK($iConRLM,$keySCMID,$strStepPrev,"COMPLETE")) {
			WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"START");

			try {
				$intDFGIIDProd = $valueSCM["DFGIID_PROD"];
				Echo Chr(9).$keySCMID." / ".$intDFGIIDProd."...";

				$strChangeDesc = "";
				$strChangeReason = "";

				
				if($valueSCM["REV"] == "A") {
					#2020-03-27 RM
					$strChangeDesc = "(watchdog) Initial conversion from Maxcim";
					$strChangeReason = "(watchdog) Initial conversion from Maxcim";
#					WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"COMPLETE");
#					Echo chr(9).$keySCMID." / REV A, NO CHANGES.\n";
				}else{
					#find previous max rev
					$strSQL = "
						SELECT
						DFGIID_PROD
						FROM TEST.SUS_CONSOL_MASTER SCM
						INNER JOIN 
						(
							SELECT
							MAX(SCM_ID) SCM_ID
							FROM TEST.SUS_CONSOL_MASTER
							WHERE IS_GOOD = 1
							AND DOCNUM =
							(
								SELECT
								DOCNUM
								FROM TEST.SUS_CONSOL_MASTER
								WHERE SCM_ID = ".MySQLI_Real_Escape_String($iConRLM,$keySCMID)."
							)
							AND REV != '".MySQLI_Real_Escape_String($iConRLM,$valueSCM["REV"])."'
						) SCM_MAX ON SCM.SCM_ID = SCM_MAX.SCM_ID";
					$RSMain = ExecuteIQuery($strSQL,$iConRLM);
					$intDFGIIDProdPrev = 0;
					While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
						$intDFGIIDProdPrev = $LineMain["DFGIID_PROD"];
					}

					Echo $intDFGIIDProdPrev."...";


					if($intDFGIIDProdPrev != 0) {
						$arySUSThis = SUS_DATA_GET_V2($iConSUSProd,$intDFGIIDProd);
						$arySUSThis = ARRAY_KEY_VALUE_TO_UCASE_SORT_V2($arySUSThis);

						$aryFGID2FID = Array();
						$aryFGID2FID = FGID_TO_FID_GET($iConSUSProd,$aryFGID2FID,$intDFGIIDProd);

						$arySUSThat = SUS_DATA_GET_V2($iConSUSProd,$intDFGIIDProdPrev);
						$arySUSThat = ARRAY_KEY_VALUE_TO_UCASE_SORT_V2($arySUSThat);

						$aryFGID2FID = FGID_TO_FID_GET($iConSUSProd,$aryFGID2FID,$intDFGIIDProdPrev);

						#2020-02-24 RM fill in missing (or blank) fields existing in one but not the other
						$arySUSThis = MISSING_FID_FILL($arySUSThis,$aryFGID2FID);
						$arySUSThat = MISSING_FID_FILL($arySUSThat,$aryFGID2FID);


						#Print_R(array_keys($arySUSThat["TESTOPT_MULTI"]));
						#die();
						$aryIgnore = Array("PROGRAM2","BINDESCSTEP","BINNUMSTEP","BINTYPESTEP");
						$aryDelta = DELTA_GET_FROM_SUS_V2($arySUSThis,$arySUSThat,$aryIgnore);
						#Print_R($aryDelta);
						#die();
						$aryChangeDesc = CHANGEDESC_FROM_DELTA_GET_V2($aryDelta,$aryFIDDesc);
						
						$strChangeDesc = implode("\n",$aryChangeDesc);

						if(strlen($strChangeDesc) > 500) {
							$strChangeDesc = SubStr($strChangeDesc,0,493)."...more";
						}
					}
				}
				#echo $strChangeDesc."\n";
				#echo $strChangeReason."\n";
				#echo strlen($strChangeDesc)."\n";
				#die();

				if($strChangeDesc != "") {
					$arySUS = Array();

					$strSQL = "
						SELECT
						FGIID
						FROM FTSSE.FIELD_GROUP_INSTANCE
						WHERE DOCUMENTFGIID = ".MySQLI_Real_Escape_String($iConSUSProd,$intDFGIIDProd)."
						AND FGID = 'CHANGE'";
					$RSMain = ExecuteIQuery($strSQL,$iConSUSProd);
					While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
						$intFGIID = $LineMain["FGIID"];

						$arySUS["Change"][$intFGIID]["ChngDesc"] = Array($strChangeDesc);
					
						if($strChangeReason != "") {
							if(strlen($strChangeReason) > 500) {
								$strChangeReason = substr($strChangeReason,0,493)."...more";
							}
							$arySUS["Change"][$intFGIID]["ChngReason"] = Array($strChangeReason);
						}
					}

					
					$strSQL = "
						SELECT
						MAX(FGIID) FGIID
						FROM FTSSE.FIELD_GROUP_INSTANCE
						WHERE DOCUMENTFGIID = ".MySQLI_ReaL_Escape_String($iConSUSProd,$intDFGIIDProd)."
						AND FGID = 'RevHstry'";
					$RSMain = ExecuteIQuery($strSQL,$iConSUSProd);
					While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
						$intFGIID = $LineMain["FGIID"];

						$arySUS["RevHstry"][$intFGIID]["RHChanges"] = Array($strChangeDesc);
					}

					#Print_R($arySUS);

					SUS_SPLICE_LONG_BY_FGIID($iConSUSProd,$arySUS);
				}else{
					throw new exception("computed changedesc is blank");
				}

				
				SUS_SPLICE_LONG_BY_FGIID($iConSUSProd,$arySUS);

				#Break;
				++$varCtr;
				If($varCtr >= 20) {
				#	Break;
				}
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"COMPLETE");

				Echo "DONE.\n";
			} catch (exception $e) {
				$varMessage = $e->getMessage();
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"FAILURE",$varMessage);
				Echo $varMessage."\n";
				#ErrorHandler($e,True);
			}

		}Else{
			Echo "DID NOT PASS PREVIOUS STEP (".$strStepPrev.")";
		}
	}

	
	Echo __FUNCTION__." DONE.\n";
}


Function WORKFLOW_OK($iConRLM,$varSCMID,$varStepName,$varStepStatus) {
	$varSQL = "
		SELECT
		SC_WORKFLOW_ID
		FROM TEST.V_SUS_CONSOL_WORKFLOW
		WHERE SCM_ID = '".MySQLI_ReaL_Escape_String($iConRLM,$varSCMID)."'
		AND STEP_NAME = '".MySQLI_Real_Escape_String($iConRLM,$varStepName)."'
		AND STEP_STATUS = '".MySQLI_Real_Escape_String($iConRLM,$varStepStatus)."'";
	$RSMain = ExecuteIQuery($varSQL,$iConRLM);
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		Return True;
	}
	Return False;
}


Function WORKFLOW_DO($iConRLM,$varSCMID,$varStepName,$varStepStatus,$varStepDetail="") {
	$aryTemp 				= Array();
	$aryTemp["SCM_ID"] 		= $varSCMID;
	$aryTemp["STEP_NAME"] 	= $varStepName;
	$aryTemp["STEP_STATUS"] = $varStepStatus;
	$aryTemp["STEP_DETAIL"]	= $varStepDetail;

	ForEach($aryTemp as $keyTemp => $valueTemp) {
		$aryTemp[$keyTemp] = "'".MySQLI_Real_Escape_String($iConRLM,Trim($valueTemp))."'";
	}


	$varSQL = "
		INSERT INTO
		TEST.SUS_CONSOL_WORKFLOW
		(SCM_ID,STEP_NAME,STEP_STATUS,STEP_DETAIL,STEP_DT)
		VALUES
		(".Implode(",",$aryTemp).",NOW())";
	ExecuteIQuery($varSQL,$iConRLM);
}


Function WORKFLOW_REMOVE($iConRLM,$intSCMID,$strStepName,$bolDebug=false) {
	echo __FUNCTION__." / ".$intSCMID." / ".$strStepName."...";
	#clear out workflow entries (complete, started, anything) given a SCM_ID and STEP_NAME
	
	try{
		$strSQL = "
			SELECT
			COUNT(SC_WORKFLOW_ID) RECORDCOUNT
			FROM TEST.SUS_CONSOL_WORKFLOW
			WHERE SCM_ID = ".MySQLI_Real_Escape_String($iConRLM,$intSCMID)."
			AND STEP_NAME = '".MySQLI_Real_Escape_String($iConRLM,$strStepName)."'";
		if($bolDebug === true) {
			echo $strSQL."<BR>";
		}
		$RSMain = ExecuteIQuery($strSQL,$iConRLM);
		$LineMain = MySQLI_Fetch_Assoc($RSMain);
		if($LineMain["RECORDCOUNT"] > 0) {
			$strSQL = "
				DELETE
				FROM TEST.SUS_CONSOL_WORKFLOW
				WHERE SCM_ID = ".MySQLI_Real_Escape_String($iConRLM,$intSCMID)."
				AND STEP_NAME = '".MySQLI_Real_Escape_String($iConRLM,$strStepName)."'";
			if($bolDebug === true) {
				echo $strSQL."<BR>";
			}
			ExecuteIQuery($strSQL,$iConRLM);

			echo "DONE!<BR>";
			return true;
		}else{
			return false;
		}
	} catch (exception $e) {
		echo "failed.<BR>";
		return false;
	}
}



Function PKGHW_GET($iConSUS,$varDFGIID) {
	$aryReturn = Array();

	$aryTry = Array('PKG','HANDLER','SITES','CNTMTHD');

	$varSQL = "
		SELECT DISTINCT
		PKGHW.FGIID,
		PKG.SHORTVALUE PKG,
		HANDLER.SHORTVALUE HANDLER,
		SITES.SHORTVALUE SITES,
		CNTMTHD.SHORTVALUE CNTMTHD
		FROM FTSSE.FIELD_GROUP_INSTANCE PKGHW
		LEFT JOIN FTSSE.FIELD_INSTANCE PKG ON PKGHW.FGIID = PKG.CONTAININGFGIID AND PKG.FID = 'PKG'
		LEFT JOIN FTSSE.FIELD_INSTANCE HANDLER ON PKGHW.FGIID = HANDLER.CONTAININGFGIID AND HANDLER.FID = 'HANDLER'
		LEFT JOIN FTSSE.FIELD_INSTANCE SITES ON PKGHW.FGIID = SITES.CONTAININGFGIID AND SITES.FID = 'SITES'
		LEFT JOIN FTSSE.FIELD_INSTANCE CNTMTHD ON PKGHW.FGIID = CNTMTHD.CONTAININGFGIID AND CNTMTHD.FID = 'CNTMTHD'
		WHERE PKGHW.FGID IN ('PKGHW','PKGHW2','PKGHWWS')
		AND PKGHW.DOCUMENTFGIID = ".MySQLI_Real_Escape_String($iConSUS,$varDFGIID);
	$RSMain = ExecuteIQuery($varSQL,$iConSUS);
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		If(!IsSet($aryReturn[$LineMain["FGIID"]])) {
			$aryTemp 			= Array();
			ForEach($aryTry as $valueTry) {
				$aryTemp[$valueTry] = Array();
			}

			$aryReturn[$LineMain["FGIID"]] = $aryTemp;
		}

		ForEach($aryTry as $valueTry) {
			Switch (True) {
				Case Is_Null($LineMain[$valueTry]):
					Break;
				Case Trim($LineMain[$valueTry]) == "":
					Break;
				Default:
					$aryReturn[$LineMain["FGIID"]][$valueTry][] = $LineMain[$valueTry];
					Break;
			}
		}
	}

	Return $aryReturn;
}



Function ECNIDEN_GET($iConSUS,$varDFGIID) {
	$varSQL = "
		SELECT
		IF(DOCID.LONGVALUE!='',DOCID.LONGVALUE,DOCID.SHORTVALUE) DOCID,
		IF(NEWECNREV.LONGVALUE!='',NEWECNREV.LONGVALUE,NEWECNREV.SHORTVALUE) NEWECNREV,
		IF(OLDECNREV.LONGVALUE!='',OLDECNREV.LONGVALUE,OLDECNREV.SHORTVALUE) OLDECNREV,
		IF(ECNNUM.LONGVALUE!='',ECNNUM.LONGVALUE,ECNNUM.SHORTVALUE) ECNNUM,
		IF(DCRECID.LONGVALUE!='',DCRECID.LONGVALUE,DCRECID.SHORTVALUE) DCRECID,
		IF(EFFDATE.LONGVALUE!='',EFFDATE.LONGVALUE,EFFDATE.SHORTVALUE) EFFDATE
		FROM (((((FTSSE.FIELD_GROUP_INSTANCE ECNIDEN
		LEFT JOIN FTSSE.FIELD_INSTANCE DOCID ON ECNIDEN.FGIID = DOCID.CONTAININGFGIID AND DOCID.fID = 'DOCID')
		LEFT JOIN FTSSE.FIELD_INSTANCE NEWECNREV ON ECNIDEN.FGIID = NEWECNREV.CONTAININGFGIID AND NEWECNREV.FID = 'NEWECNREV')
		LEFT JOIN FTSSE.FIELD_INSTANCE OLDECNREV ON ECNIDEN.FGIID = OLDECNREV.CONTAININGFGIID AND OLDECNREV.FID = 'OLDECNREV')
		LEFT JOIN FTSSE.FIELD_INSTANCE ECNNUM ON ECNIDEN.FGIID = ECNNUM.CONTAININGFGIID AND ECNNUM.FID = 'ECNNUM')
		LEFT JOIN FTSSE.FIELD_INSTANCE DCRECID ON ECNIDEN.FGIID = DCRECID.CONTAININGFGIID AND DCRECID.FID = 'DCRECID')
		LEFT JOIN FTSSE.FIELD_INSTANCE EFFDATE ON ECNIDEN.FGIID = EFFDATE.CONTAININGFGIID AND EFFDATE.FID = 'EFFDATE'
		WHERE ECNIDEN.FGID = 'ECNIDEN'
		AND ECNIDEN.DOCUMENTFGIID = '".MySQLI_Real_Escape_String($iConSUS,$varDFGIID)."'";
	$RSMain = ExecuteIQuery($varSQL,$iConSUS);
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		Return $LineMain;
	}

	Return False;
}


Function PREV_REV_DFGIID_FOR_MAIL_GET($iConSUS,$aryECNIden) {

	$aryTry = Array("DOCID","NEWECNREV","OLDECNREV");
	$varOK = True;
	ForEach($aryTry as $valueTry) {
		Switch (True) {
			Case !IsSet($aryECNIden[$valueTry]):
				$varOK = False;
				Break;
			Case Trim($aryECNIden[$valueTry]) == "":
				$varOK = False;
				Break;
			Default:
				#do nothing
				Break;
		}
	}

	If($varOK === True) {
		$varSQL = "
			SELECT
			DOCUMENTFGIID
			FROM FTSSE.LATEST_DC_STATUS
			WHERE DOCNUM = '".MySQLI_Real_Escape_String($iConSUS,$aryECNIden["DOCID"])."'
			AND REV = '".MySQLI_Real_Escape_String($iConSUS,$aryECNIden["OLDECNREV"])."'";
		$RSMain = ExecuteIQuery($varSQL,$iConSUS);
		While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
			Return $LineMain["DOCUMENTFGIID"];
		}

		#2020-10-26 RM additional catch if no entry in latest_dc_status
		$strSQL = "
			SELECT DISTINCT
			ECNIDEN.DOCUMENTFGIID,
			DATE_FORMAT(LASTEDITDT.SHORTVALUE,'%Y-%m-%d %H:%i:%s') LASTEDITDT
			FROM FTSSE.FIELD_GROUP_INSTANCE SUSTYPE
			INNER JOIN FTSSE.FIELD_GROUP_INSTANCE ECNIDEN ON SUSTYPE.FGIID = ECNIDEN.CONTAININGFGIID AND ECNIDEN.FGID = 'ECNIDEN'
			INNER JOIN FTSSE.FIELD_GROUP_INSTANCE EDITINFO ON SUSTYPE.FGIID = EDITINFO.CONTAININGFGIID AND EDITINFO.FGID = 'EDITINFO'
			INNER JOIN FTSSE.FIELD_INSTANCE DOCID ON ECNIDEN.FGIID = DOCID.CONTAININGFGIID AND DOCID.FID = 'DOCID'
			INNER JOIN FTSSE.FIELD_INSTANCE NEWECNREV ON ECNIDEN.FGIID = NEWECNREV.CONTAININGFGIID AND NEWECNREV.FID = 'NEWECNREV'
			INNER JOIN FTSSE.FIELD_INSTANCE LASTEDITDT ON EDITINFO.FGIID = LASTEDITDT.CONTAININGFGIID AND LASTEDITDT.FID = 'LASTEDITDT'
			WHERE DOCID.SHORTVALUE = '".MySQLI_Real_Escape_String($iConSUS,$aryECNIden["DOCID"])."'
			AND NEWECNREV.SHORTVALUE = '".MySQLI_Real_Escape_String($iConSUS,$aryECNIden["OLDECNREV"])."'
			ORDER BY LASTEDITDT DESC;";
		$RSMain = ExecuteIQuery($strSQL,$iConSUS);
		While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
			Return $LineMain["DOCUMENTFGIID"];
		}
	}
	Return False;
}



Function ALIAS_GET($iConSUS) {
	$varSQL = "
		SELECT
		UCASE(FGID) FIELD_NAME,
		FGID_DESC FIELD_NAME_ENGLISH
		FROM FTSSE.FGID_DESC
		WHERE !ISNULL(FGID_DESC)
		AND TRIM(FGID_DESC) != ''
		UNION
		SELECT
		UCASE(FID) FIELD_NAME,
		FID_DESC
		FROM FTSSE.FID_DESC
		WHERE !ISNULL(FID_DESC)
		AND TRIM(FID_DESC) != ''";
	$RSMain = ExecuteIQuery($varSQL,$iConSUS);
	$aryAlias = Array();
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		$aryAlias[$LineMain["FIELD_NAME"]] = $LineMain["FIELD_NAME_ENGLISH"];
	}
	REturn $aryAlias;
}




Function DOCCO_SEND($iConSUSProd,$iConRLM,$iConSJ004,$arySCM,$bolDebug=false) {
	Echo __FUNCTION__.".\n";

	if($bolDebug === true) {
		Print_R($arySCM);
	}
	#die();

	ForEach($arySCM as $keySCMID => $valueSCM) {
		Echo Chr(9).$keySCMID." / ".$valueSCM["DOCNUM"]." rev. ".$valueSCM["REV"]." (".$valueSCM["DFGIID_PROD"].")...";
		
		WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"START");

		#make sure you include the engineer (by AD NAME)
		#or dont
		#DOCCO_SEND($iConSUSProd,$iConRLM,$iConRLM,$keySCMID,$valueSCM);
		#Echo "DONE.\n";
		
		try{
			$varDFGIIDThis = $valueSCM["DFGIID_PROD"];

			$arySUSMetadata = SUS_METADATA_GET($iConSUSProd,$varDFGIIDThis);

			$aryECNIden = ECNIDEN_GET($iConSUSProd,$varDFGIIDThis);
			$aryPKGHW = PKGHW_GET($iConSUSProd,$varDFGIIDThis);

			$aryTemp = Array();
			ForEach(Array_Keys($aryPKGHW) as $keyFGIID) {
				ForEach($aryPKGHW[$keyFGIID]["PKG"] as $value) {
					$aryTemp[] = $value;
				}
			}
			$aryTemp = Array_Unique($aryTemp);
			Sort($aryTemp);
			$aryPkg = $aryTemp;

			if($bolDebug === true) {
				echo $varDFGIIDThis."\n";
				Print_R($arySUSMetadata);
				Print_R($aryPkg);
			}
			#die();

			If(StrToUpper(Trim($aryECNIden["NEWECNREV"])) != "A") {
				$varDFGIIDThat = PREV_REV_DFGIID_FOR_MAIL_GET($iConSUSProd,$aryECNIden);
			}

			$aryAlias = ALIAS_GET($iConSUSProd);

			if($bolDebug === true) {
				echo $varDFGIIDThat."\n";
				Print_R($aryAlias);
			}
			

			#Print_R($arySUSMetadata);
			#die();

			$aryRRID = RRID_FROM_SCMID_GET($iConRLM,$keySCMID);
			$aryRRID = RRID_DATA_ATTACH($iConRLM,$aryRRID,$keySCMID,"CONSOL");


			$aryProduct = Array();
			ForEach(Array_keys($aryRRID) as $keyRRID) {
				$aryProduct[] = $aryRRID[$keyRRID]["PART_NUMBER"];
			}

			$aryThat = PRODUCT_DATA_GET($iConRLM,$aryProduct);
			$aryThis = PRODUCT_DATA_GET($iConSJ004,$aryProduct);
			ForEach(Array_Keys($aryThis) as $keyBISMT) {
				ForEach(Array_Keys($aryThis[$keyBISMT]) as $keyField) {
					ForEach($aryThis[$keyBISMT][$keyField] as $value) {
						$aryThat[$keyBISMT][$keyField][] = $value;
					}
				}
			}
			ForEach(Array_keys($aryThat) as $keyBISMT) {
				ForEach(Array_keys($aryThat[$keyBISMT]) as $keyField) {
					$aryThat[$keyBISMT][$keyField] = Array_Unique($aryThat[$keyBISMT][$keyField]);
					Sort($aryThat[$keyBISMT][$keyField]);
				}
			}
			$aryPD = $aryThat;


			if($bolDebug === true) {
				Print_R($aryRRID);
				Print_R($aryPD);
			}


			#die();
			$varSent = False;

			Switch (True) {
				Case Count($aryProduct) == 0:
					#2020-05-29 RM default to workflow system, dont throw an error otherwise the script will never progress
					WORKFLOW_DO($iConRLM,$keySCMID,"NO PRODUCT DATA","START");
					WORKFLOW_DO($iConRLM,$keySCMID,"NO PRODUCT DATA","COMPLETE");
					#throw new exception("no products found");
					Break;
				Case Count($aryPD) == 0:
					#2020-05-29 RM default to workflow system, dont throw an error otherwise the script will never progress
					WORKFLOW_DO($iConRLM,$keySCMID,"NO PRODUCT DATA","START");
					WORKFLOW_DO($iConRLM,$keySCMID,"NO PRODUCT DATA","COMPLETE");
					#throw new exception("no product data found");
					Break;
				#2019-11-20 RM removed check on $varDFGIIDThat, because this would kick out any initial rev (A)
				#Case $varDFGIIDThat === False:
				#	Break;
				Default:
					$aryECNSpec = MAXCIM_LAST_ECN_GET($iConRLM,$aryProduct);
					$varECNDesc = MAXCIM_ECN_LAST_DESCRIPTION_GET($iConRLM,$aryECNSpec);
					$varECNApproval = MAXCIM_ECN_MAX_APPROVAL_DATE_GET($iConRLM,$aryECNSpec);

					if($bolDebug === true) {
						Print_R($aryECNSpec);
						Echo $varECNDesc."\n";
						Echo $varECNApproval."\n";
					}

					ForEach(Array_Keys($arySUSMetadata["ECNIDEN"]) as $keyFGIIDEI) {
						$varDocNumRev = $arySUSMetadata["ECNIDEN"][$keyFGIIDEI]["DOCID"][0].$arySUSMetadata["ECNIDEN"][$keyFGIIDEI]["NEWECNREV"][0];
					}

					$aryTo = Array("reyn.mito@maximintegrated.com","mpoc.dcc@maximintegrated.com","primadonna.rogales@maximintegrated.com");
					#$aryTo = Array("reyn.mito@maximintegrated.com");
					$aryFrom = Array("Maxcim.SUS@maximintegrated.com");
					#$aryFrom = Array("reyn.mito@maximintegrated.com");

					$aryHeader = Array();
					$aryHeader[] = "MIME-Version: 1.0";
					$aryHeader[] = "Content-type:text/html;charset=UTF-8";
					$aryHeader[] = "Content-Transfer-Encoding: base64";
					$aryHeader[] = "From:".Implode(",",$aryFrom);

					$arySubject = Array();
					$arySubject[] = "New Internal Maxcim SUS: ".$varDocNumRev;


					$aryMessage = Array();
					$aryMessage[] = '<HTML><SPAN style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 9pt;">';

					
					$varPersistence = "Permanent";
					$aryET = $arySUSMetadata["ECNTYPE"];
					ForEach(Array_Keys($aryET) as $keyFGIIDET) {
						If(IsSet($aryET[$keyFGIIDET]["PERSISTENCE"])) {
							ForEach($aryET[$keyFGIIDET]["PERSISTENCE"] as $valuePersistence) {
								$varPersistence = $valuePersistence;
							}
						}
					}

					$aryMessage[] = "A new, ".$varPersistence." Maxcim Setup Sheet has been generated and is available here:";
					$aryMessage[] = "<BR>";


					$aryMessage[] = "<A HREF=\"http://testweb2.maxim-ic.com:8080/FTSSE/DocumentRO?refNum=".$varDFGIIDThis."\">".$varDocNumRev."</A>";
					$aryMessage[] = "<BR>";


					$varApprovalRouting = "";
					$varURL 			= "http://testweb2.maxim-ic.com/SUS_APPROVER/FTSSE_COMPARE_003.PHP?DOCUMENTFGIID=".$varDFGIIDThis."&NOSTYLE=1";
					$varApprovalRouting = file_get_contents($varURL);
					$aryMessage[] = $varApprovalRouting;

					#$aryMessage[] = "Please review and submit this document in accordance with Maxcim Setup Sheet Routing.";


					Switch (True) {
						Case StrToUpper(Trim($arySUSMetadata["ECNIDEN"][$keyFGIIDEI]["NEWECNREV"][0])) == "A":
							$aryMessage[] = "This is an initial revision (A).";
							Break;
						Case $varDFGIIDThat === False:
							$aryMessage[] = "This is a revision but no reference setup sheet was found.";
							Break;
						Default:

							#$aryMessage[] = "<A HREF=\"http://testweb2.maxim-ic.com/SUS_APPROVER/FTSSE_COMPARE_006.PHP?ACTION=COMPARE&DFGIID_THIS=".$varDFGIIDThis."&DFGIID_THAT=".$varDFGIIDThat."\">Compare</A> to previous rev (".$aryECNIden["OLDECNREV"].")";
							$strURL = "http://afotcosj004.maxim-ic.com/SUS_APPROVER/REV_COMPARISON_001.PHP?DFGIID_THIS=".$varDFGIIDThis."&DFGIID_THAT=".$varDFGIIDThat."&NO_SUPPLEMENT=1";
							$strCompare = file_get_contents($strURL);
							$aryMessage[] = $strCompare;


							$varURL = "http://testweb2.maxim-ic.com/SUS_APPROVER/FTSSE_COMPARE_006.PHP?ACTION=JSON_MAKE&DFGIID_THIS=".$varDFGIIDThis."&DFGIID_THAT=".$varDFGIIDThat;
							$jsnCompare = file_get_contents($varURL);
							$aryCompare = JSON_Decode($jsnCompare,True);

							$arySummary = Array();
							If(IsSet($aryCompare["PARTNUM"])) {
								$aryPartnum = $aryCompare["PARTNUM"];
								ForEach(Array_Keys($aryPartnum) as $keyPartnum) {
									ForEach(Array_Keys($aryPartnum[$keyPartnum]) as $keyChangeType) {
										$aryDesc = Array();

										ForEach(Array_Keys($aryPartnum[$keyPartnum][$keyChangeType]) as $keyChangeCtr) {
											$aryChange = $aryPartnum[$keyPartnum][$keyChangeType][$keyChangeCtr];
											$aryTemp = Array();

											if(is_array($aryChange)) {									
												ForEach($aryChange as $keyChange => $valueChange) {
													If($valueChange != "") {
														$aryTemp[] = $keyChange.": ".$valueChange;
													}
												}
											}

											$aryDesc[] = Implode(", ",$aryTemp);
										}

										$arySummary[$keyPartnum][$keyChangeType] = Implode("<BR>",$aryDesc);
									}
								}
							}


							If(Count($arySummary) > 0) {
								$aryThis = Array();
								$aryThis[] = "<TABLE BORDER=1 CELLSPACING=0 CELLPADDING=1>";
								$aryThis[] = "<THEAD BGCOLOR=SILVER><TR><TH>Partnum</TH><TH>Change Type</TH><TH>Change Desc</TH></TR></THEAD>";
								$aryThis[] = "<TBODY BGCOLOR=#FFFFFF>";

								ForEach(Array_Keys($arySummary) as $keyPartnum) {
									ForEach($arySummary[$keyPartnum] as $keyChangeType => $valueChangeDesc) {
										$aryThis[] = "<TR><TD>".$keyPartnum."</TD><TD>".$keyChangeType."</TD><TD>".$valueChangeDesc."</TD></TR>";
									}
								}

								$aryThis[] = "</TBODY></TABLE>";

								$aryMessage[] = Implode("\n",$aryThis);
								#Print_R($aryThis);
								#die();
							}
							Break;
					}


					$varFTSUSInfo 	= "";
					$varURL 		= "http://testweb2.maxim-ic.com/test/FTSUS_INFORMATION.PHP?DOCUMENTFGIID=".$varDFGIIDThis;
					$varFTSUSInfo 	= Str_Replace("\n","<BR>",file_get_contents($varURL));

					$aryMessage[] = "</SPAN>";
					$aryMessage[] = '<SPAN style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 12pt;">';
					$aryMessage[] = $varFTSUSInfo;
					$aryMessage[] = "</SPAN>";
					$aryMessage[] = '<SPAN style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 9pt;">';

					
					/*
					#2020-01-07 RM moved this to FTSSE_COMPARE_003
					$aryAlert = Array();
					If(IsSet($arySUSMetadata["IMPACT"]["DISTRIB"]) && Count($arySUSMetadata["IMPACT"]["DISTRIB"]) > 0) {
						$aryAlert[] = "This is an External Test Setup Sheet. Refer to Impact.Distribution below.";
					}Else{
						$aryAlert[] = "This is an Internal Test Setup Sheet.";
					}

					If(Count($aryAlert) > 0) {
						$aryMessage[] = "</SPAN>";

						$aryMessage[] = '<SPAN style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 14pt;">';
						$aryMessage[] = "<BR>".Implode("<BR>",$aryAlert);
						$aryMessage[] = "</SPAN>";

						$aryMessage[] = '<SPAN style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 9pt;">';
					}
					*/

					#die();
					$aryMessage[] = "<BR>";

					#2020-01-07 RM now referencing FTSUS_INFORMATION
					/*
					$varIsWS = True;
					ForEach($aryPkg as $valuePkg) {
						If(StrPos($valuePkg,"WAFER") === False) {
							$varIsWS = False;
							Break;
						}
					}

					If($varIsWS === True) {
						$aryMessage[] = "Test Type: WS_New";
					}Else{
						$aryMessage[] = "Test Type: FT_New";
					}

					$aryMessage[] = "<BR>";
					*/


					$arySetupIden = $arySUSMetadata["SETUPIDEN"];
					$aryTemp = Array();

					$aryPF = Array();
					ForEach(Array_Keys($aryPD) as $keyBISMT) {
						If(IsSet($aryPD[$keyBISMT]["PART_FAMILY"])) {
							ForEach($aryPD[$keyBISMT]["PART_FAMILY"] as $valuePF) {
								$aryPF[] = $valuePF;
							}
						}
					}
					$aryPF = Array_Unique($aryPF);
					Sort($aryPF);
					$aryTemp[] = "Part Family : ".Implode(" or ",$aryPF);


					ForEach(Array_keys($arySetupIden) as $keyFGIIDSI) {
						ForEach(Array_Keys($arySetupIden[$keyFGIIDSI]) as $keyFID) {
							$varFID = $keyFID;
							If(IsSet($aryAlias[$keyFID])) {
								$varFID = $aryAlias[$keyFID];
							}

							$aryTemp[] = $varFID." : ".Implode(" or ",$arySetupIden[$keyFGIIDSI][$keyFID]);
						}
						$aryMessage[] = $aryAlias["SETUPIDEN"].":<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".Implode("<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;",$aryTemp);
					}

					$aryMessage[] = "<BR>";

					If(IsSet($arySUSMetadata["IMPACT"])) {
						$aryImpact = $arySUSMetadata["IMPACT"];
						$aryTemp = Array();

						ForEach(Array_keys($aryImpact) as $keyFGIIDI) {
							ForEach(Array_Keys($aryImpact[$keyFGIIDI]) as $keyFID) {
								$varFID = $keyFID;
								If(IsSet($aryAlias[$keyFID])) {
									$varFID = $aryAlias[$keyFID];
								}

								$aryTemp[] = $varFID." : ".Implode(" or ",$aryImpact[$keyFGIIDI][$keyFID]);
							}
							$aryMessage[] = $aryAlias["IMPACT"].":<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".Implode("<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;",$aryTemp);
						}
					}
					$aryMessage[] = "<BR>";


					$aryRH = $arySUSMetadata["REVHSTRY"];
					#only get the LAST item
					ForEach(Array_Keys($aryRH) as $keyFGIIDRH) {

					}


					$aryTemp = Array();
					ForEach(Array_Keys($aryRH[$keyFGIIDRH]) as $keyFID) {
						$varFID = $keyFID;
						If(IsSet($aryAlias[$keyFID])) {
							$varFID = $aryAlias[$keyFID];
						}

						$aryTemp[] = $varFID." : ".Implode("",Str_Replace("\n","<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;",$aryRH[$keyFGIIDRH][$keyFID]));
					}
					$aryMessage[] = "Revision History<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".Implode("<BR>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;",$aryTemp);
					$aryMessage[] = "<BR>";

					$aryMessage[] = "This message was created automatically on behalf of:</SPAN>";


					$aryMessage[] = '
			<table class="default" border="0" cellpadding="5" cellspacing="0" width="100%">
				<tr>
					<td width="5%"><a href="https://www.maximintegrated.com"><img src="https://www.maximintegrated.com/content/dam/images/email/logo-email-footer.png"></a></td>
					<td width="95%" align="left" style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 9pt;">Reyn Mito<br>Senior Principal Analyst, IT<br>160 Rio Robles, San Jose, CA 95134 | USA<br>Office: + 1 (408) 601-5175 | Mobile: +1 (408) 910-3031</td>
				</tr>
			</table>
			<table class="default" border="0" cellpadding="5" cellspacing="0" width="100%">
				<tr>
					<td style="font-family: Arial, Helvetica, Verdana, sans-serif; font-size: 9pt;" align="left"><font style="color: #00b2a9;">Maxim Integrated</font> | <font style="color: #6e2585;">Empowering Design Innovation</font> | <font style="color: #00b2a9;">www.maximintegrated.com</font></td>
				</tr>
			</table>';

					$aryMessage[] = "</HTML>";


					/*
					Echo Implode(",",$aryTo)."\n";
					Echo $varSubject."\n";
					Echo Implode("<BR>",$aryMessage)."\n";
					Echo Implode("\n",$aryHeader)."\n";
					die();
					*/

					#Print_R($aryMessage);
					#die();

					$varMessage = chunk_split(base64_encode(Implode("<BR>",$aryMessage)));
					
					/*
					$File = FOpen("/home/rmito/mail.txt","a");
					FWrite($File,Date("Y-m-d H:i:s",Time())."\n");
					FWrite($File,Print_R($aryTo,True));
					FWrite($File,Print_R($arySubject,True));
					FWrite($File,Print_R($aryHeader,True));
					FWrite($File,Print_R($aryMessage,True));
					fwrite($File,$varMessage."\n");
					FCLose($File);
					*/

					#echo "HI";
					#die();

					mail(
						Implode(",",$aryTo),
						Implode(",",$arySubject),
						$varMessage,
						Implode("\n",$aryHeader)
					);

					$varSent = True;
					#Print_R($aryMessage);
					Break;
					
			}

			If($varSent === True) {
				Echo "SENT!\n";
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"COMPLETE");
			}Else{
				Echo "NOT SENT!\n";
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"FAILURE");
			}
		} catch (exception $e) {
			Echo $e->getMessage();
			WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"FAILURE",$e->getMessage());
			ErrorHandler(Print_R($e->getMessage()));
		}
	}
	#die();

	Echo "DONE.\n";
}



Function PRODUCT_DATA_GET($iConRLM,$aryProduct) {
	$aryTemp = Array_Map(Array($iConRLM,'real_escape_string'),$aryProduct);

	$varSQL = "
		SELECT DISTINCT
		BISMT,
		FIELD_NAME,
		FIELD_VALUE
		FROM TEST.SUS_COMBINE_PRODUCT_DATA
		WHERE BISMT IN ('".Implode("','",$aryTemp)."')";
	$RSMain = ExecuteIQuery($varSQL,$iConRLM);
	$aryPD = Array();
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		$aryPD[$LineMain["BISMT"]][$LineMain["FIELD_NAME"]][] = $LineMain["FIELD_VALUE"];
	}
	Return $aryPD;
}



Function SCM_BY_WORKFLOW_GET($iConRLM,$varStepName) {
	$varSQL = "
		SELECT DISTINCT
		SCM.SCM_ID,
		SCM.DOCNUM,
		SCM.REV,
		SCM.DFGIID_DEV,
		SCM.DFGIID_PROD,
		WF.STEP_NAME
		FROM TEST.V_SUS_CONSOL_WORKFLOW WF
		INNER JOIN 
		(
			SELECT
			MAX(SC_WORKFLOW_ID) SC_WORKFLOW_ID
			FROM TEST.SUS_CONSOL_WORKFLOW
			WHERE STEP_STATUS = 'COMPLETE'
			GROUP BY SCM_ID
		) WF_MAX ON WF.SC_WORKFLOW_ID = WF_MAX.SC_WORKFLOW_ID
		INNER JOIN TEST.SUS_CONSOL_MASTER SCM ON WF.SCM_ID = SCM.SCM_ID
		WHERE SCM.IS_GOOD = 1
		AND WF.STEP_NAME = '".MySQLI_ReaL_Escape_String($iConRLM,$varStepName)."'";
	#Echo $varSQL."\n";
	$RSMain = ExecuteIQuery($varSQL,$iConRLM);
	$arySCM = Array();
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		$aryTemp = $LineMain;
		Unset($aryTemp["SCM_ID"]);
		$arySCM[$LineMain["SCM_ID"]] = $aryTemp;
	}
	return $arySCM;
}



Function SUS_METADATA_GET($iConSUS,$varDFGIID) {
	$aryTry = Array();
	$aryTry["ECNIDEN"] = Array(
		"FGID"=>Array("ECNIDEN"),
		"FID"=>Array(
			"DOCID",
			"NEWECNREV",
			"OLDECNREV"
		)
	);

	$aryTry["SETUPIDEN"] = Array(
		"FGID"=>Array("SETUPIDEN"),
		"FID"=>Array(
			"EXTPARTFAM",
			"DIETYPE",
			"CUSTSPEC",
			"TESTER",
			"PROGRAMREV"
		)
	);
	$aryTry["ECNTYPE"] = Array(
		"FGID"=>Array("ECNTYPE"),
		"FID"=>Array(
			"883",
			"ITAR",
			"EMRGNCY",
			"EXPDATE",
			"EXPLOTS",
			"INITIAL"
		)
	);
	$aryTry["IMPACT"] = Array(
		"FGID"=>Array("IMPACT"),
		"FID"=>Array("DISTRIB")
	);
	$aryTry["REVHSTRY"] = Array(
		"FGID"=>Array("REVHSTRY"),
		"FID"=>Array(
			"RHREV",
			"RHDATE",
			"RHCHANGES",
			"RHORGNTR"
		)
	);

	$arySUSMetadata = Array();
	ForEach(Array_Keys($aryTry) as $keyGroup) {
		$aryFGID = Array_Map(Array($iConSUS,'real_escape_string'),$aryTry[$keyGroup]["FGID"]);
		$aryFID = Array_Map(Array($iConSUS,'real_escape_string'),$aryTry[$keyGroup]["FID"]);

		
		$varSQL = "
			SELECT DISTINCT
			FGI.FGIID,
			UCASE(FID) FID,
			IF(
				LONGVALUE != '',
				LONGVALUE,
				SHORTVALUE
			) VALUE
			FROM FTSSE.FIELD_GROUP_INSTANCE FGI
			INNER JOIN FTSSE.FIELD_INSTANCE FI ON FGI.FGIID = FI.CONTAININGFGIID AND FI.FID IN ('".Implode("','",$aryFID)."')
			WHERE FGI.FGID IN ('".Implode("','",$aryFGID)."')
			AND FGI.DOCUMENTFGIID = ".MySQLI_Real_Escape_String($iConSUS,$varDFGIID)."
			ORDER BY FGISEQUENCENUM, FISEQUENCENUM";
		#Echo $varSQL."\n";
		#die();
		$RSMain = ExecuteIQuery($varSQL,$iConSUS);
		While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
			Switch (True) {
				Case $LineMain["FID"] == "DISTRIB":
					$aryTemp = Explode(",",$LineMain["VALUE"]);

					ForEach($aryTemp as $valueTemp) {
						$arySUSMetadata[$keyGroup][$LineMain["FGIID"]][$LineMain["FID"]][] = Trim($valueTemp);
					}

					Break;
				Default:
					$arySUSMetadata[$keyGroup][$LineMain["FGIID"]][$LineMain["FID"]][] = $LineMain["VALUE"];
					Break;
			}
		}
	}



	#special care taken for emergency/temporary
	$aryET = $arySUSMetadata["ECNTYPE"];
	ForEach(Array_Keys($aryET) as $keyFGIIDET) {
		Switch (True) {
			Case IsSet($aryET[$keyFGIIDET]["EMRGNCY"]):
				$aryET[$keyFGIIDET]["PERSISTENCE"] = "Emergency";
				Break;
			Case IsSet($aryET[$keyFGIIDET]["EXPDATE"]) || IsSet($aryET[$keyFGIIDET]["EXPLOTS"]):
				$aryET[$keyFGIIDET]["PERSISTENCE"] = "Temporary";
				Break;
			Default:
				$aryET[$keyFGIIDET]["PERSISTENCE"] = "Permanent";
				Break;
		}
	}

	#Print_R($arySUSMetadata);
	#die();

	Return $arySUSMetadata;
}




Function DEV_TO_PROD_PUT($iConRLM,$arySCM,$varStepPrev) {
	Echo __FUNCTION__."\n";

	#Echo "\n".Implode(",",Array_keys($arySCM))."\n";
	#die();
	#Echo Count($arySCM)."\n";
	#die();

	try {
		#$ssh2This = ssh2_connect("afodevsvr.maxim-ic.com");
		#SSH2_Auth_Password($ssh2This,"reynmito","reynmito") or ErrorHandler("unable to authenticate");

		$varCtr = 0;
		ForEach($arySCM as $keySCMID => $valueSCM) {
			If(WORKFLOW_OK($iConRLM,$keySCMID,$varStepPrev,"COMPLETE")) {

				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"START");

				Echo Chr(9).$keySCMID." / ";
				$varDFGIID = $valueSCM["DFGIID_DEV"];

				#2020-01-07 RM replacement here
				$varURL = "http://afodevsvr.maxim-ic.com/SCRIPTS/MAXCIM_CONVERSION/SUS_TRANSFER_FROM_DEV_TO_PROD_AUTO_INTERNAL.PHP?DFGIID=".$varDFGIID."&AUTH_KEY=PENTESTWEBSCRAPERSARESTUPIDANDUSELESS";
				$varResponse = file_get_contents($varURL);
				Echo $varResponse."\n";


				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"COMPLETE");
			

				#Break;
				++$varCtr;
				If($varCtr >= 20) {
				#	Break;
				}
			}Else{
				Echo "DID NOT PASS PREVIOUS STEP (".$varStepPrev.")";
			}
		}

		#SSH2_Exec($ssh2This,"exit");
		#Unset($ssh2This);
	} catch (exception $e) {
		WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"FAILURE",$e->getMessage());
		Echo $e->getMessage();
		ErrorHandler($e,True);
	}

	Echo "DONE.\n";
}


Function SCM_VALIDATED_OK_GET($iConRLM,$varStepPrev) {
	Echo __FUNCTION__."...";

	$arySQL = Array();

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY,
		TEST.TEST_002_TEMPORARY,
		TEST.TEST_003_TEMPORARY;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_001_TEMPORARY
		(	
			INDEX IDX1 (SCM_ID)
		)
		SELECT
		MAX(SCM_ID) SCM_ID
		FROM TEST.SUS_CONSOL_MASTER SCM INNER JOIN
		(
			SELECT
			DOCNUM,
			MAX(REV) REV
			FROM TEST.SUS_CONSOL_MASTER 
			WHERE IS_GOOD = 1
			GROUP BY DOCNUM
		) SCM_MAX
		ON SCM.DOCNUM = SCM_MAX.DOCNUM AND SCM.REV = SCM_MAX.REV
		WHERE SCM.IS_GOOD = 1
		AND !ISNULL(DFGIID_DEV)	
		AND ISNULL(DFGIID_PROD)
		GROUP BY SCM.DOCNUM, SCM.REV;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_002_TEMPORARY
		(
			INDEX IDX1 (SCM_ID)
		)
		SELECT DISTINCT
		T001.SCM_ID
		FROM TEST.TEST_001_TEMPORARY T001 LEFT JOIN TEST.SCM_NOT_VALIDATED_TEMPORARY NV ON T001.SCM_ID = NV.SCM_ID
		WHERE ISNULL(NV.SCM_ID);";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_003_TEMPORARY
		(
			INDEX IDX1 (SCM_ID)
		)
		SELECT DISTINCT
		T002.SCM_ID
		FROM TEST.TEST_002_TEMPORARY T002 INNER JOIN TEST.SUS_CONSOL_VALIDATION SCV ON T002.SCM_ID = SCV.SCM_ID
		WHERE SCV.VALIDATION_RESULT != 'SUCCESS';";

	ForEach($arySQL as $valueSQL) {
		#Echo $valueSQL."\n";
		ExecuteIQuery($valueSQL,$iConRLM);
	}


	$varSQL = "
		SELECT
		SCM.SCM_ID,
		SCM.DFGIID_DEV
		FROM ((TEST.TEST_001_TEMPORARY T001 INNER JOIN TEST.SUS_CONSOL_MASTER SCM ON T001.SCM_ID = SCM.SCM_ID)
		INNER JOIN TEST.TEST_002_TEMPORARY T002 ON T001.SCM_ID = T002.SCM_ID)
		LEFT JOIN TEST.TEST_003_TEMPORARY T003 ON T001.SCM_ID = T003.SCM_ID
		WHERE ISNULL(T003.SCM_ID)
		AND ISNULL(SCM.DFGIID_PROD)";
	$RSMain = ExecuteIQuery($varSQL,$iConRLM);
	$arySCM = Array();
	While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
		$arySCM[$LineMain["SCM_ID"]] = Array("DFGIID_DEV"=>$LineMain["DFGIID_DEV"]);
		#Print_R($LineMain);
	}


	$aryThis = $arySCM;
	$aryThat = Array();
	ForEach($aryThis as $keyThis => $valueThis) {
		If(WORKFLOW_OK($iConRLM,$keyThis,$varStepPrev,"COMPLETE")) {
			$aryThat[$keyThis] = $valueThis;
		}
		If(Count($aryThat) >= 10) {
			#Break;
		}
	}
	$arySCM = $aryThat;


	$varSQL = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY,
		TEST.TEST_002_TEMPORARY,
		TEST.TEST_003_TEMPORARY";
	ExecuteIQuery($varSQL,$iConRLM);


	Echo "DONE. (".Count($arySCM).")\n";

	Return $arySCM;
}


Function SCM_VALIDATE($iConRLM,$arySCM) {
	Echo __FUNCTION__.".\n";
	

	ForEach(Array_Keys($arySCM) as $keySCMID) {
		try {
			$arySQL = Array();
			WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"START");

			$arySQLTemp = Array();

			$varOKTotal = True;

			Echo Chr(9).$keySCMID." (".Count($arySCM[$keySCMID]["RAW_ROUTE_ID"]).")...";

			#Print_R($arySCM[$keySCMID]);
			#die();

			$aryRRID = $arySCM[$keySCMID]["RAW_ROUTE_ID"];

			$aryTemp = Array();
			ForEach($aryRRID as $keyRRID => $valueRRID) {
				#2020-01-07 RM changed this to simple browser call - pear ssh2 is not installed by default on RHEL
				/*
				$aryTemp 	= Array();
				$aryTemp[] 	= $valueRRID["PART_NUMBER"];
				$aryTemp[] 	= $valueRRID["ROUTING_CODE"];
				$aryTemp[] 	= $arySCM[$keySCMID]["DFGIID_DEV"];

				#Echo $keyRRID."\n";
				#Print_R($aryTemp);
				#die();


				$varCommand = "php /var/www/GES_DASHBOARD/MAXCIM_VALIDATION_SCRIPT/validate_cli.php ".Implode(" ",$aryTemp);
				Echo $varCommand."\n";
				$strmSOURCE = ssh2_exec($ssh2This,$varCommand);
				$strmSTDIO 	= ssh2_fetch_stream($strmSOURCE,SSH2_STREAM_STDIO);
				$strmSTDERR = ssh2_fetch_stream($strmSOURCE,SSH2_STREAM_STDERR);

				stream_set_blocking($strmSTDIO,true);
				stream_set_blocking($strmSTDERR,true);
				
				$msgSTDIO = stream_get_contents($strmSTDIO);
				$errSTDIO = stream_get_contents($strmSTDERR);

				#Echo $msgSTDIO."\n";

				Switch (True) {
					Case $errSTDIO != "":
						throw new Exception ("ERROR IN RETURNED OUTPUT:\n".$errSTDIO);
						Break;
					Case StrPos(strtoupper($msgSTDIO),"ERROR") !== False:
						throw new Exception ("ERROR IN RETURNED OUTPUT:\n".$msgSTDIO);
						Break;
				}
				*/

				#2020-01-07 RM this is the replacement
				$aryTemp 	= Array();
				$aryTemp[] 	= "PART_NUMBER=".URLEncode($valueRRID["PART_NUMBER"]);
				$aryTemp[] 	= "ROUTING_CODE=".URLEncode($valueRRID["ROUTING_CODE"]);
				$aryTemp[] 	= "DFGIID_DEV=".URLEncode($arySCM[$keySCMID]["DFGIID_DEV"]);
				
				$varURL = "http://afodevsvr.maxim-ic.com/GES_DASHBOARD/MAXCIM_VALIDATION_SCRIPT/validate.php?".Implode("&",$aryTemp);
				#Echo $varURL."\n";
				#die();
				$jsnResult = file_get_contents($varURL);
				$aryResult = JSON_Decode($jsnResult,True);


				$varOK = True;
				$varText = "";

				if (json_last_error() === JSON_ERROR_NONE) {
				    // JSON is valid
				    #Print_R($aryResult);
				    Switch (True) {
				    	Case !IsSet($aryResult["STATUS"]) || $aryResult["STATUS"] != "SUCCESS":
				    		$varText = "STATUS != SUCCESS";
		    				$varOK = False;
				    		Break;
				    	Case IsSet($aryResult["ERROR"]) && Count($aryResult["ERROR"]) > 0:
				    		$varText = "ERROR NOT EMPTY";
		    				$varOK = False;
				    		Break;
				    	Default:
				    		$aryValidation = $aryResult["VALIDATION"];
				    		ForEacH(Array_Keys($aryValidation) as $keyCtr) {
				    			If(IsSet($aryValidation[$keyCtr]["VALIDATION"]) && $aryValidation[$keyCtr]["VALIDATION"] == "OK") {
				    				#Echo "OK\n";
				    			}Else{
				    				$varText = $aryValidation[$keyCtr]["VALIDATION"]." : ".$aryValidation[$keyCtr]["VALIDATION_SUMMARY"];
		    						$varOK = False;
				    			}
				    		}
				    		Break;
				    }
				}Else{
					$varText = "INVALID JSON";
	   				$varOK = False;
				}

				If($varOK === True) {
					$varResult = "SUCCESS";
				}Else{
					$varResult = "FAIL";
					$varOKTotal = False;
				}


				#Echo $varResult."\n";

				$aryTemp = Array();

				$aryTemp["SCM_ID"] 					= $keySCMID;
				$aryTemp["RAW_ROUTE_ID"] 			= $keyRRID;
				$aryTemp["PART_NUMBER"] 			= $valueRRID["PART_NUMBER"];
				$aryTemp["ROUTING_CODE"] 			= $valueRRID["ROUTING_CODE"];
				$aryTemp["DFGIID_DEV"] 				= $arySCM[$keySCMID]["DFGIID_DEV"];
				$aryTemp["DFGIID_DEV_CREATE_DT"] 	= $arySCM[$keySCMID]["DFGIID_DEV_CREATE_DT"];
				$aryTemp["VALIDATION_RESULT"] 		= $varResult;
				$aryTemp["VALIDATION_TEXT"] 		= $varText;

				ForEacH($aryTemp as $keyTemp => $valueTemp) {
					$aryTemp[$keyTemp] = Trim(MySQLI_Real_Escape_String($iConRLM,$valueTemp));
				}

				$arySQLTemp[] = "('".Implode("','",$aryTemp)."',NOW())";
			}

			$arySQL = Array();

			$arySQL[] = "
				LOCK TABLES
				TEST.SUS_CONSOL_VALIDATION WRITE";

			$arySQL[] = "
				DELETE FROM
				TEST.SUS_CONSOL_VALIDATION
				WHERE SCM_ID = ".$keySCMID;

			$arySQL[] = "
				INSERT INTO
				TEST.SUS_CONSOL_VALIDATION
				(SCM_ID,RAW_ROUTE_ID,PART_NUMBER,ROUTING_CODE,DFGIID_DEV,DFGIID_DEV_CREATE_DT,VALIDATION_RESULT,VALIDATION_TEXT,VALIDATION_DATETIME)
				VALUES
				".Implode(",",$arySQLTemp);
			
			$arySQL[] = "
				UNLOCK TABLES";

			ForEach($arySQL as $valueSQL) {
				ExecuteIQuery($valueSQL,$iConRLM);
			}


			If($varOKTotal === True) {
				Echo "SUCCESS\n";
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"COMPLETE");
			}Else{
				throw new exception("VALIDATION FAILED");
			}
	
		} catch (exception $e) {
				WORKFLOW_DO($iConRLM,$keySCMID,__FUNCTION__,"FAILURE",$e->getMessage());
				Echo $e->getMessage();
				#ErrorHandler($e,True);
		}
		
	}

	#2020-01-07 RM disabled in favor of simple URL call
	#SSH2_Exec($ssh2This,"exit");
	#Unset($ssh2This);
	#die("FINISHED");
}




Function SCM_NOT_VALIDATED_PREP($iConRLM) {
	Echo __FUNCTION__."...";

	$arySQL = Array();

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY,
		TEST.TEST_002_TEMPORARY,
		TEST.TEST_003_TEMPORARY,
		TEST.SCM_NOT_VALIDATED_TEMPORARY;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_001_TEMPORARY
		(	
			INDEX IDX1 (SCM_ID)
		)
		SELECT
		MAX(SCM_ID) SCM_ID
		FROM TEST.SUS_CONSOL_MASTER SCM INNER JOIN
		(
			SELECT
			DOCNUM,
			MAX(REV) REV
			FROM TEST.SUS_CONSOL_MASTER 
			WHERE IS_GOOD = 1
			GROUP BY DOCNUM
		) SCM_MAX
		ON SCM.DOCNUM = SCM_MAX.DOCNUM AND SCM.REV = SCM_MAX.REV
		WHERE SCM.IS_GOOD = 1		
		GROUP BY SCM.DOCNUM, SCM.REV;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_002_TEMPORARY
		(
			INDEX IDX1 (SCM_ID,RAW_ROUTE_ID,PART_NUMBER,ROUTING_CODE,DFGIID_DEV)
		)
		SELECT
		SCM.SCM_ID,
		R2R.RAW_ROUTE_ID,
		R2R.PART_NUMBER,
		R2R.ROUTING_CODE,
		SCM.DFGIID_DEV,
		SCM.DFGIID_DEV_CREATE_DT
		FROM TEST.TEST_001_TEMPORARY T001 INNER JOIN TEST.SUS_CONSOL_MASTER SCM ON T001.SCM_ID = SCM.SCM_ID
		INNER JOIN TEST.SUS_CONSOL_ROUTE_TO_ROUTE R2R ON SCM.SCM_ID = R2R.SCM_ID;";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.TEST_003_TEMPORARY
		(
			INDEX IDX1 (SCM_ID)
		)
		SELECT DISTINCT
		SCV_FAIL.SCM_ID
		FROM TEST.SUS_CONSOL_VALIDATION SCV_FAIL 
		LEFT JOIN TEST.SUS_CONSOL_VALIDATION SCV_SUCCESS ON SCV_FAIL.SCM_ID = SCV_SUCCESS.SCM_ID AND SCV_SUCCESS.VALIDATION_RESULT = 'SUCCESS'
		WHERE SCV_FAIL.VALIDATION_RESULT = 'FAIL' 
		AND ISNULL(SCV_SUCCESS.SCM_ID);";

	$arySQL[] = "
		CREATE TEMPORARY TABLE
		TEST.SCM_NOT_VALIDATED_TEMPORARY
		(
			INDEX IDX1 (SCM_ID)
		)
		SELECT DISTINCT
		T002.SCM_ID
		FROM TEST.TEST_002_TEMPORARY T002 LEFT JOIN TEST.SUS_CONSOL_VALIDATION SCV 
		ON T002.SCM_ID = SCV.SCM_ID AND T002.RAW_ROUTE_ID = SCV.RAW_ROUTE_ID AND T002.PART_NUMBER = SCV.PART_NUMBER AND T002.ROUTING_CODE = SCV.ROUTING_CODE AND T002.DFGIID_DEV = SCV.DFGIID_DEV
		WHERE ISNULL(SCV.SCM_ID) OR ISNULL(SCV.SCM_ID) OR ISNULL(SCV.PART_NUMBER) OR ISNULL(SCV.ROUTING_CODE);";

	$arySQL[] = "
		INSERT INTO
		TEST.SCM_NOT_VALIDATED_TEMPORARY
		(SCM_ID)
		SELECT DISTINCT
		T002.SCM_ID
		FROM TEST.TEST_002_TEMPORARY T002 INNER JOIN TEST.SUS_CONSOL_VALIDATION SCV 
		ON T002.SCM_ID = SCV.SCM_ID AND T002.RAW_ROUTE_ID = SCV.SCM_ID AND T002.PART_NUMBER = SCV.PART_NUMBER AND T002.ROUTING_CODE = SCV.ROUTING_CODE AND T002.DFGIID_DEV = SCV.DFGIID_DEV
		WHERE T002.DFGIID_DEV != SCV.DFGIID_DEV;";

	$arySQL[] = "
		INSERT INTO
		TEST.SCM_NOT_VALIDATED_TEMPORARY
		(SCM_ID)
		SELECT DISTINCT
		T003.SCM_ID
		FROM TEST.TEST_002_TEMPORARY T002
		INNER JOIN TEST.TEST_003_TEMPORARY T003 ON T002.SCM_ID = T003.SCM_ID;";

	$arySQL[] = "
		DROP TEMPORARY TABLE IF EXISTS
		TEST.TEST_001_TEMPORARY,
		TEST.TEST_002_TEMPORARY,
		TEST.TEST_003_TEMPORARY;";

	ForEach($arySQL as $valueSQL) {
		#Echo $valueSQL."\n";
		ExecuteIQuery($valueSQL,$iConRLM);
	}
	#die();

	Echo "DONE.\n";
}


Function SCM_NOT_VALIDATED_GET($iConRLM,$arySCM) {
	$arySCMTemp = Array();

	if(count($arySCM) > 0) {
		$aryTemp = Array_Map(Array($iConRLM,'real_escape_string'),array_keys($arySCM));
	
		$varSQL = "
			SELECT DISTINCT
			SCM.SCM_ID,
			R2R.RAW_ROUTE_ID,
			R2R.PART_NUMBER,
			R2R.ROUTING_CODE,
			SCM.DFGIID_DEV,
			SCM.DFGIID_DEV_CREATE_DT
			FROM TEST.SUS_CONSOL_MASTER SCM
			INNER JOIN TEST.SUS_CONSOL_ROUTE_TO_ROUTE R2R ON SCM.SCM_ID = R2R.SCM_ID
			WHERE SCM.SCM_ID IN (".Implode(",",$aryTemp).")
			ORDER BY SCM_ID DESC;";
		#Echo $varSQL."\n";
		$RSMain = ExecuteIQuery($varSQL,$iConRLM);
		While ($LineMain = MySQLI_Fetch_Assoc($RSMain)) {
			If(!IsSet($arySCMTemp[$LineMain["SCM_ID"]])) {
				$aryTemp 							= Array();
				$aryTemp["DFGIID_DEV"] 				= $LineMain["DFGIID_DEV"];
				$aryTemp["DFGIID_DEV_CREATE_DT"] 	= $LineMain["DFGIID_DEV_CREATE_DT"];
				$aryTemp["RAW_ROUTE_ID"] 			= Array();

				$arySCMTemp[$LineMain["SCM_ID"]] 		= $aryTemp;
			}

			$aryTemp 					= Array();
			$aryTemp["PART_NUMBER"] 	= $LineMain["PART_NUMBER"];
			$aryTemp["ROUTING_CODE"] 	= $LineMain["ROUTING_CODE"];
			$arySCMTemp[$LineMain["SCM_ID"]]["RAW_ROUTE_ID"][$LineMain["RAW_ROUTE_ID"]] = $aryTemp;
		}

	#Print_R($arySCMTemp);
	#die();

		$varSQL = "
			DROP TEMPORARY TABLE IF EXISTS
			TEST.TEST_001_TEMPORARY,
			TEST.TEST_002_TEMPORARY,
			TEST.TEST_003_TEMPORARY";
		ExecuteIQuery($varSQL,$iConRLM);

	}

	Return $arySCMTemp;
}





?>