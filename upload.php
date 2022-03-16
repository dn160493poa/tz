<?php
require_once 'ModelRequests.php';
$db_path = './geo_db/geo_names.csv';
$continents = array(
    'AF' => 'Africa',
    'AS' => 'Asia',
    'EU' => 'Europe',
    'NA' => 'North America',
    'OC' => 'Oceania',
    'SA' => 'South America',
    'AN' => 'Antarctica'
);


session_start();
$_SESSION['data'] = array();
$message = '';
$location = 'Location: index.php';

if(isset($_POST['uploadBtn']) && $_POST['uploadBtn'] == 'Upload'){
    if(isset($_FILES['uploadedFile']) && $_FILES['uploadedFile']['error'] === UPLOAD_ERR_OK){
        // get details of the uploaded file
        $fileTmpPath = $_FILES['uploadedFile']['tmp_name'];

        $fileName = $_FILES['uploadedFile']['name'];
        $fileSize = $_FILES['uploadedFile']['size'];
        $fileType = $_FILES['uploadedFile']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // sanitize file-name
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

        // check if file has one of the following extensions
        $allowedfileExtensions = array('csv');

        if (in_array($fileExtension, $allowedfileExtensions)){
            // directory in which the uploaded file will be moved
            $uploadFileDir = './uploaded_files/';
            if (!file_exists($uploadFileDir)) {
                mkdir($uploadFileDir, 0777, true);
            }
            $dest_path = $uploadFileDir . $newFileName;

            // read and save uploaded data
            $callsData = array();
            if(move_uploaded_file($fileTmpPath, $dest_path)){
                if(($file = fopen($dest_path, 'r')) !== false){
                    $errors = array();
                    for ($i = 0; $data = fgetcsv($file, 100, ","); $i++){
                        $customerId = isset($data[0]) && preg_match_all('/\d/', $data[0]) ? $data[0] : null;
                        $callDate = $data[1]; isset($data[1]) && preg_match('/^20\d{2}-(0[0-9]|1[0-9])-(0[1-9]|1[0-9]|2[0-9]|3[01])\s{1}(0[0-9]|1[0-9]|2[0-3]):(0[0-9]|[0-5][0-9]:(0[0-9]|[0-5][0-9]))$/',
                            $data[1]) ? $data[1] : null;
                        $duration = isset($data[2]) && preg_match_all('/\d/', $data[2]) ? $data[2] : null;
                        $dialedPhoneNumber = isset($data[3]) && preg_match_all('/\d/', $data[3]) ? $data[3] : null;
                        $customerIp = isset($data[4]) && preg_match('/([0-9]{1,3}[\.]){3}[0-9]{1,3}/', $data[4]) ? $data[4] : null;
                        if(isset($customerId, $callDate, $duration, $dialedPhoneNumber, $customerIp)){
                            $obj = (object) array(
                                'customerId' => $customerId,
                                'callDate' => $callDate,
                                'duration' => $duration,
                                'dialedPhoneNumber' => $dialedPhoneNumber,
                                'customerIp' => $customerIp
                            );
                            array_push($callsData, $obj);
                        }else{
                            array_push($errors, $i);
                        }
                    }
                    fclose($file);
                }

                if(count($errors) > 0){
                    $lines = '';
                    foreach ($errors as $error){
                        $lines .= $error . ' ';
                    }
                    $message .= "You have error field format in lines : $lines";
                }else{
                    $message = 'File is successfully uploaded.';

                    // get all geo data
                    $geoDataArr = array();
                    if(($file = fopen($db_path, 'r')) !== false){
                        for ($y = 0; $geoData = fgetcsv($file, 100, ","); $y++) {
                            $obj = (object) array(
                                'countryCode' => $geoData[12],
                                'continentCode' => $geoData[8],
                                'countryName' => $geoData[4]
                            );
                            array_push($geoDataArr, $obj);
                        }
                        fclose($file);
                    }

                    // add missing data to calls
                    for($i = 0; $i < count($callsData); $i++){
                        $dialedPhoneNumber = $callsData[$i]->dialedPhoneNumber;
                        $phoneCode = substr($dialedPhoneNumber, 0, strlen($dialedPhoneNumber)-9);

                        for($y = 0; $y < count($geoDataArr); $y++){
                            $countryCode = $geoDataArr[$y]->countryCode;
                            if($phoneCode === $countryCode){
                                $callsData[$i]->reqContinentName = $continents[$geoDataArr[$y]->continentCode];
                                $callsData[$i]->reqContinentCode = $geoDataArr[$y]->continentCode;
                                $callsData[$i]->contryName = $geoDataArr[$y]->countryName;
                            }
                        }
                    }

                    // customers by calls
                    $allCustomersId = array();
                    $allClients = array();
                    $req = new ModelRequests;
                    for($i = 0; $i < count($callsData); $i++){
                        $customerId = $callsData[$i]->customerId;
                        if(!in_array($customerId, $allCustomersId)){
                            $customerIp = $callsData[$i]->customerIp;
                            $uniqCustomer = (object) array(
                                'customerId' => $customerId,
                                'customerIp' => $customerIp
                            );
                            array_push($allCustomersId, $customerId);

                            // For test Request API is limited
                            //$uniqCustomer->continentNameFrom = 'Europe';
                            //$uniqCustomer->continentCodeFrom = 'EU';
                            $curlRequestData = $req->sendGetCurlRequest("http://api.ipstack.com/$customerIp", '4d8e86cea01e4414e7061eb67e19d3ce');
                            if($curlRequestData["status"]) {
                                $uniqCustomer->continentNameFrom = $curlRequestData["response"]->continent_name;
                                $uniqCustomer->continentCodeFrom = $curlRequestData["response"]->continent_code;
                            }
                            array_push($allClients, $uniqCustomer);
                        }
                    }

                    // creating answer
                    $result = array();
                    foreach ($allClients as $oneClient){
                        $clientId = $oneClient->customerId;
                        $clientIp = $oneClient->customerIp;
                        $clientContinentName = $oneClient->continentNameFrom;
                        $clientContinentCode = $oneClient->continentCodeFrom;

                        // start data
                        $toSameContinentDuration  = 0;
                        $toSameContinentCalls = 0;
                        $totalDuration = 0;
                        $totalCalls = 0;

                        for($u = 0; $u < count($callsData); $u++){
                            if($clientId === $callsData[$u]->customerId){
                                if($clientContinentCode === $callsData[$u]->reqContinentCode){
                                    $toSameContinentDuration += $callsData[$u]->duration;
                                    $toSameContinentCalls++;
                                }
                                $totalDuration += $callsData[$u]->duration;
                                $totalCalls++;
                            }
                        }

                        $obj = (object) array(
                            'customerId' => $clientId,
                            'toSameContinentCalls' => $toSameContinentCalls,
                            'toSameContinentDuration' => $toSameContinentDuration,
                            'totalCalls' => $totalCalls,
                            'totalDuration' => $totalDuration
                        );

                        array_push($result, $obj);
                    }

                    $_SESSION['data'] = $result;
                    $location = 'Location: result.php';
                }
            }else{
                $message = 'There was some error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
            }
        }else{
            $message = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
        }
    }else{
        $message = 'There is some error in the file upload. Please check the following error.<br>';
        $message .= 'Error:' . $_FILES['uploadedFile']['error'];
    }
}

$_SESSION['message'] = $message;
header($location);