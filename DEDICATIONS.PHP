<?php

    header('Access-Control-Allow-Origin: *');
    include_once("../MYSQLI_COMMON_FUNCTIONS.PHP");
    include_once("ADI_FUNCTIONS.PHP");
    ini_set("error_reporting",E_ALL);
    ini_set("display_errors",1);

    $aryRequest = $_REQUEST;
    $requestMedthod = $_SERVER['REQUEST_METHOD'];
    date_default_timezone_set('Asia/Manila');
    $iConRLM = Open_ILink("MXHTAFOT01L");

    if ($requestMedthod != "POST") {
        exit("Invalid HTTP Method!");
    }

    $bolDebug = false;
    if(isset($aryRequest["DEBUG"]) && $aryRequest["DEBUG"] != "") {
        $bolDebug=true;
    }

    $bolJSON = true;
    if(isset($aryRequest["NO_JSON"]) && $aryRequest["NO_JSON"] != "") {
        $bolJSON = false;
    }

    if(isset($aryRequest["PROCESS_TYPE"]) && $aryRequest["PROCESS_TYPE"] != "") {
        $process = $aryRequest["PROCESS_TYPE"];
    }

    if ($process == "GET_TESTER_HANDLER") {
        print_r(json_encode(GET_TESTER_HANDLER($iConRLM, $_POST['data'], $_POST['site'], $_POST['module_type'])));
    }

    mysqli_close($iConRLM);

    function GET_TESTER_HANDLER($iConRLM, $data, $site, $module = null){
        
        $type = ['TESTER', 'HANDLER'];
        $res_area = $data[2][0];
        $tester = implode('","', array_unique($data[0]));
        $handler = implode('","', array_unique($data[1]));
        $tester_data = [];
        $handler_data = [];
        $tester_header = [];
        $handler_header = [];
        $new_tester_header = [];
        $new_handler_header = [];
        $default_tester_header = [];
        $default_handler_header = [];

        if ($module != null) {
            $other_testers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'TESTER', 'DEDICATION')['DATA'][0];
            $other_handlers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'HANDLER', 'DEDICATION')['DATA'][0];
    
            foreach (array_unique(array_keys($other_testers)) as $key => $ott) {
                $header_key = $ott;
                foreach (array_keys($other_testers[$ott]) as $key2 => $ottv) {
                    $item_key = $ottv;
                    // if ($other_testers[$header_key][$item_key]['CONFIG_PCNT'] > 0) {
                        array_push($tester_data, [
                            'SITE_NUM'  => $site,
                            'RES_TYPE'  => 'TESTER',
                            'ENG_NAME'  => $header_key,
                            'ATOM_NAME' => $item_key
                        ]);
                    // }
                }
                array_push($tester_header, $header_key);
            }
    
            foreach (array_unique(array_keys($other_handlers)) as $key => $oth) {
                $header_key = $oth;
                foreach (array_keys($other_handlers[$oth]) as $key2 => $othv) {
                $item_key = $othv;
                    // if ($other_handlers[$header_key][$item_key]['CONFIG_PCNT'] > 0) {
                        array_push($handler_data, [
                            'SITE_NUM'  => $site,
                            'RES_TYPE'  => 'HANDLER',
                            'ENG_NAME'  => $header_key,
                            'ATOM_NAME' => $item_key
                        ]);
                    // }
                }
                array_push($handler_header, $header_key);
            }
        }
        else{

            // $eng_config_testers = GET_OTHER_TESTER_HANDLER($site, 'TESTER', 'GET_ENG_CONFIG', $tester)['DATA'][0];
            // $eng_config_handlers = GET_OTHER_TESTER_HANDLER($site, 'HANDLER', 'GET_ENG_CONFIG', $handler)['DATA'][0];
            // $ect = (count(array_keys($eng_config_testers)) > 0) ? array_keys($eng_config_testers)[0] : $tester;
            // $ecf = (count(array_keys($eng_config_handlers)) > 0) ? array_keys($eng_config_handlers)[0] : $handler;

            // if (count(array_keys($eng_config_testers)) > 0) {
            //     $ect = array_keys($eng_config_testers)[0];
            //     array_push($new_tester_header, array_keys($eng_config_testers)[0]);
            // }
            // else{
            //     $ect = $tester;
            // }

            // if (count(array_keys($eng_config_handlers)) > 0) {
            //     $ecf = array_keys($eng_config_handlers)[0];
            //     array_push($new_handler_header, array_keys($eng_config_handlers)[0]);
            // }
            // else{
            //     $ecf = $handler;
            // }

            // $additional_testers = GET_OTHER_TESTER_HANDLER($site, 'TESTER', 'NORMAL', $ect)['DATA'][0];
            // $additional_handlers = GET_OTHER_TESTER_HANDLER($site, 'HANDLER', 'NORMAL', $ecf)['DATA'][0];

            $additional_testers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'TESTER', 'NORMAL', $tester)['DATA'][0];
            $additional_handlers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'HANDLER', 'NORMAL', $handler)['DATA'][0];

            foreach (array_unique(array_keys($additional_testers)) as $key => $att) {
                $header_key = $att;
                foreach (array_keys($additional_testers[$att]) as $key2 => $attv) {
                    $item_key = $attv;
                    // if ($additional_testers[$header_key][$item_key]['CONFIG_PCNT'] > 0) {
                        array_push($tester_data, [
                            'SITE_NUM'  => $site,
                            'RES_TYPE'  => 'TESTER',
                            'ENG_NAME'  => $header_key,
                            'ATOM_NAME' => $item_key
                        ]);
                    // }
                }
            }
    
            foreach (array_unique(array_keys($additional_handlers)) as $key => $ath) {
                $header_key = $ath;
                foreach (array_keys($additional_handlers[$ath]) as $key2 => $athv) {
                $item_key = $athv;
                    // if ($additional_handlers[$header_key][$item_key]['CONFIG_PCNT'] > 0) {
                        array_push($handler_data, [
                            'SITE_NUM'  => $site,
                            'RES_TYPE'  => 'HANDLER',
                            'ENG_NAME'  => $header_key,
                            'ATOM_NAME' => $item_key
                        ]);
                    // }
                }
            }

            if (count($tester_data) == 0) {
                // $get_config_testers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'TESTER', 'GET_ENG_CONFIG', $tester)['DATA'][0];
                // if (count($get_config_testers) > 0) {
                //     foreach (array_unique(array_keys($get_config_testers)) as $key => $gct) {
                //         $header_key = $gct;
                //         foreach (array_keys($get_config_testers[$gct]) as $key2 => $gctv) {
                //             $item_key = $gctv;
                //             array_push($tester_data, [
                //                 'SITE_NUM'  => $site,
                //                 'RES_TYPE'  => 'TESTER',
                //                 'ENG_NAME'  => $header_key,
                //                 'ATOM_NAME' => $item_key,
                //             ]);
                //         }
                //         array_push($tester_header,$header_key);
                //     }
                // }
                // else{

                    $other_testers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'TESTER', 'DEDICATION')['DATA'][0];

                    $target_tester_key = "";
                    foreach (array_unique(array_keys($other_testers)) as $key => $ott) {
                        $header_key = $ott;
                        foreach (array_keys($other_testers[$ott]) as $key2 => $ottv) {
                            $item_key = $ottv;
                            if (strpos($item_key, $tester) !== false) {
                               $target_tester_key = $header_key;
                               break;
                            }
                        }
                    }

                    if ($target_tester_key != "") {
                        foreach (array_keys($other_testers[$target_tester_key]) as $key2 => $ottv) {
                            $atom_item_exists = false;
                            $item_key = $ottv;
                            array_push($tester_data, [
                                'SITE_NUM'  => $site,
                                'RES_TYPE'  => 'TESTER',
                                'ENG_NAME'  => $target_tester_key,
                                'ATOM_NAME' => $item_key,
                            ]);
                            
                            $td_key = "ATOM_NAME";
                            $td_val = $tester;
                            array_walk_recursive($tester_data, function($v, $k) use ($td_key, $td_val, &$atom_item_exists) {
                                if ($k === $td_key && $v === $td_val) {
                                    $atom_item_exists = true;
                                }
                            });

                            if (!$atom_item_exists) {
                                if ($tester != $item_key) {
                                    array_push($tester_data, [
                                        'SITE_NUM'  => $site,
                                        'RES_TYPE'  => 'TESTER',
                                        'ENG_NAME'  => $target_tester_key,
                                        'ATOM_NAME' => $tester,
                                    ]);
                                }
                            }

                        }

                        array_push($tester_header,$target_tester_key);
                        usort($tester_data, function($a, $b) {
                            return strcmp($a['ATOM_NAME'], $b['ATOM_NAME']);
                        });
                    }
                // }
            }
            else{
                array_push($tester_header,$tester);
            }

            if (count($handler_data) == 0) {
                // $get_config_handlers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'HANDLER', 'GET_ENG_CONFIG', $handler)['DATA'][0];
                // if (count($get_config_handlers) > 0) {
                //     foreach (array_unique(array_keys($get_config_handlers)) as $key => $gch) {
                //         $header_key = $gch;
                //         foreach (array_keys($get_config_handlers[$gch]) as $key2 => $gchv) {
                //         $item_key = $gchv;
                //             array_push($handler_data, [
                //                 'SITE_NUM'  => $site,
                //                 'RES_TYPE'  => 'HANDLER',
                //                 'ENG_NAME'  => $header_key,
                //                 'ATOM_NAME' => $item_key,
                //             ]);
                //         }
                //         array_push($handler_header,$header_key);
                //     }
                // }
                // else{

                    $other_handlers = GET_OTHER_TESTER_HANDLER($site, $res_area, 'HANDLER', 'DEDICATION')['DATA'][0];

                    $target_handler_key = "";
                    foreach (array_unique(array_keys($other_handlers)) as $key => $oth) {
                        $header_key = $oth;
                        foreach (array_keys($other_handlers[$oth]) as $key2 => $othv) {
                            $item_key = $othv;
                            if (strpos($item_key, $handler) !== false) {
                               $target_handler_key = $header_key;
                               break;
                            }
                        }
                    }

                    if ($target_handler_key != "") {
                        foreach (array_keys($other_handlers[$target_handler_key]) as $key2 => $othv) {
                            $atom_item_exists = false;
                            $item_key = $othv;
                            array_push($handler_data, [
                                'SITE_NUM'  => $site,
                                'RES_TYPE'  => 'HANDLER',
                                'ENG_NAME'  => $target_handler_key,
                                'ATOM_NAME' => $item_key,
                            ]);
                            
                            $td_key = "ATOM_NAME";
                            $td_val = $handler;
                            array_walk_recursive($handler_data, function($v, $k) use ($td_key, $td_val, &$atom_item_exists) {
                                if ($k === $td_key && $v === $td_val) {
                                    $atom_item_exists = true;
                                }
                            });

                            if (!$atom_item_exists) {
                                if ($handler != $item_key) {
                                    array_push($handler_data, [
                                        'SITE_NUM'  => $site,
                                        'RES_TYPE'  => 'HANDLER',
                                        'ENG_NAME'  => $target_handler_key,
                                        'ATOM_NAME' => $handler,
                                    ]);
                                }
                            }
                        }

                        array_push($handler_header,$target_handler_key);
                        usort($handler_data, function($a, $b) {
                            return strcmp($a['ATOM_NAME'], $b['ATOM_NAME']);
                        });
                    }

                    // array_push($handler_header,$handler);
                    // array_push($handler_data, [
                    //     'SITE_NUM'  => $site,
                    //     'RES_TYPE'  => 'HANDLER',
                    //     'ENG_NAME'  => $handler,
                    //     'ATOM_NAME' => $handler,
                    // ]);
                // }
            }
            else{
                array_push($handler_header,$handler);
            }
        }

        // $get_data = 'SELECT SITE_NUM, RES_TYPE, ENG_NAME, ATOM_NAME FROM BRAIN.ENG_ATOM WHERE MANUALLY_EXCLUDED IS NULL AND SITE_NUM = "'.$site.'" AND RES_TYPE = "TESTER" AND ENG_NAME IN ("'.$tester.'")
        //             UNION
        //             SELECT SITE_NUM, RES_TYPE, ENG_NAME, ATOM_NAME FROM BRAIN.ENG_ATOM WHERE MANUALLY_EXCLUDED IS NULL AND SITE_NUM = "'.$site.'" AND RES_TYPE = "HANDLER" AND ENG_NAME IN ("'.$handler.'")';

        // $get_data_res = ExecuteIQuery($get_data,$iConRLM);
        // while($row = mysqli_fetch_assoc($get_data_res)){
        //     if ($row['RES_TYPE'] == 'TESTER') {
        //         $tester_key = $row['ATOM_NAME'];
        //         $existing_tester = array_map(function($item) {
        //             return $item['ATOM_NAME'];
        //         }, $tester_data);

        //         if (!in_array($tester_key, $existing_tester)) {
        //             array_push($tester_data, $row);
        //             $default_tester_header = array_values(array_unique($data[0]));
        //         }
        //     }
        //     else{
        //         $handler_key = $row['ATOM_NAME'];
        //         $existing_handler = array_map(function($item) {
        //             return $item['ATOM_NAME'];
        //         }, $handler_data);

        //         if (!in_array($handler_key, $existing_handler)) {
        //             array_push($handler_data, $row);
        //             $default_handler_header = array_values(array_unique($data[1]));
        //         }
        //     }
        // }

        // if (count($default_tester_header) == 0) {
        //     $default_tester_header = $new_tester_header;
        // }

        // if (count($default_handler_header) == 0) {
        //     $default_handler_header = $new_handler_header;
        // }

        // if ($module != null) {
        //     array_push($default_tester_header,$tester);
        //     array_push($default_handler_header,$handler);
        //     $tester_header = array_unique(array_merge($default_tester_header, array_unique(array_keys($other_testers))));
        //     $handler_header = array_unique(array_merge($default_handler_header, array_unique(array_keys($other_handlers))));
        // }
        // else{
        //     array_push($tester_header,$tester);
        //     array_push($handler_header,$handler);
        // }

        return [
            'TESTER_HEADER' => $tester_header,
            'HANDLER_HEADER' => $handler_header,
            'TESTER' => $tester_data,
            'HANDLER' => $handler_data,
        ];
    }

    function GET_OTHER_TESTER_HANDLER($site, $res_area, $res_type, $module_type, $config_data = null){
        $results = [];

        switch ($module_type) {
            case 'NORMAL':
                $param = '[{ "SITE_NUM":["'.$site.'"],"RESOURCE_TYPE":["'.$res_type.'"],"ENG_CONFIG":["'.$config_data.'"], "RES_AREA":["'.$res_area.'"] }]';
                break;

            case 'GET_ENG_CONFIG':
                $param = '[{ "SITE_NUM":["'.$site.'"],"RESOURCE_TYPE":["'.$res_type.'"],"RES_NM":["'.$config_data.'"], "RES_AREA":["'.$res_area.'"] }]';
                break;
            
            default:
                $param = '[{"SITE_NUM":["'.$site.'"],"RESOURCE_TYPE":["'.$res_type.'"], "RES_AREA":["'.$res_area.'"] }]';
                break;
        }

        $url = 'http://afotcosj004.maxim-ic.com/API/MAPPER_EQUIP.PHP?INPUT_TYPE=JSON&OUTPUT_TYPE=ENG_ATOM&INPUT='.urlencode($param);

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Accept: application/json')
            ));
            $results = json_decode(curl_exec($curl), true);
            curl_close($curl);
        }
        catch(Exception $e) {
            $results = 'Message: ' .$e->getMessage();
        }

        return $results;
    }
?>