<?PHP
Include_Once("../COMMON_FUNCTIONS.PHP");
Session_Start();

#Print_R2($_REQUEST);
$LinkTestweb2 = Open_Link("localhost");
$LinkTestweb = Open_Link("TESTWEB");

#$varRedirect = GET_START_PAGE_FROM_SYSTEM($LinkTestweb2,$LinkTestweb,"JM_PDM_DB");
#$varRedirect = str_replace("TESTWEB2","MXHDAFOT01L",strtoupper($varRedirect),);
#echo $varRedirect."<BR>";
#die();

Switch (True) {
	Case IsSet($_REQUEST["id"]) && IsSet($_REQUEST["SYSTEM"]) && $_REQUEST["SYSTEM"] == "ROUTE_BUILDER":
		$_SESSION["SSO"] = GET_USER_INFO($_GET['id']);
		ATTACH_EMPLOYEE_NAME_NICE($LinkTestweb2);

		$varRedirect = GET_START_PAGE_FROM_SYSTEM($LinkTestweb2,$LinkTestweb,$_REQUEST["SYSTEM"]);
	
		$varRedirect = str_replace("testweb2","mxhdafot01l",$varRedirect);

		If(!Is_Null($varRedirect)) {
			#Echo "<BODY ONLOAD=\"window.location='".$varRedirect."';\">Redirecting...</BODY>";
			Echo "<BODY ONLOAD=\"window.location='".$varRedirect."'\">";
			Echo "Redirecting you to ".$_REQUEST["SYSTEM"].". If redirection fails, please click <A HREF=\"".$varRedirect."\"
			>here</A> to directly navigate";

			Echo "</BODY>";
		}

		Break;

		#GET_USER_INFO_FROM_WORKDAY($LinkTestweb2,$)
	Case IsSet($_REQUEST["id"]) && IsSet($_REQUEST["SYSTEM"]) && StrPos($_REQUEST["SYSTEM"],"RSM") !== False:
		$_SESSION["SSO"] = GET_USER_INFO($_GET['id']);
		ATTACH_EMPLOYEE_NAME_NICE($LinkTestweb2);

		$aryTemp = Explode("^",$_REQUEST["SYSTEM"]);

		$varRedirect = str_replace("testweb2","mxhdafot01l",$varRedirect);
	
		$varRedirect = str_replace(strtoupper($varRedirect),"TESTWEB2","MXHDAFOT01L");
		$varRedirect .= "?TICKET_ID=".$aryTemp[1];

		If(!Is_Null($varRedirect)) {
			Echo "<BODY ONLOAD=\"window.location='".$varRedirect."'\">";
			Echo "Redirecting you to ".$_REQUEST["SYSTEM"].". If redirection fails, please click <A HREF=\"".$varRedirect."\"
			>here</A> to directly navigate";

			Echo "</BODY>";
		}

		Break;

		#GET_USER_INFO_FROM_WORKDAY($LinkTestweb2,$)

	Case IsSet($_REQUEST["id"]) && IsSet($_REQUEST["SYSTEM"]):
		$_SESSION["SSO"] = GET_USER_INFO($_GET['id']);
		ATTACH_EMPLOYEE_NAME_NICE($LinkTestweb2);

		$varUserID = GET_USER_ID_FROM_SSO($LinkTestweb,$_SESSION["SSO"]);

		If(!Is_Null($varUserID)) {
			$_SESSION[$_REQUEST["SYSTEM"]]["USER_ID"] = $varUserID;
			$_SESSION[$_REQUEST["SYSTEM"]]["PERMISSIONS"] = GET_PERMISSIONS_FROM_USER_ID($LinkTestweb,$_REQUEST["SYSTEM"],$varUserID);
		}Else{
			$_SESSION[$_REQUEST["SYSTEM"]]["USER_ID"] = 3;
			$_SESSION[$_REQUEST["SYSTEM"]]["PERMISSIONS"] = "READ";

		}

		$varRedirect = GET_START_PAGE_FROM_SYSTEM($LinkTestweb2,$LinkTestweb,$_REQUEST["SYSTEM"]);
		$varRedirect = str_replace("testweb2","mxhdafot01l",$varRedirect);

		If(!Is_Null($varRedirect)) {
			Echo "<BODY ONLOAD=\"window.location='".$varRedirect."';\">Redirecting...</BODY>";
		}
	#Print_R2($_SESSION);
		#die();

		

		Break;

}


