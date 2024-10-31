<?php

class ParallelGet
{
    var $response=array();
    function __construct($urls)
    {
       // Create get requests for each URL
       $mh = curl_multi_init();

       foreach($urls as $i => $url)
       {
           $ch[$i] = curl_init($url);
           curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
           curl_multi_add_handle($mh, $ch[$i]);
       }

       // Start performing the request
       do {
           $execReturnValue = curl_multi_exec($mh, $runningHandles);
       } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);

       // Loop and continue processing the request
       while ($runningHandles && $execReturnValue == CURLM_OK) 
       {
           // !!!!! changed this if and the next do-while !!!!!

           if (curl_multi_select($mh) != -1) 
           {
               usleep(100);
           }

           do {
               $execReturnValue = curl_multi_exec($mh, $runningHandles);
           } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
       }

       // Check for any errors
       if ($execReturnValue != CURLM_OK) 
       {
           trigger_error("Curl multi read error $execReturnValue\n", E_USER_WARNING);
       }

       // Extract the content
       foreach ($urls as $i => $url)
       {
           // Check for errors
           $curlError = curl_error($ch[$i]);

           if ($curlError == "") 
           {
               $responseContent = curl_multi_getcontent($ch[$i]);
               $res[$i] = $responseContent;
           } 
           else 
           {
               print "Curl error on handle $i: $curlError\n";
           }
           // Remove and close the handle
           curl_multi_remove_handle($mh, $ch[$i]);
           curl_close($ch[$i]);
       }

       // Clean up the curl_multi handle
       curl_multi_close($mh);

       // Print the response data
       //print "response data: " . print_r($res, true);
       $this->response=$res;        
    }
}
/*
$urls = array(
    'https://apps.semadata.org/sdapi/plugin/brand/newdata?AAIA_BrandID=FCMW&TargetDate=2023-09-11&Token=EAAAAK0Fx7naE5y3i-26ZPnV_q0nEQ8xZikh9cbjC7c42mUGvJvGBCfjyh2gop8LeYcNfA&Purpose=test',
    "https://apps.semadata.org/sdapi/plugin/brand/newdata?AAIA_BrandID=BBVR&TargetDate=2023-10-04&Token=EAAAAK0Fx7naE5y3i-26ZPnV_q0nEQ8xZikh9cbjC7c42mUGvJvGBCfjyh2gop8LeYcNfA&Purpose=Shopify"
);

$getter = new ParallelGet($urls);
var_dump($getter);*/
