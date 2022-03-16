<?php


class ModelRequests{

    function sendGetCurlRequest($urlRequest = null, $apiKey=null) {
        $result = array('status' => false);
        if (isset($urlRequest, $apiKey)) {
            try {
                $dataArray = ['access_key' => $apiKey];
                $data = http_build_query($dataArray);
                $getUrl = $urlRequest."?".$data;

                $ch = curl_init($urlRequest);

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_URL, $getUrl);
                curl_setopt($ch, CURLOPT_TIMEOUT, 80);
                $response = curl_exec($ch);
                curl_close($ch);
                if($response !== false){
                    $result = array('status' => true, 'response' => (object) json_decode($response));
                }
            }
            catch(Exception $error){
                $result["error"] = $error;
            }
        }
        return $result;
    }

}