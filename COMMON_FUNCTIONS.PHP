<?PHP

// added by CJ, 2012-05-18
Function print_a($ary) {
	Echo "<pre>".print_r($ary,true)."</pre>";
}

// added by CJ, 2011-06-08
Function FIND_WEEKS_IN_FY($Link,$varFY) {
	$varSQL = "
		SELECT
		shared.SF_FIND_WEEKS_IN_FY(".$varFY.") AS WEEKS";
	$RSMain = ExecuteQuery($varSQL,$Link);
	$LineMain = MySQL_Fetch_Assoc($RSMain);
	Return ($LineMain["WEEKS"]);
}

Function FIND_Q_FROM_FYWW($Link,$varFY,$varWW) {
	$varSQL = "
		SELECT
		shared.SF_FIND_Q_FROM_FYWW(".$varFY.",".$varWW.") AS Q";
	$RSMain = ExecuteQuery($varSQL,$Link);
	$LineMain = MySQL_Fetch_Assoc($RSMain);
	Return ($LineMain["Q"]);
}

Function FIND_DATE_FROM_FYWW($Link,$varFY,$varWW) {
	$varSQL = "
		SELECT
		shared.SF_FIND_DATE_FROM_FYWW(".$varFY.",".$varWW.") AS DATE_VALUE";
	$RSMain = ExecuteQuery($varSQL,$Link);
	$LineMain = MySQL_Fetch_Assoc($RSMain);
	Return $LineMain["DATE_VALUE"];	
}

Function FIND_WW_FROM_FYQ($Link,$varFY,$varQ) {
	$varSQL = "
		SELECT
		shared.SF_FIND_WW_FROM_FYQ(".$varFY.",".$varQ.") AS WW";
	$RSMain = ExecuteQuery($varSQL,$Link);
	$LineMain = MySQL_Fetch_Assoc($RSMain);
	Return $LineMain["WW"];	
}

Function CHECK_DMCLS_MEMORY($Link) {
	$varSQL = "
		CALL shared.CHECK_DMCLS_MEMORY()";
	ExecuteQuery($varSQL,$Link);	
}



Function FIND_WEEKS_IN_FYQ($Link,$varFY,$varQ) {
	$varSQL = "
		CALL shared.CHECK_DMCLS_MEMORY()";
	ExecuteQuery($varSQL,$Link);
	
	$varSQL = "
		SELECT
		DISTINCT
		WEEK_NUMBER
		FROM SHARED.DMCLS_MEMORY
		WHERE MFG_YEAR = ".$varFY."
		AND MFG_QUARTER = ".$varQ."
		ORDER BY WEEK_NUMBER";
	//Echo $varSQL;
	$RSMain = ExecuteQuery($varSQL,$Link);
	$aryTemp = Array();
	While ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
		$aryTemp[] = $LineMain["WEEK_NUMBER"];
	}
	
	Return $aryTemp;
}



Function Convert_Loc($varLoc,$varTargetType) {
Switch (True) {
   Case $varTargetType == "WIP":
      Switch (True) {
         Case $varLoc == "Beaverton" OR $varLoc == "Hillsboro":
            $varLoc = "BVN";
            Break;
         Case $varLoc == "OFFSHORE" OR $varLoc == "Philippines":
            $varLoc = "PHL";
            Break;
         Case $varLoc == "Sunnyvale":
            $varLoc = "SV";
            Break;
         Case $varLoc == "Dallas":
            $varLoc = "DAL";
            Break;
         Case $varLoc == "Thailand":
            $varLoc = "THI";
      }
      Break;
   Case $varTargetType == "EQUIP":
      Switch (True) {
         Case $varLoc == "BVN" OR $varLoc == "Beaverton":
            $varLoc = "Hillsboro";
            Break;
         Case $varLoc == "PHL" OR $varLoc == "OFFSHORE":
            $varLoc = "Philippines";
            Break;
         Case $varLoc == "SV":
            $varLoc = "Sunnyvale";
            Break;
         Case $varLoc == "DAL":
            $varLoc = "Dallas";
            Break;
         Case $varLoc == "THI":
            $varLoc = "Thailand";
            Break;
      }
      Break;
   Case $varTargetType == "WF":
      Switch (True) {
         Case $varLoc == "BVN" OR $varLoc == "Hillsboro":
            $varLoc = "Beaverton";
            Break;
         Case $varLoc == "PHL" OR $varLoc == "Philippines":
            $varLoc = "OFFSHORE";
            Break;
         Case $varLoc == "SV":
            $varLoc = "Sunnyvale";
            Break;
         Case $varLoc == "DAL":
            $varLoc = "Dallas";
            Break;
         Case $varLoc == "THI":
            $varLoc = "Thailand";
            Break;
      } 
}
Return $varLoc;

}



