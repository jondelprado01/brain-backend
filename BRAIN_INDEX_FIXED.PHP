<head>

    <script src="../JS/jquery-3.7.1/jquery-3.7.1.js"></script>
	<link href="../JS/datatables/datatables.css" rel="stylesheet">
 	<script src="../JS/datatables/datatables.js"></script>

 	<script src="../JS/datatables/buttons.print.min.js"></script>
 	<script src="../JS/datatables/buttons.html5.min.js"></script>
 	<script src="../JS/datatables/jszip.min.js"></script>
 	<script src="../JS/datatables/buttons.dataTables.js"></script>
 	<script src="../JS/datatables/dataTables.buttons.js"></script>

</head>
<body>
	<h3>All Updates are Disabled!</h3>
<table class="inputs">
	<thead>
		<tr><th colspan=3>Range Filters</th></tr>
		<tr><th>Field</th><th>Min</th><th>Max</th></tr>
	</thead>
	<tbody>
	<tr>
		<td>UTPI</td>
	    <td><input type="text" id="minUTPI" name="minUTPI" placeholder="1,2,3 etc."></td>
	    <td><input type="text" id="maxUTPI" name="maxUTPI" placeholder="4,8,16..."></td>
	</tr>
	<tr>
		<td>Index Value</td>
	    <td><input type="text" id="minIV" name="minIV" placeholder="0.2, 0.45, etc."></td>
	    <td><input type="text" id="maxIV" name="maxIV" placeholder="3,5, ..."></td>
	</tr>
    </tbody>
</table>

<?php
include_once("/var/www/html/MYSQLI_COMMON_FUNCTIONS.PHP");



$iConRLM = Open_ILink("MXHTAFOT01L");

$aryFIT = FIXED_INDEX_GET_V2($iConRLM,$bolDebug);

FIXED_INDEX_RENDER($aryFIT,$bolDebug);


mysqli_close($iConRLM);




?>

<script>
	const minIV = document.querySelector('#minIV');
	const maxIV = document.querySelector('#maxIV');
	const minUTPI = document.querySelector('#minUTPI');
	const maxUTPI = document.querySelector('#maxUTPI');

	const table = new DataTable('#mainTable', 
		{
			columns: [
				{ data: 'HANDLER' },
				{ data: 'PKG_TYPE' },
				{ data: 'BODY_SIZE' },
				{ data: 'LEAD_COUNT_MIN' },
				{ data: 'LEAD_COUNT_MAX' },
				{ data: 'TEMP_CLASS' },
				{ data: 'UTPI' },
				{ data: 'INDEX_EFFECTIVE' }
			],
			colReorder: {
				columns: ':not(:first-child)'
			},
			columnControl: [
				{
					target: 0,
					content: ['order']
				},
				{
					target: 1,
					content: ['search']
				}
			],
			ordering: {
		        indicators: false,
		        handler: false
		    },
		    fixedHeader: true,
			autoFill: true,
			keys: true,
			layout: {
				topStart: {
					buttons: ['copy', 'csv', 'excel']
				}
			}
		});

	table.search.fixed('range', function (searchStr, data, index) {
	    var srcMinUTPI = parseInt(minUTPI.value, 10);
	    var srcMaxUTPI = parseInt(maxUTPI.value, 10);
	    var srcUTPI = parseInt(data['UTPI']) || 0; // use data for the LC column

	    var srcMinIV = parseFloat(minIV.value, 10);
	    var srcMaxIV = parseFloat(maxIV.value, 10);
	    var srcIV = parseFloat(data['INDEX_VALUE'], 10); // use data for the LC column
	    //var LC = parseFloat(data[0]) || 0; // use data for the LC column
	 
	    if (
	        (
	        	(isNaN(srcMinIV) && isNaN(srcMaxIV)) ||
	        	(isNaN(srcMinIV) && srcIV <= srcMaxIV) ||
	        	(srcMinIV <= srcIV && isNaN(srcMaxIV)) ||
	        	(srcMinIV <= srcIV && srcIV <= srcMaxIV)
        	)
        	&&
	        (
	        	(isNaN(srcMinUTPI) && isNaN(srcMaxUTPI)) ||
	        	(isNaN(srcMinUTPI) && srcUTPI <= srcMaxUTPI) ||
	        	(srcMinUTPI <= srcUTPI && isNaN(srcMaxUTPI)) ||
	        	(srcMinUTPI <= srcUTPI && srcUTPI <= srcMaxUTPI)
        	)
	    ) {
	        return true;
	    }
	 
	    return false;
	});

	minUTPI.addEventListener('input', function () {
	    table.draw();
	});
	maxUTPI.addEventListener('input', function () {
	    table.draw();
	});
	minIV.addEventListener('input', function () {
	    table.draw();
	});
	maxIV.addEventListener('input', function () {
	    table.draw();
	});

