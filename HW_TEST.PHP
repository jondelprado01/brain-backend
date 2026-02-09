<?php
header("Access-Control-Allow-Origin: *");
include_once("/var/www/html/MYSQLI_COMMON_FUNCTIONS.PHP");


$iConRLM = Open_ILink("localhost");

$strSQL = "
	SELECT DISTINCT
	HW_TYPE, 
	REPLACE(HW_NM,'_ZEROCAP','') HW_NM
	FROM BODS_JDA_ADI_EXPORT.HARDWARE_SET
	WHERE HW_NM NOT LIKE '%FAKE'
	
	UNION

	SELECT DISTINCT
	HW_TYPE, 
	REPLACE(HW_NM,'_ZEROCAP','') HW_NM
	FROM BODS_JDA_ADI_EXPORT.HARDWARE_ALTERNATES
	WHERE HW_NM NOT LIKE '%FAKE'

	ORDER BY HW_TYPE, HW_NM";
$RSMain = ExecuteIQuery($strSQL,$iConRLM);
$aryHW = array();
while ($LineMain = mysqli_fetch_assoc($RSMain)) {
	$strType = $LineMain["HW_TYPE"];
	$strName = $LineMain["HW_NM"];

	$aryTemp = array(
		"SOURCE_QTY"=>1,
		"EFF_START_DT"=>array(
			"2011-11-01"=>2,
			"2025-03-30"=>3
		)
	);

	if($strType == "BURNIN_BOARD") {
		$aryTemp["SOCKET_EFF"] = 0.98;
	}

	$aryHW[$strType][$strName] = $aryTemp;

}
#$jsnHW = json_encode($aryHW);
#echo $jsnHW;


$aryReturn = array(
	"STATUS"=>"SUCCESS",
	"MESSAGE"=>"",
	"DATA"=>$aryHW
);

if(!isset($_REQUEST["NO_JSON"]) || trim($_REQUEST["NO_JSON"]) == "") {
	echo json_encode($aryReturn);
}else{
	print_R2($aryReturn);
}

mysqli_close($iConRLM);



Function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_R($aryArray,true))));
}