Function Find_Browser() {
   $userBrowser = $_SERVER['HTTP_USER_AGENT']; 

   #Echo $userBrowser."<BR>";

   Switch (True) {
      Case (StrPos($userBrowser,"MSIE") != False):
         $userBrowser = "MSIE";
         Break;
      Case (StrPos($userBrowser,"Firefox") != False):
         $userBrowser = "Firefox";
         Break;
      Case (StrPos($userBrowser,"Gecko") != False):
         $userBrowser = "Mozilla";
         Break;
      Default:
         $userBrowser = "MSIE";
         Break;
   }

   return $userBrowser;

}

Function SQLSafe($varString) {
   If ($varString != "") {
      Return MySQL_Real_Escape_String($varString);
   }Else{
      Return $varString;
   }
}


Function Open_Link($varDatabase) {
	$varUser = "MITO_R";
	$varPassword = "PASSWORD";
	
	Switch (True) {
		Case StrToUpper($varDatabase) == "TESTWEB" || StrToUpper($varDatabase) == "TESTWEBSVR.MAXIM-IC.INTERNAL" || StrPos(StrToUpper($varDatabase),"TESTWEBSVR") !== False:
			$Link = MySQL_Connect("testwebsvr.maxim-ic.com",$varUser,$varPassword) OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case StrToUpper($varDatabase) == "TESTWEB2" || StrToUpper($varDatabase) == "TESTWEB2.MAXIM-IC.COM":
			$Link = MySQL_Connect("testweb2.maxim-ic.com",$varUser,$varPassword) OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case $varDatabase == "TESTOPS" || $varDatabase == "STAT" || $varDatabase == "ICO" || $varDatabase == "UTBS" || $varDatabase=="EQUIP":
			$Link = MySQL_Connect("testops.maxim-ic.com",$varUser,$varPassword) OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case StrPos(StrToUpper($varDatabase),"MFGQTRPLAN") !== False || StrPos(StrToUpper($varDatabase),"CPT") !== False:
			$Link = MySQL_Connect("mfgqtrplan.maxim-ic.com","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case StrPos(StrToUpper($varDatabase),"MFGAPP") !== False:
			$Link = MySQL_Connect("mfgapp.maxim-ic.com","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case $varDatabase == "MFGQTRPLANDEV":
			$Link = MySQL_Connect("172.18.44.184","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case $varDatabase == "MYSQL_MPOC" || $varDatabase == "172.23.100.170" || StrPos(StrToUpper($varDatabase),"CAV-ITSVR14") !== False:
			// $Link = MySQL_Connect("172.23.100.170","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
			$Link = MySQL_Connect("afotcocv001.maxim-ic.com","MITO_R","PASSWORD") OR Die("MySQL Error MYSQL_MPOC: ".MySQL_Error());
			
			Break;
		Case StrPos(StrToUpper($varDatabase),"PACKAGING") !== False:
			$Link = MySQL_Connect("packaging.maxim-ic.com",'testoperation','reynmito') OR Die("MySQL Error : ".MySQL_Error());
			Break;
		Case StrPos(StrToUpper($varDatabase),"AFOTCOCB001") !== False:
			$Link = MySQL_Connect("172.21.128.100","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
			Break;
      Case StrPos(StrToUpper($varDatabase),"AFODEVSVR") !== False:
         $Link = MySQL_Connect("afodevsvr.maxim-ic.com","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
         Break;
      Case StrPos(StrToUpper($varDatabase),"AFOTCOSJ004") !== False:
         $Link = MySQL_Connect("afotcosj004.maxim-ic.com","MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
         Break;
      Case StrToUpper($varDatabase) == "LOCALHOST":
         $Link = MySQL_Connect("LOCALHOST","MITO_R","PASSWORD");
         Break;
		Case $varDatabase == $_SERVER['SERVER_ADDR']:
			$Link = MySQL_Connect(gethostbyaddr($varDatabase),$varUser,$varPassword);
         #echo "connected3<BR>";
			Break;
		Case $varDatabase == $_SERVER['SERVER_NAME']:
			$Link = MySQL_Connect($varDatabase,$varUser,$varPassword);
         #echo $varDatabase;
         #echo "connected1<BR>";
			Break;
      Case StrPos(StrToUpper($varDatabase),"MPOCTSG") !== False:
         $Link = MySQL_Connect("mpoctsg.maxim-ic.com", 'rmito', 'm@xim30') or die("MySQL Error MPOCTSG: " . mysql_error());
         break;
      Case StrPos(StrToUpper($varDatabase),"MXHDAFOT01L") !== False:
         $Link = MySQL_Connect("mxhdafot01l.maxim-ic.com",'MITO_R','PASSWORD') or die("MySQL Error MXHDAFOT01L: " . mysql_error());
         #echo "connected2<BR>";
         break;
		Default:
			$Link = MySQL_Connect("testwebsvr.maxim-ic.com",$varUser,$varPassword) OR Die("MySQL Error : ".MySQL_Error());
			Break;
	}
	Return $Link;
}
   

Function FDOQ() {
   $aryFDOQ[0] = FDOWW(Curr_FY(),Curr_WW());
   Switch (True) {
     Case (Curr_WW()<14):
       $wwQbeg = 1;
       Break;
     Case (Curr_WW()<27):
       $wwQbeg = 14;
       Break;
     Case (Curr_WW()<40):
       $wwQbeg = 27;
       Break;
     Default:
       $wwQbeg = 40;
       Break;
   }    
   
   $aryFDOQ[-1] = FDOWW(Curr_FY(),$wwQbeg);

   //last two qtrs
   Switch (True) {
      Case $wwQbeg==1: 
         $aryFDOQ[-2] = FDOWW(Curr_FY()-1,40);
         $aryFDOQ[-3] = FDOWW(Curr_FY()-1,27);
         Break;
         
      Case $wwQbeg==14: 
         $aryFDOQ[-2] = FDOWW(Curr_FY(),1);
         $aryFDOQ[-3] = FDOWW(Curr_FY()-1,40);
         Break;
         
      Default: 
         $aryFDOQ[-2] = FDOWW(Curr_FY(),$wwQBeg-13);
         $aryFDOQ[-3] = FDOWW(Curr_FY(),$wwQBeg-26);
   }
         
      
      
   $ww = Curr_WW();
   $fy = Curr_FY();
   $fyNextQ = $fy;

   For ($i=1;$i<=4;$i++) {
     $wwNextQ = $wwQbeg+(13*$i);
     If ($wwNext>53) {
        $wwNextQ = $wwNextQ-52;
        $fyNextQ = $fy+1;
     }
     $aryFDOQ[$i] = FDOWW($fyNextQ,$wwNextQ) ;

   }
   Return $aryFDOQ;
}


Function FDOQ_Session($fy_session,$ww_session) {

   $aryFDOQ[0] = FDOWW($fy_session,$ww_session);
   Switch (True) {
     Case ($ww_session<14):
       $wwQbeg = 1;
       Break;
     Case ($ww_session<27):
       $wwQbeg = 14;
       Break;
     Case ($ww_session<40):
       $wwQbeg = 27;
       Break;
     Default:
       $wwQbeg = 40;
       Break;
   }    
   
   $aryFDOQ[-1] = FDOWW($fy_session,$wwQbeg);

   //last two qtrs
   Switch (True) {
      Case $wwQbeg==1: 
         $aryFDOQ[-2] = FDOWW($fy_session-1,40);
         $aryFDOQ[-3] = FDOWW($fy_session-1,27);
         Break;
         
      Case $wwQbeg==14: 
         $aryFDOQ[-2] = FDOWW($fy_session,1);
         $aryFDOQ[-3] = FDOWW($fy_session-1,40);
         Break;
         
      Default: 
         $aryFDOQ[-2] = FDOWW($fy_session,$wwQBeg-13);
         $aryFDOQ[-3] = FDOWW($fy_session,$wwQBeg-26);
   }
         
      
      
   $ww = $ww_session;
   $fy = $fy_session;
   $fyNextQ = $fy;

   For ($i=1;$i<=4;$i++) {
     $wwNextQ = $wwQbeg+(13*$i);
     If ($wwNext>53) {
        $wwNextQ = $wwNextQ-52;
        $fyNextQ = $fy+1;
     }
     $aryFDOQ[$i] = FDOWW($fyNextQ,$wwNextQ) ;

   }
   Return $aryFDOQ;
}



Function FDOQ0() {

$Now = Time();
$Beg = MkTime(0,0,0,7,1,2000);

$varFY = 0;
$varLeapCtr = 0;
$varWW = Ceil(($Now - $Beg) / 3600 / 168);
While ($varWW > 52) {
   $varWW -= 52;
   $varFY++;
   If (FMod($varFY,4) == 0 AND $varFY != 0) {
      $varLeapCtr ++;
   }
}

Switch (True) {
   Case $varWW <= 13:
      $varWW = 1;
      Break;
   Case $varWW <= 26:
      $varWW = 14;
      Break;
   Case $varWW <= 39:
      $varWW = 27;
      Break;
   Case $varWW <= 52:
      $varWW = 40;
      Break;
}

$varFDOQ0 = $Beg + (3600 * 168 * 52 * $varFY) + (3600 * 168 * ($varWW - 1)) + ($varLeapCtr * 24 * 3600);
Return $varFDOQ0;
}

Function LDOQ0() {
$Now = Time();
$Beg = MkTime(0,0,0,7,1,2000);

$varFY = 0;
$varLeapCtr = 0;
$varWW = Ceil(($Now - $Beg) / 3600 / 168);
While ($varWW > 52) {
   $varWW -= 52;
   $varFY++;
   If (FMod($varFY,4) == 0 AND $varFY != 0) {
      $varLeapCtr ++;
   }
}

Switch (True) {
   Case $varWW <= 13:
      $varWW = 13;
      Break;
   Case $varWW <= 26:
      $varWW = 26;
      Break;
   Case $varWW <= 39:
      $varWW = 39;
      Break;
   Case $varWW <= 52:
      $varWW = 52;
      Break;
}

$varLDOQ0 = $Beg + (3600 * 168 * 52 * $varFY) + (3600 * 168 * $varWW) + (25 * 3600) - 1;;
Return $varLDOQ0;

}

Function FDOQ1() {
Return LDOQ0() + 1;
}

Function LDOQ1() {
Return FDOQ1() + (3600 * 168 * 13) - 1;
}


Function Find_WW($varMonth,$varDay,$varYear) {
   Switch (True) {
   	Case $varYear < 1970:
   		$varYear = 1970;
   		Break;
   	Case $varYear > 2037:
   		$varYear = 2037;
   		Break;	
   }
	
	$Link2 = Open_Link("TESTOPS");
	$varSQL = "SELECT shared.SF_FIND_WWFY('".$varYear."-".$varMonth."-".$varDay."','WW')";
	$RSMain = MySQL_Query($varSQL,$Link2) OR Die("MySQL Error : ".MySQL_Error($Link2));
	$LineMain = MySQL_Fetch_Array($RSMain);
   
	Return $LineMain[0];
}

Function Find_FY($varMonth,$varDay,$varYear) {
   Switch (True) {
   	Case $varYear < 1970:
   		$varYear = 1970;
   		Break;
   	Case $varYear > 2037:
   		$varYear = 2037;
   		Break;	
   }
	
	$Link2 = Open_Link("TESTOPS");
	$varSQL = "SELECT shared.SF_FIND_WWFY('".$varYear."-".$varMonth."-".$varDay."','FY')";
	$RSMain = MySQL_Query($varSQL,$Link2) OR Die("MySQL Error : ".MySQL_Error($Link2));
	$LineMain = MySQL_Fetch_Array($RSMain);
   
	Return ($LineMain[0]-2000);
}

Function Find_Q($varWW) {
   If ($varWW == "") {
      $varWW = Curr_WW();
   }
   Switch (True) {
      Case $varWW <=13:
         Return 1;  
         Break;
      Case $varWW <=26:
         Return 2;  
         Break;
      Case $varWW <=39:
         Return 3;  
         Break;
      Default:
         Return 4;  
         Break;
   }
}


Function Curr_WW() {
	$varSQL = "SELECT shared.SF_FIND_WWFY(NOW(),'WW')";
	$Link2 = Open_Link("TESTOPS");
	$RSMain = MySQL_Query($varSQL,$Link2) OR Die("MySQL Error : COMMON_FUNCTIONS.PHP : CURR_WW".MySQL_Error($Link2));
	$LineMain = MySQL_Fetch_ArraY($RSMain);
	Return $LineMain[0];
}

Function Curr_FY() {
	$varSQL = "SELECT shared.SF_FIND_WWFY(NOW(),'FY')";
	$Link2 = Open_Link("TESTOPS");
	$RSMain = MySQL_Query($varSQL,$Link2) OR Die("MySQL Error : COMMON_FUNCTIONS.PHP : CURR_WW".MySQL_Error($Link2));
	$LineMain = MySQL_Fetch_ArraY($RSMain);
	Return ($LineMain[0] - 2000);
}

Function Curr_Q() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 1;  
         Break;
      Case $varWW <=26:
         Return 2;  
         Break;
      Case $varWW <=39:
         Return 3;  
         Break;
      Case $varWW <=52:
         Return 4;  
         Break;
   }
}

//For Leap Year Quarter calc ,  SA - Q4, 2012
Function Curr_Q_LeapY() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 1;  
         Break;
      Case $varWW <=27:
         Return 2;  
         Break;
      Case $varWW <=40:
         Return 3;  
         Break;
      Case $varWW <=53:
         Return 4;  
         Break;
   }
}
Function BOQ() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 1;
         Break;
      Case $varWW <=26:
         Return 14;
         Break;
      Case $varWW <=39:
         Return 27;
         Break;
      Default:
         Return 40;
         Break;
   }
}

