<?php
/**
 * Created by IntelliJ IDEA.
 * User: leo
 * Date: 2018-05-23
 * Time: 09:25
 */
function callTakApi($url)
{
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    $info = curl_getinfo($curl);
    echo date('c'), '. Request to ', $info['url'];

    $resultJSON = json_decode($result);

    $foundError = false;

    // Check if any error occurred
    if (curl_errno($curl)) {
        echo 'Request Error:' . curl_error($curl);
        $foundError = true;
    }
    if (! $resultJSON) {
        echo "Error, returned data from TAK-api not syntactically correct JSON!\n";
        echo "Data ends with:\n", substr($result, -20);
        $foundError = true;
    }

    echo ' took ', $info['total_time'], ' seconds. Received ', strlen($result), ' bytes and ', count($resultJSON),  " items. \n";

    curl_close($curl);

    if ($foundError) {
        return false;
    }

    return $result;
}
?>