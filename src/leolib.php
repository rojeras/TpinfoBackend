<?php
/**
    Copyright (C) 2013-2018 Lars Erik Röjerås

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <https://www.gnu.org/licenses/>.
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

function leoGetenv($envVar) {
    $envValue = getenv($envVar);

    if (! $envValue) {
        echo "** WARNING mandatory environment variable is not set: " . $envVar . "\n";
    }
    return $envValue;
}
?>