Function Find_BOQ($varWW) {
   If ($varWW == "") {
      $varWW = Curr_WW();
   }
   Switch (True) {
      Case $varWW <=13:
         Return 1;
         Break;
      Case $varWW <=26:
         Return 14;
         Break;
      Case $varWW <=39:
         Return 27;
         Break;
      Default:
         Return 40;
         Break;
   }
}

Function BOQ1() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 14;
         Break;
      Case $varWW <=26:
         Return 27;
         Break;
      Case $varWW <=39:
         Return 40;
         Break;
      Default:
         Return 1;
         Break;
   }
}

Function BOQ2() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 27;
         Break;
      Case $varWW <=26:
         Return 40;
         Break;
      Case $varWW <=39:
         Return 1;
         Break;
      Default:
         Return 14;
         Break;
   }
}

Function EOQ() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 13;
         Break;
      Case $varWW <=26:
         Return 26;
         Break;
      Case $varWW <=39:
         Return 39;
         Break;
      Default:
         Return 52;
         Break;
   }
}



Function Find_EOQ($varWW) {
   If ($varWW == "") {
      $varWW = Curr_WW();
   }
   Switch (True) {
      Case $varWW <=13:
         Return 13;
         Break;
      Case $varWW <=26:
         Return 26;
         Break;
      Case $varWW <=39:
         Return 39;
         Break;
      Default:
         Return 52;
         Break;
   }
}