Function ATTACH_EMPLOYEE_NAME_NICE($LinkTestweb2) {
	$varSQL = "
		SELECT
		CONCAT_WS(
			' ',
			PREFERRED_NAME,
			LAST_NAME
		) NAME_NICE
		FROM PERSONNEL.V_WORKDAY
		WHERE EMPLOYEE_NO = '".$_SESSION["SSO"]["employeeno"]."'";
	$RSMain = ExecuteQuery($varSQL,$LinkTestweb2);
	WHile ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
		$_SESSION["SSO"]["employeenamenice"] = $LineMain["NAME_NICE"];
		return;
	}

}



Function ROUTE_BUILDER_GET_LAST_PAGE_VISITED($LinkTestweb2) {
	$varSession = MySQL_Real_Escape_String(Session_ID());

	$varSQL = "
		SELECT
		LAST_PAGE_VISITED
		FROM ROUTE_BUILDER.SESSION_HISTORY
		WHERE SESSION_ID = '".$varSession."'";
	$RSMain = ExecuteQuery($varSQL,$LinkTestweb2);
	WHile ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
		Return $LineMain["LAST_PAGE_VISITED"];
	}
	Return "";
}
/*
if( isset($_REQUEST['id']) && !isset($_SESSION['SSO']['employeename'])){
$data_sso = GET_USER_INFO($_GET['id']);
$_SESSION['SSO'] = $data_sso;

$_SESSION['LOGGED_IN_USER'] = VERIFY_USER_ACCESS($data_sso);

if(isset($_SESSION['SSO']['faultstring']) && $_SESSION['SSO']['faultstring']!=""){
header('Location: index.php');
}

}else if(isset($_SESSION['SSO']['employeename']) && isset($_GET['id'])){	 
$data_sso = $_SESSION['SSO'];
}else{

unset($_SESSION['SSO']);
unset($_SESSION['LOGGED_IN_USER']);
$data_sso='';

}
*/


Function GET_PERMISSIONS_FROM_USER_ID($LinkTestweb,$varSystem,$varUserID) {
	$varSQL = "
		SELECT
		PERMISSIONS
		FROM USER.USERS U,
		USER.USER_ACCESS UA
		WHERE U.ID = UA.USERID
		AND UA.SYSTEM = '".$varSystem."'";
	$RSMain = ExecuteQuery($varSQL,$LinkTestweb);
	While ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
		Return $LineMain["PERMISSIONS"];
	}
	Return Null;
}

Function GET_START_PAGE_FROM_SYSTEM($LinkTestweb2,$LinkTestweb,$varSystem) {
	Switch (True) {
		Case $varSystem == "ROUTE_BUILDER":
			$varRedirect = ROUTE_BUILDER_GET_LAST_PAGE_VISITED($LinkTestweb2);
			If(Trim($varRedirect) != "") {
				Return $varRedirect;
			}
			Break;
	}

	$varSQL = "
		SELECT
		START_PAGE
		FROM USER.SYSTEMS
		WHERE NAME = '".$varSystem."'";
	$RSMain = ExecuteQuery($varSQL,$LinkTestweb);
	While ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
		Return $LineMain["START_PAGE"];
	}
	REturn Null;
}

Function GET_USER_ID_FROM_SSO($LinkTestweb,$arySSO) {
	If(IsSet($arySSO["employeeno"]) && Trim($arySSO["employeeno"]) != "") {
		$varSQL = "
			SELECT
			ID
			FROM USER.USERS
			WHERE WWID = '".$arySSO["employeeno"]."'";
		#Echo $varSQL."<BR>";
		$RSMain = ExecuteQuery($varSQL,$LinkTestweb);
		While ($LineMain = MySQL_Fetch_Assoc($RSMain)) {
			Return $LineMain["ID"];
		}
	}
	Return Null;
}

Function ExecuteQuery($varSQL,$Link) {
	$Result = MySQL_Query($varSQL,$Link) OR ErrorHandler("MYSQL ERROR<BR>".MySQL_Error($Link)."<BR>".$varSQL."<BR>".SubStr(Print_R(debug_backtrace(),True),0,300));
	Return $Result;
}

FUNCTION GET_USER_INFO($l_aParam) {
    require('./nusoap/lib/nusoap.php');
    $l_oClient = new nusoap_client('http://ds2.maxim-ic.com/singlesignon/userauth.php');
    $data_sso = $l_oClient->call('getUserDetailByID', $l_aParam);

    if ( ! $l_oClient->getError() && $data_sso['message'] != "" ) echo '<h3><font color=red><center>xx '.$data_sso['message'] . '</center></font></h3><br>';
    return $data_sso;
}	


Function Print_R2($aryArray) {
	Echo Str_Replace("<BR><BR>","<BR>",Str_Replace("\n","<BR>",Str_Replace(" ","&nbsp;",Print_R($aryArray,True))));
}
Function ErrorHandler($varErrorMessage) {
	Die($varErrorMessage);
}