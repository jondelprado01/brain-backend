<?PHP



Function Open_ILink($varServiceName) {
	$varServiceName = StrToUpper($varServiceName);
	Switch (True) {
		Case $varServiceName == "AFOTCOSJ004":
			$iCon = MySQLI_Connect("afotcosj004.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "MXHPAFOT01L":
			$iCon = MySQLI_Connect("MXHPAFOT01L.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "MXHTAFOT01L":
			$iCon = MySQLI_Connect("MXHTAFOT01L.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "MXHDAFOT01L":
			$iCon = MySQLI_Connect("MXHDAFOT01L.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "TESTWEB2":
			$iCon = MySQLI_Connect("testweb2.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "TESTWEB";
			$iCon = MySQLI_Connect("testwebsvr.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "TESTOPS":
			$iCon = MySQLI_Connect("testops.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "AFOTCODS001":
			$iCon = MySQLI_Connect("afotcods001.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "AFOTCOCV001":
			$iCon = MySQLI_Connect("afotcocv001.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "MFGQTRPLAN":
			$iCon = MySQLI_Connect("mfgqtrplan.maxim-ic.com","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "MFGAPP":
			$iCon = MySQLI_Connect("mfgapp.maxim-ic.com","MITO_R","PASSWORD");
			Break;			
		Case $varServiceName == "AFODEVSVR":
			$iCon = MySQLI_Connect("afodevsvr.maxim-ic.com","MITO_R","PASSWORD");
			Break;			
		Case $varServiceName == "CAVLMYSQLDB09":
			$iCon = MySQLI_Connect("cavlmysqldb09.maxim-ic.com","MITO_R","PASSWORD");
			Break;			
		case $varServiceName == "CAVLAFODEV01":
			$iCon = MySQLI_Connect("cavlafodev01.maxim-ic.com","MITO_R","PASSWORD");
			Break;			
		Case $varServiceName == "LOCALHOST":
			$iCon = MySQLI_Connect("localhost","MITO_R","PASSWORD");
			Break;
		Case $varServiceName == "PACKAGING":
			$iCon = MySQLI_Connect("packaging.maxim-ic.com",'testoperation','reynmito');
			Break;		
	}
	
	Return $iCon;
}


Function ExecuteIQuery($varSQL,$Link) {
	$Result = MySQLI_Query($Link,$varSQL) OR ErrorHandler("MYSQLI ERROR\n".MySQLI_Error($Link)."\n".$varSQL."\n".SubStr(Print_r(debug_backtrace(),True),0,300));
	Return $Result;
}


Function ErrorHandler($varErrorMessage) {
	Die($varErrorMessage);
}