</script>

<?php

function FIXED_INDEX_RENDER($aryFIT,$bolDebug=false) {
	echo "<table id=\"mainTable\" class=\"display compact cell-border\">";
	#echo "<TABLE ID=\"tblFixed\" class=\"table table-striped\" BORDER=1 CELLSPACING=0 CELLPADDING=1>";
	echo "<THEAD BGCOLOR=SILVER>";
	echo "<tr><th>Handler</th><th>Package Type</th><th>Body Size</th><th>Leads Min</th><th>Leads Max</th><th>Temp Class</th><th>UTPI</th><th>Fixed Effective Index Time</th></tr>";
	echo "</THEAD><TBODY CLASS=B1 BGCOLOR=#FFFFFF>";

	$intCtr = 0;
	foreach($aryFIT as $intISDID => $aryFITTemp) {
		$aryLine = array(
			"HANDLER"=>$aryFITTemp["HANDLER"],
			"PKG_TYPE"=>$aryFITTemp["PKG_TYPE"],
			"BODY_SIZE"=>$aryFITTemp["BODY_SIZE"],
			"LEAD_COUNT_MIN"=>$aryFITTemp["LEAD_COUNT_MIN"],
			"LEAD_COUNT_MAX"=>$aryFITTemp["LEAD_COUNT_MAX"],
			"TEMP_CLASS"=>$aryFITTemp["TEMP_CLASS"],
			"UTPI"=>$aryFITTemp["UTPI"],
			"INDEX_VALUE"=>$aryFITTemp["INDEX_VALUE"]
		);

		#Print_R2($aryLine);
		#die();

		#echo "<tr><td>".implode("</td><td>",$aryLine)."</td></tr>";
		if($intCtr == 0) {
			echo "<tr id=\"tr_111\">";
		}else{
			echo "<tr>";
		}
		echo "<td>".$aryLine["HANDLER"]."</td>";
		echo "<td>".$aryLine["PKG_TYPE"]."</td>";
		echo "<td>".$aryLine["BODY_SIZE"]."</td>";
		echo "<td>".$aryLine["LEAD_COUNT_MIN"]."</td>";
		echo "<td>".$aryLine["LEAD_COUNT_MAX"]."</td>";
		echo "<td>".$aryLine["TEMP_CLASS"]."</td>";
		echo "<td>".$aryLine["UTPI"]."</td>";
		echo "<td>".number_format($aryLine["INDEX_VALUE"],3)."</td>";
		echo "</tr>";

		++$intCtr;
	}

	echo "</TBODY></TABLE>";


}


function FIXED_INDEX_GET_V2($iConRLM,$bolDebug=false) {
	$strSQL = "
		SELECT
		ISD_ID,
		ISM_ID,
		HANDLER,
		PACKAGE PKG_TYPE,
		BODY_SIZE,
		LEAD_COUNT_MIN,
		LEAD_COUNT_MAX,
		TEMP_CLASS,
		UTPI,
		INDEX_VALUE		
		FROM BRAIN.V_INDEX_SPEC
		WHERE INDEX_VALUE IS NOT NULL
		ORDER BY HANDLER, PKG_TYPE, IFNULL(LEAD_COUNT_MIN,0), IFNULL(LEAD_COUNT_MAX,99), TEMP_CLASS, UTPI;";
	$RSMain = ExecuteIQuery($strSQL,$iConRLM);
	$aryReturn = array();
	while ($LineMain = mysqli_fetch_assoc($RSMain)) {
		$intISDID = $LineMain["ISD_ID"];
		$aryTemp = $LineMain;
		unset($aryTemp["ISD_ID"]);
		$aryReturn[$intISDID] = $aryTemp;
	}

	return $aryReturn;
}


function Print_R2($aryArray) {
	echo str_replace("<BR><BR>","<BR>",str_replace("\n","<BR>",str_replace(" ","&nbsp;",print_r($aryArray,true))));
}