Function EOQ1() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 26;
         Break;
      Case $varWW <=26:
         Return 39;
         Break;
      Case $varWW <=39:
         Return 52;
         Break;
      Default:
         Return 13;
         Break;
   }
}

Function EOQ2() {
   $varWW = Curr_WW();
   Switch (True) {
      Case $varWW <=13:
         Return 39;
         Break;
      Case $varWW <=26:
         Return 52;
         Break;
      Case $varWW <=39:
         Return 13;
         Break;
      Default:
         Return 26;
         Break;
   }
}


Function FDOWW($varFY,$varWW) {
$Link2 = Open_Link("TESTOPS");


Switch (True) {
   Case $varWW > 0:
      $varSQL = "SELECT UNIX_TIMESTAMP(shared.SF_FIND_FDOWWFY(".($varFY+2000).",".$varWW."))";
      Break;
   Default:
      $varSQL = "SELECT shared.SF_FIND_WWFY(DATE_SUB(shared.SF_FIND_FDOWWFY(".($varFY+2000).",1), INTERVAL 7 DAY),'FY'), shared.SF_FIND_WWFY(DATE_SUB(shared.SF_FIND_FDOWWFY(".($varFY+2000).",1), INTERVAL 7 DAY),'WW')";
//Echo Arial1($varSQL)."<BR><BR>";
      $RSMain = MySQL_Query($varSQL,$Link2) OR Die("MySQL Error : ".MySQL_Error($Link2));
      $LineMain = MySQL_Fetch_Array($RSMain);
      $varSQL = "SELECT UNIX_TIMESTAMP(shared.SF_FIND_FDOWWFY(".$LineMain[0].",".$LineMain[1]."))";
      Break;
}

//Echo Arial1($varSQL)."<BR><BR>";
$RSMain = MySQL_Query($varSQL,$Link2) OR Die("MySQL Error : ".MySQL_Error($Link2));
$LineMain = MySQL_Fetch_Array($RSMain);
Return $LineMain[0];


/*
$varStart = MkTime(0,0,0,7,1,2000,"U");

$varLeap = 0;
For ($i=2001;$i<(2000+$varFY);$i++) {
   $varYear = MkTime(0,0,0,7,1,$i,"U");
   If (Date("L",$varYear) == 1) {
      $varLeap++;
   }
}

$varTarget = $varStart + (($varFY - 1) * 31449600) + (($varWW-1) * 604800) + ($varLeap * 86400);
Return $varTarget;
*/

}

