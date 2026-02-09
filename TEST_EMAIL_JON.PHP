<?php

ini_set("SMTP", "testwebsvr.maxim-ic.internal");
    ini_set("smtp_port", 25);

    $aryHeader = Array();
    $aryHeader[] = "MIME-Version: 1.0";
    $aryHeader[] = "Content-type:text/html;charset=UTF-8";
    $aryHeader[] = "Content-Transfer-Encoding: base64";
    $aryHeader[] = "From: do_not_reply@brain.analog.com";

    // $headers = "From: do_not_reply@brain.analog.com \r\n";
 
    // $headers .= "Content-Type: text/html; charset=utf-8; MIME-Version: 1.0\r\n";
    $varMessage = chunk_split(base64_encode(implode("<BR>",["TEST EMAIL","TEST EMAIL","TEST EMAIL","TEST EMAIL","TEST EMAIL","TEST EMAIL"])));
    $result = mail(implode(",",["jonathan.delprado@analog.com","jonathandelprado60@gmail.com","jonathandelprado60@gmail.com",]), implode(",", ["BRAIN","BRAIN","BRAIN","BRAIN"]),$varMessage, implode("\n",$aryHeader));
    var_dump($varMessage);
    if ($result) {
        echo "Mail sent successfully.";
    } else {
        echo "Mail failed to send.";
    }

?>