Function FDOWW_Temp($varFY,$varWW) {

}


Function LAST_RUN($varDB, $varTbl, $varDisplayName) {
$Link = MySQL_Connect($_SERVER['LOCAL_ADDR'],"MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
$varSQL = "SELECT `DISPLAYNAME`, `SOURCENAME`, `SOURCETIMESTAMP`, `OUTPUTTIMESTAMP` FROM `".$varDB."`.`".$varTbl."` WHERE `DISPLAYNAME` = '".$varDisplayName."'";
$RSLastRun = MySQL_Query($varSQL,$Link) OR Die("MySQL Error : ".MySQL_Error($Link));
$varFieldCount = MySQL_Num_Fields($RSLastRun);
$LineLastRun = MySQL_Fetch_Array($RSLastRun);

$varString = "<TABLE BORDER=\"1\" CELLSPACING=\"0\" CELLPADDING=\"1\">\n";
$varString .= "<THEAD STYLE=\"FONT-FAMILY:ARIAL; FONT-SIZE:xx-small; FONT-WEIGHT:BOLD; COLOR:#FFFFFF;\"><TR BGCOLOR=\"NAVY\">\n";
$varString .= "<TH>ITEM</TH><TH>VALUE</TH>\n";
$varString .= "</TR></THEAD><TBODY STYLE=\"FONT-FAMILY:ARIAL; FONT-SIZE:xx-small; FONT-WEIGHT:BOLD; COLOR:#000000;\">\n";
For ($i=0;$i<$varFieldCount;$i++) {
   $varString .= "<TR BGCOLOR=\"WHITE\">\n";
   $varString .= "<TD>".MySQL_Field_Name($RSLastRun,$i)."</TD>\n";
   $varString .= "<TD>".$LineLastRun[$i]."</TD>\n";
   $varString .= "</TR>\n";
}
$varString .= "</TBODY></TABLE>\n";
Return $varString;


}




Function Arial1White($varString) {
Return "<FONT FACE=ARIAL COLOR=\"WHITE\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function Arial2White($varString) {
Return "<FONT FACE=ARIAL COLOR=\"WHITE\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function Arial3White($varString) {
Return "<FONT FACE=ARIAL COLOR=\"WHITE\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}

Function Arial1($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLACK\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function Arial2($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLACK\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function Arial3($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLACK\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}

Function Arial1U($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLACK\" SIZE=\"1\"><B><U>".$varString."</U></B></FONT>";
}

Function Arial2U($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLACK\" SIZE=\"2\"><B><U>".$varString."</U></B></FONT>";
}

Function Arial3U($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLACK\" SIZE=\"3\"><B><U>".$varString."</U></B></FONT>";
}

Function CN1($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"BLACK\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function CN2($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"BLACK\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function CN3($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"BLACK\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}

Function CN1Red($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"RED\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function CN2Red($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"RED\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function CN3Red($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"RED\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}

Function CN1White($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"WHITE\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function CN2White($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"WHITE\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function CN3White($varString) {
Return "<FONT FACE=\"COURIER NEW\" COLOR=\"WHITE\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}

Function Arial1Red($varString) {
Return "<FONT FACE=ARIAL COLOR=\"RED\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function Arial2Red($varString) {
Return "<FONT FACE=ARIAL COLOR=\"RED\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function Arial3Red($varString) {
Return "<FONT FACE=ARIAL COLOR=\"RED\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}

Function Arial1Blue($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLUE\" SIZE=\"1\"><B>".$varString."</B></FONT>";
}

Function Arial2Blue($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLUE\" SIZE=\"2\"><B>".$varString."</B></FONT>";
}

Function Arial3Blue($varString) {
Return "<FONT FACE=ARIAL COLOR=\"BLUE\" SIZE=\"3\"><B>".$varString."</B></FONT>";
}



function CheckCRC($fname) 
{ 
/* 
This function calculates the CRC checksum of the given file and returns it in chars of lower 16 bits. 
*/ 



    // Check sum table 
    $crctab16 =array( 
        0x0000,  0x1021,  0x2042,  0x3063,  0x4084,  0x50a5,  0x60c6,  0x70e7, 
        0x8108,  0x9129,  0xa14a,  0xb16b,  0xc18c,  0xd1ad,  0xe1ce,  0xf1ef, 
        0x1231,  0x0210,  0x3273,  0x2252,  0x52b5,  0x4294,  0x72f7,  0x62d6, 
        0x9339,  0x8318,  0xb37b,  0xa35a,  0xd3bd,  0xc39c,  0xf3ff,  0xe3de, 
        0x2462,  0x3443,  0x0420,  0x1401,  0x64e6,  0x74c7,  0x44a4,  0x5485, 
        0xa56a,  0xb54b,  0x8528,  0x9509,  0xe5ee,  0xf5cf,  0xc5ac,  0xd58d, 
        0x3653,  0x2672,  0x1611,  0x0630,  0x76d7,  0x66f6,  0x5695,  0x46b4, 
        0xb75b,  0xa77a,  0x9719,  0x8738,  0xf7df,  0xe7fe,  0xd79d,  0xc7bc, 
        0x48c4,  0x58e5,  0x6886,  0x78a7,  0x0840,  0x1861,  0x2802,  0x3823, 
        0xc9cc,  0xd9ed,  0xe98e,  0xf9af,  0x8948,  0x9969,  0xa90a,  0xb92b, 
        0x5af5,  0x4ad4,  0x7ab7,  0x6a96,  0x1a71,  0x0a50,  0x3a33,  0x2a12, 
        0xdbfd,  0xcbdc,  0xfbbf,  0xeb9e,  0x9b79,  0x8b58,  0xbb3b,  0xab1a, 
        0x6ca6,  0x7c87,  0x4ce4,  0x5cc5,  0x2c22,  0x3c03,  0x0c60,  0x1c41, 
        0xedae,  0xfd8f,  0xcdec,  0xddcd,  0xad2a,  0xbd0b,  0x8d68,  0x9d49, 
        0x7e97,  0x6eb6,  0x5ed5,  0x4ef4,  0x3e13,  0x2e32,  0x1e51,  0x0e70, 
        0xff9f,  0xefbe,  0xdfdd,  0xcffc,  0xbf1b,  0xaf3a,  0x9f59,  0x8f78, 
        0x9188,  0x81a9,  0xb1ca,  0xa1eb,  0xd10c,  0xc12d,  0xf14e,  0xe16f, 
        0x1080,  0x00a1,  0x30c2,  0x20e3,  0x5004,  0x4025,  0x7046,  0x6067, 
        0x83b9,  0x9398,  0xa3fb,  0xb3da,  0xc33d,  0xd31c,  0xe37f,  0xf35e, 
        0x02b1,  0x1290,  0x22f3,  0x32d2,  0x4235,  0x5214,  0x6277,  0x7256, 
        0xb5ea,  0xa5cb,  0x95a8,  0x8589,  0xf56e,  0xe54f,  0xd52c,  0xc50d, 
        0x34e2,  0x24c3,  0x14a0,  0x0481,  0x7466,  0x6447,  0x5424,  0x4405, 
        0xa7db,  0xb7fa,  0x8799,  0x97b8,  0xe75f,  0xf77e,  0xc71d,  0xd73c, 
        0x26d3,  0x36f2,  0x0691,  0x16b0,  0x6657,  0x7676,  0x4615,  0x5634, 
        0xd94c,  0xc96d,  0xf90e,  0xe92f,  0x99c8,  0x89e9,  0xb98a,  0xa9ab, 
        0x5844,  0x4865,  0x7806,  0x6827,  0x18c0,  0x08e1,  0x3882,  0x28a3, 
        0xcb7d,  0xdb5c,  0xeb3f,  0xfb1e,  0x8bf9,  0x9bd8,  0xabbb,  0xbb9a, 
        0x4a75,  0x5a54,  0x6a37,  0x7a16,  0x0af1,  0x1ad0,  0x2ab3,  0x3a92, 
        0xfd2e,  0xed0f,  0xdd6c,  0xcd4d,  0xbdaa,  0xad8b,  0x9de8,  0x8dc9, 
        0x7c26,  0x6c07,  0x5c64,  0x4c45,  0x3ca2,  0x2c83,  0x1ce0,  0x0cc1, 
        0xef1f,  0xff3e,  0xcf5d,  0xdf7c,  0xaf9b,  0xbfba,  0x8fd9,  0x9ff8, 
        0x6e17,  0x7e36,  0x4e55,  0x5e74,  0x2e93,  0x3eb2,  0x0ed1,  0x1ef0); 

//        calculate the checksum of the file 
    $fp=fopen($fname,"r"); 
    $crc=0xffff; 

    while(1){ 
        $a=fgetc($fp); 
        if(feof($fp)){ break; } 
        $crc = $crctab16[ ( $crc >> 8 ^  ord($a) ) & 0xFF ] ^ ( ($crc << 8) & 0xffff ); 
    } 
    fclose($fp); 
    $h=sprintf("%04x",$crc); 
    list($a,$b)=array(substr($h,0,2),substr($h,2,2)); 

    return chr(intval($a,16)).chr(intval($b,16)); 
} 

/*
**  Returns an array of permissions for the specified user on the specified system.
*/
function getUserPermissions ($iUserID, $sSystem) {
  // Init
  $aResult = Array ();
  $Link = MySQL_Connect($_SERVER['LOCAL_ADDR'],"MITO_R","PASSWORD") OR Die("MySQL Error : ".MySQL_Error());
  
  // Get permissions
  $iUserID = (int)$iUserID;  // Easy way to scrub INTs for injection attack prevention
  $sSQL = "SELECT permissions
           FROM user.user_access
           WHERE userid = {$iUserID}
           AND system = '{$sSystem}'";
  $oDataset = MySQL_Query ($sSQL);
  
  // Build list
  while ($aRow = MySQL_Fetch_Array ($oDataset)) {
    $aResult [] = trim ($aRow ["permissions"]);
  }
  
  // Return results
  return ($aResult);
}

FUNCTION OPEN_LINK_MYSQLI($hostname,$username="MITO_R",$password="PASSWORD"){
    $link = "";
    
    if(strtolower($hostname)=="cavldwdb01.maxim-ic.com"){
        $link = mysqli_connect($hostname,"tableauadmin","tableaudev2016");
    }
    else{
        $link = mysqli_connect($hostname,$username,$password);
    }
    
    if(!$link){
        ERROR_HANLDER_MYSQLI("CONNECTION ERROR: ".$hostname." \n\r");
        die("Connection failed: ".mysqli_connect_error());
    }
    else{
        return $link;
    }
}

?>
