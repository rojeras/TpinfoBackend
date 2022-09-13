<?php
/**
 * Copyright (C) 2013-2018 Lars Erik Röjerås
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

// todo: Ensure all integration calls are done with dates specified - otherwise do not cache
require_once 'leolib.php';

error_reporting(E_ALL);
ini_set('memory_limit', leoGetenv('TPDBAPI_MEMORY_LIMIT'));
date_default_timezone_set('Europe/Stockholm');

$VERSION = '6.2';
$DEPLOYDATE = '2019-01-27';

require 'leolib_sql.php';
require_once 'leolib.php';

$serverName = $_SERVER['SERVER_NAME'];

header('Access-Control-Allow-Origin: *');
header("Content-type:application/json");

$scriptName = basename(__FILE__, 'tpdbapi.php');

if (isset($_SERVER['QUERY_STRING'])) {
    $queryString = $_SERVER['QUERY_STRING'];
} else {
    $queryString = '';
}

$requestURI = $_SERVER['REQUEST_URI'];
$TYPE = substr(strrchr($requestURI, '/'), 1);
$TYPE = str_replace('?' . $queryString, "", $TYPE);
define('TYPE', $TYPE);

echo callCache($TYPE, $queryString);

exit;
// End of main program
// ===============================================================================================
function callCache($resource, $queryString) {
    $cache_path = 'cache/';

    if ($resource == 'reset') {
        array_map('unlink', glob($cache_path . "*.cache"));
        exit();
    }

    if ($resource == 'version') {
        return file_get_contents('versionInfo.json');
    }

    $filename = md5($queryString) . '.' . $resource . '.cache';
    $filepath = $cache_path . $filename;

   // if (!file_exists($filepath) || (time() - 84600 > filemtime($filepath))) {
    if (!file_exists($filepath)) {
        parse_str($queryString, $queryArr);
        $data = callDb($resource, $queryArr);
        // Only create/update the file if the call succeeded
        if ($data) {
            file_put_contents($filepath, $data);
        }
    }
    return file_get_contents($filepath);
}

// Definitions of the API
function callDb($resource, $queryArr)
{
    GLOBAL $DBCONN;
    $DBCONN = sqlConnectEnvs();

    switch ($resource) {
        case "plattforms":
            $resultArr = getConnectionPointArray();
            $answer = json_encode($resultArr);
            break;

        case "dates":
            $resultArr = getDateArray();

            $arr = array(
                "dates" => $resultArr,
            );
            $answer = json_encode($arr);
            break;

        case "components":
            $resultArr = getComponentArray();
            $answer = json_encode($resultArr);
            break;

        case "contracts":
            $resultArr = getServiceContractArray();
            $answer = json_encode($resultArr);
            break;
        case "domains":
            $resultArr = getServiceDomainArray();
            $answer = json_encode($resultArr);
            break;

        case "logicalAddress":
            $resultArr = getLogicalAddressArray();
            $answer = json_encode($resultArr);
            break;

        case "plattformChains":
            $resultArr = getPlattformChainArray();
            $answer = json_encode($resultArr);
            break;

        case "counters":
            $resultArr = getMaxCountersArray('2018-05-27', '2018-05-29');
            $answer = json_encode($resultArr);
            break;

        // Returns information about which plattforms have statistics data
        case "statPlattforms":
            $answerArr = getStatPlattformArray();
            $answer = json_encode($answerArr);
            break;

        case "integrations":
            $answerArr = getIntegrationArray($queryArr);
            $answer = json_encode($answerArr);
            break;

        case "statistics":
            $answerArr = getStatisticsArray($queryArr);
            $answer = json_encode($answerArr);
            break;

        case "history":
            $answerArr = getHistoryArrayV2($queryArr);
            $answer = json_encode($answerArr);
            break;

        case "currentItems":
            $answerArr = getCurrentItemsArray($queryArr);
            $answer = json_encode($answerArr);
            break;

        case "ping":
            $answer = "PING ANSWER...";
            break;
        default:
            die("tpdbapi: *** ERROR - unknown item: " . $resource);
    }
    $DBCONN->close();
    return $answer;
}

function getConnectionPointArray()
{

    $select = "
         SELECT  
          id, 
          name, 
          environment, 
          lastSnapshot
         FROM TakPlattform
         ORDER BY id 
         ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['id'],
            "platform" => $row['name'],
            "environment" => $row['environment'],
            "snapshotTime" => $row['lastSnapshot']
        );
        $resultArr[] = $recordArr;
    }

    return $resultArr;
}

function getLastDate()
{

    $select = " 
        SELECT   
          MAX(dateEnd) AS DateEnd
        FROM
          TakIntegration
        ";

    $result = sqlSelectPrep($select, "", array());

    $row = $result->fetch_assoc();

    return $row['DateEnd'];

}

function getDateArray()
{
    // First collect all dates when the TAKs have changed
    $select = " 
        SELECT DISTINCT 
          dateEffective AS date
        FROM
          TakIntegration 
        
        UNION DISTINCT 
        SELECT DISTINCT 
          dateEnd AS date
        FROM
          TakIntegration
          
        ORDER BY date DESC  
        ";

    $result = sqlSelectPrep($select, "", array());

    $dateArr = array();
    while ($row = $result->fetch_assoc()) {
        $dateArr[] = $row['date'];
    }

    // Then get an array of dates when there exist statistic information

    $select = "SELECT DISTINCT date FROM StatDataTable ORDER BY date";
    $result = sqlSelectPrep($select, "", array());

    $statDateArr = array();
    while ($row = $result->fetch_assoc()) {
        $statDateArr[] = $row['date'];
    }

    $answerArr['integrations'] = $dateArr;
    $answerArr['statistics'] = $statDateArr;

    //var_dump($answerArr);

    return $answerArr;
}

function getComponentArray()
{
    // The result is ordered after prescendence i TakPlattform table to get better quality descriptions
    $select = "
         SELECT DISTINCT 
          sc.id, 
          sc.description, 
          sc.value,
          ms.synonym
         FROM TakServiceComponent sc LEFT JOIN MetaSynonym ms
            ON ms.originalIdentifier = sc.value
         ORDER BY id
         ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['id'],
            "description" => trim($row['description']),
            "hsaId" => trim($row['value'])
        );

        if ($row['synonym']) {
            $recordArr["synonym"] = $row['synonym'];
        }

        $resultArr[] = $recordArr;
    }

    return $resultArr;
}

function getLogicalAddressArray()
{

    $select = "
      SELECT DISTINCT 
        la.id, 
        la.description, 
        la.value
      FROM 
        TakLogicalAddress la
      ORDER BY id 
       ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['id'],
            "description" => trim($row['description']),
            "logicalAddress" => trim($row['value'])
        );
        $resultArr[] = $recordArr;
    }

    return $resultArr;
}

function getServiceContractArray()
{

    /*
    $select = "
    SELECT DISTINCT 
      sc.id, 
      sc.contractName AS description,
      sc.serviceDomainId, 
      sc.namespace, 
      sc.major 
    FROM 
      TakServiceContract sc 
    ORDER BY id  
    ";
    */

    $select = "
    SELECT DISTINCT
      sc.id,
      sc.contractName AS description,
      sc.serviceDomainId,
      sc.namespace,
      sc.major,
      ms.synonym
    FROM
      TakServiceContract sc LEFT JOIN MetaSynonym ms
        ON ms.originalIdentifier = sc.namespace
    ORDER BY sc.id
    ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['id'],
            "name" => trim($row['description']),
            "serviceDomainId" => $row['serviceDomainId'],
            "namespace" => trim($row['namespace']),
            "major" => $row['major']
        );

        if ($row['synonym']) {
            $recordArr["synonym"] = $row['synonym'];
        }
        $resultArr[] = $recordArr;
    }

    return $resultArr;
}

function getServiceDomainArray()
{

    $select = "
    SELECT DISTINCT 
      domain.id, 
      domain.domainName
    FROM
      TakServiceDomain domain    
    ORDER BY id  
      ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['id'],
            "domainName" => trim($row['domainName'])
        );
        $resultArr[] = $recordArr;
    }

    return $resultArr;
}

function getPlattformChainArray()
{

    $select = "
        SELECT DISTINCT
            firstPlattformId, 
            middlePlattformId,
            lastPlattformId 
        FROM
            TakIntegration
        ORDER BY 1, 2, 3
    ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    $idCounter = 0;
    while ($row = $result->fetch_array()) {
        $recordArr = array(
            "id" => $idCounter,
            "plattforms" => [$row[0], $row[1], $row[2]]
        );
        $resultArr[] = $recordArr;
        $idCounter++;

        //$resultArr[] = [$row[0], $row[1], $row[2]] ;
    }

    return $resultArr;
}


function getMaxCountersArray($firstDate, $lastDate)
{

    $select = "
      SELECT
        COUNT(DISTINCT consumerId) AS consumers,
        COUNT(DISTINCT contractId) AS contracts,
        COUNT(DISTINCT domainId) AS domains,
        COUNT(DISTINCT CONCAT_WS(firstPlattformId, '-', middlePlattformId, '-', lastPlattformId)) AS plattformChains,
        COUNT(DISTINCT logicalAddressId) AS logicalAddress,
        COUNT(DISTINCT producerid) AS producers
      FROM TakIntegration
      WHERE
            dateEffective <= ?
        AND dateEnd >= ?
    ";

    $result = sqlSelectPrep($select, "ss", array($lastDate, $firstDate));

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "consumers" => $row['consumers'],
            "contracts" => $row['contracts'],
            "domains" => $row['domains'],
            "plattformChains" => $row['plattformChains'],
            "logicalAddress" => $row['logicalAddress'],
            "producers" => $row['producers']
        );
        //$resultArr[] = $recordArr;
    }

    return $recordArr;
    //return $resultArr;
}

/*
 * getCurrentItemsArray()
 * This function mimics much of the getIntegrationsArray() function, but returns the data in a different dimension
 * It collects arrays of all items part of the filtered integrations defined by the $queryArr.
 * It handles updateDates(), but not statistics and history
 * For hippo, this function will be very much faster than getIntegrationsArray(). And, the answer set is much smaller.
 */
/*
 * Let us hide this function for the time being. It is faster that loadIntegrations() for hippo, but
 * due to the hippo cache this speedup is not really needed. Do not want to support to versions.

function getCurrentItemsArray($queryArr)
{

    $today = getLastDate();

    list($dateEffective,
        $dateEnd,
        $whereClauseIntegrations,
        $typeStringIntegrations,
        $paramArrayIntegrations,
        $include,
        $whereClauseIntegrationsWithoutDates,
        $paramArrayIntegrationsWithoutDates,
        $typeStringIntegrationsWithoutDates) = mkWhereClauseFromParams($queryArr);

    $keyArr = array();
    $keyArr[0] = ['firstPlattformId', 'firstPlattforms'];
    $keyArr[1] = ['middlePlattformId', 'middlePlattforms'];
    $keyArr[2] = ['lastPlattformId', 'lastPlattforms'];
    $keyArr[3] = ['logicalAddressId', 'logicalAddress'];
    $keyArr[4] = ['contractId', 'contracts'];
    $keyArr[5] = ['domainId', 'domains'];
    $keyArr[6] = ['consumerId', 'consumers'];
    $keyArr[7] = ['producerId', 'producers'];

    for ($i = 0; $i < 8; $i++) {
        $selectCI = "
        SELECT DISTINCT " . $keyArr[$i][0] .
            " FROM TakIntegration  
          " . $whereClauseIntegrations .
            " ORDER BY " . $keyArr[$i][0];

        $result = sqlSelectPrep($selectCI, $typeStringIntegrations, $paramArrayIntegrations);

        $resultArr = array();

        while ($row = $result->fetch_array()) {
            $value = $row[0];
            $resultArr[] = $value;
        }

        $itemsArr[$keyArr[$i][1]] = $resultArr;

    }

    $selectCI = "
        SELECT DISTINCT 
            firstPlattformId, 
            middlePlattformId,
            lastPlattformId
        FROM TakIntegration " .
        $whereClauseIntegrations . "
        ORDER BY firstPlattformId, middlePlattformId, lastPlattformId";

    $result = sqlSelectPrep($selectCI, $typeStringIntegrations, $paramArrayIntegrations);

    $resultArr = array();

    while ($row = $result->fetch_array()) {
        $resultArr[] = [$row[0], $row[1], $row[2]] ;
    }

    $itemsArr['plattformChains'] = $resultArr;

    $answerArr['currentItems'] = $itemsArr;
    $answerArr['maxCounters'] = getMaxCountersArray($dateEffective, $dateEnd);
    $answerArr['updateDates'] = getUpdatedDatesList($today, $whereClauseIntegrationsWithoutDates, $typeStringIntegrationsWithoutDates, $paramArrayIntegrationsWithoutDates);

    return $answerArr;
}
*/

function getIntegrationArray($queryArr)
{

    $today = getLastDate();

    list($dateEffective,
        $dateEnd,
        $whereClauseIntegrations,
        $typeStringIntegrations,
        $paramArrayIntegrations,
        $include,
        $whereClauseIntegrationsWithoutDates,
        $paramArrayIntegrationsWithoutDates,
        $typeStringIntegrationsWithoutDates) = mkWhereClauseFromParams($queryArr);

    $selectIntegrations1 = "
    SELECT DISTINCT
        id";

    $selectIntegrations2 = "
        , firstPlattformId,
        middlePlattformId,
        lastPlattformId,
        logicalAddressId,
        contractId,
        domainId,
        consumerId,
        producerId";

    $selectIntegrations3 = " FROM TakIntegration ";

    $selectIntegrations = $selectIntegrations1 . $selectIntegrations2 . $selectIntegrations3 . $whereClauseIntegrations;

    // Before we execute the select of the integrations we fetch the statistics if specified
    // We reuse this SELECT statement at a subquery for the statistics
    $statArray = array();

    if (strpos($include, 'statistics') !== false) {
        error_log("Will call stat func");
        $statArray = getStatistics(
            $dateEffective, // Need for the statistics part
            $dateEnd,       // Need for the statistics part
            $selectIntegrations1,        // The select and params which should be used as a subquerey
            $selectIntegrations3,
            $whereClauseIntegrations,
            $typeStringIntegrations,
            $paramArrayIntegrations
        );
    }

    //echo "selectIntegrations = " . $selectIntegrations . "\n";

    $result = sqlSelectPrep($selectIntegrations, $typeStringIntegrations, $paramArrayIntegrations);

    $resultArr = array();

    while ($row = $result->fetch_assoc()) {

        $integrationId = $row['id'];

        $recordArr = array(
            $integrationId,             // 0
            $row["firstPlattformId"],   // 1
            $row["middlePlattformId"],  // 2
            $row["lastPlattformId"],    // 3
            $row["logicalAddressId"],   // 4
            $row["contractId"],         // 5
            $row["domainId"],           // 6
            $row["consumerId"],         // 7
            $row["producerId"]          // 8

        );

        if (array_key_exists($integrationId, $statArray)) {
            $numberOfCalls = $statArray[$integrationId]["numberOfCalls"];
            $averageResponseTime = $statArray[$integrationId]["averageResponseTime"];

            if ($numberOfCalls) {
                //$recordArr["numberOfCalls"] = $numberOfCalls;
                $recordArr[9] = $numberOfCalls;         // 9
            }

            if ($averageResponseTime) {
                //$recordArr["averageResponseTime"] = $averageResponseTime;
                $recordArr[10] = $averageResponseTime;   // 10
            }

        }
        $resultArr[] = $recordArr;

    }

    $answerArr['integrations'] = $resultArr;

    if (strpos($include, 'history') !== false) {
        $answerArr['history'] = getHistoryArray($queryArr);
    }

    $answerArr['maxCounters'] = getMaxCountersArray($dateEffective, $dateEnd);
    $answerArr['updateDates'] = getUpdatedDatesList($today, $whereClauseIntegrationsWithoutDates, $typeStringIntegrationsWithoutDates, $paramArrayIntegrationsWithoutDates);

    return $answerArr;
}

function getStatPlattformArray()
{
    $select = "
    SELECT DISTINCT
      sdt.plattformId AS PlattformId,
      tp.name AS TpName,
      tp.environment AS TpEnvironment
    FROM
      TakPlattform tp,
      StatDataTable sdt
    WHERE
     sdt.plattformId = tp.id    
    ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['PlattformId'],
            "platform" => $row['TpName'],
            "environment" => $row['TpEnvironment']
        );
        $resultArr[] = $recordArr;
    }

    return $resultArr;
}

// The new statistics function to fetch data from StatDataTable
function getStatisticsArray($queryArr)
{

    $paramsArr = mkWhereClauseFromParamsForStatistics($queryArr);
    if ($paramsArr == null) {
        return null;
    }
    $dateEffective = $paramsArr[0];
    $dateEnd = $paramsArr[1];
    $whereClauseIntegrations = $paramsArr[2];
    $typeStringIntegrations = $paramsArr[3];
    $paramArrayIntegrations = $paramsArr[4];

    /*
    if (! isset($queryArr['dateEffective'])) {
        echo "*** Error, mandatory parameter dateEffective not specified!";
        return null;
    }
    if (! isset($queryArr['dateEnd'])) {
        echo "*** Error, mandatory parameter dateEnd not specified!";
        return null;
    }

    // The call might refer to lastPlattformId instead of plattformId
    if (isset($queryArr['lastPlattformId'])) {
        $queryArr['plattform=Id'] = $queryArr['lastPlattformId'];
        unset($queryArr['lastPlattformId']);
    }

    // Build a WHERE clause based on the filter parameters
    $typeStringIntegrations = '';
    $paramArrayIntegrations = array();
    $whereClauseIntegrations = 'WHERE TRUE '; // This way we know there can always be a WHERE clause


    $legalParams = array(
        'lastPlattformId',
        'contractId',
        'domainId',
        'logicalAddressId',
        'consumerId',
        'producerId'
    );

    foreach ($legalParams as $param) {
        if (isset($queryArr[$param])) {
            $idString = $queryArr[$param];
            list($whereClauseDelta, $typeStringDelta, $idArrDelta) = mkWhereClause($param, $idString);

            foreach ($idArrDelta as $idValue) {
                $paramArrayIntegrations[] = $idValue;
            }
            $typeStringIntegrations .= $typeStringDelta;

            if (strlen($whereClauseIntegrations) > 7) {
                $whereClauseIntegrations .= ' AND ';
            }
            $whereClauseIntegrations .= $whereClauseDelta;
        }
    }

    // Now we add the two date parameters
    $dateEffective = null;
    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    $whereClauseIntegrations .= ' date >= ?';
    $dateEffective = $queryArr['dateEffective'];
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEffective;

    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    $whereClauseIntegrations .= 'date <= ?';
    $dateEnd = $queryArr['dateEnd'];
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEnd;
    */

// todo: Will need to change calc of average. Need to sum respons time over all calls and then divide with sum of num calls
    $select = "
SELECT
    plattformId,
    consumerId,
    logicalAddressId,
    contractId,
    cont.serviceDomainId AS domainId,  
    producerId,
    SUM(calls) AS numberOfCalls,
    AVG(averageResponseTime) DIV 1 AS averageResponseTime
FROM
    StatDataTable sdt,
    TakServiceContract cont
 " . $whereClauseIntegrations . "
    AND sdt.contractId = cont.id 
    AND sdt.producerId IS NOT NULL
GROUP BY
    plattformId,
    consumerId,
    logicalAddressId,
    contractId,
    producerId
    ";

    //$selectStat = $selectStat1 . $selectIntegrations1 . $selectIntegrations3 . $whereClauseIntegrations . $selectStat2;

    $result = sqlSelectPrep(
        $select,
        $typeStringIntegrations,
        $paramArrayIntegrations
    );

    $resultArr = array();

    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            $row["plattformId"],   // 0
            $row["logicalAddressId"],   // 1
            $row["contractId"],         // 2
            $row["domainId"],           // 3
            $row["consumerId"],         // 4
            $row["producerId"],         // 5
            (int)$row["numberOfCalls"], // 6
            (int)$row["averageResponseTime"] // 7
        );
        $resultArr[] = $recordArr;
    }
    return $resultArr;
}

// The history array it used to show the "Visa utveckling över tid" in statistics
function getHistoryArrayV2($queryArr)
{

    $paramsArr = mkWhereClauseFromParamsForStatistics($queryArr);
    if ($paramsArr == null) {
        return null;
    }
    $dateEffective = $paramsArr[0];
    $dateEnd = $paramsArr[1];
    $whereClauseIntegrations = $paramsArr[2];
    $typeStringIntegrations = $paramsArr[3];
    $paramArrayIntegrations = $paramsArr[4];

    $select= "
        SELECT
          date,
          SUM(calls) AS numberOfCalls,
          AVG(averageResponseTime) DIV 1 AS averageResponseTime
    FROM
        StatDataTable sdt,
        TakServiceContract cont
        " . $whereClauseIntegrations . "
        AND sdt.contractId = cont.id 
        AND sdt.producerId IS NOT NULL
      GROUP BY date
      ";

    $result = sqlSelectPrep(
        $select,
        $typeStringIntegrations,
        $paramArrayIntegrations
    );

    //$numRows = $result->num_rows;

    //$resultArr = array();

    while ($row = $result->fetch_assoc()) {
        $resultArr[$row["date"]] = (int)$row["numberOfCalls"];
    }

    $answerArr['history'] = $resultArr;
    return $answerArr;
}


function getStatistics($dateEffective,
                       $dateEnd,
                       $selectIntegrations1,
                       $selectIntegrations3,
                       $whereClauseIntegrations,
                       $typeStringIntegrations,
                       $paramArrayIntegrations)
{

// todo: Will need to change calc of average. Need to sum respons time over all calls and then divide with sum of num calls
    $selectStat = $selectIntegrations1 . $selectIntegrations3 . $whereClauseIntegrations;

    $result = sqlSelectPrep(
        $selectStat,
        "ss" . $typeStringIntegrations,
        array_merge(array($dateEffective, $dateEnd), $paramArrayIntegrations)
    );

    $resultArr = array();

    while ($row = $result->fetch_assoc()) {
        $integrationId = $row['integrationId'];
        $recordArr = array(
            "numberOfCalls" => (int)$row["numberOfCalls"],
            "averageResponseTime" => (int)$row["averageResponseTime"]
        );
        $resultArr[$integrationId] = $recordArr;
    }
    return $resultArr;
}

function getUpdatedDatesList($today, $whereClauseIntegrationsWithoutDates, $typeStringIntegrationsWithoutDates, $paramArrayIntegrationsWithoutDates)
{
    $dateSelect = "
        SELECT DISTINCT
            dateEffective AS dateEffective,
            dateEnd AS dateEnd
        FROM TakIntegration   
        ";

    $dateSelect .= $whereClauseIntegrationsWithoutDates;

    //echo $dateSelect . "\n";

    $result = sqlSelectPrep($dateSelect, $typeStringIntegrationsWithoutDates, $paramArrayIntegrationsWithoutDates);

    $updateDates = array();
    while ($row = $result->fetch_assoc()) {
        //echo $row["dateEffective"] . " " . $row["dateEnd"] . "\n";
        array_push($updateDates, $row["dateEffective"]);

        // If not today; add 1 to dateEnd to specify day when difference can be seen
        $dateEnd = $row["dateEnd"];
        if ($dateEnd !== $today) {
            array_push($updateDates, incDate($dateEnd));                // 10
        }
    }

    array_push($updateDates, $today);                // We also need today (always)

    $updateDates = array_unique($updateDates);
    rsort($updateDates);

    return $updateDates;
}

// The history array it used to show the "Visa utveckling över tid" in statistics
function getHistoryArray($queryArr)
{

    list($dateEffective,
        $dateEnd,
        $whereClauseIntegrations,
        $typeStringIntegrations,
        $paramArrayIntegrations,
        $include,
        $dummy1,
        $dummy2,
        $dummy3) = mkWhereClauseFromParams($queryArr);

    $selectStat = $whereClauseIntegrations;

    $result = sqlSelectPrep(
        $selectStat,
        "ss" . $typeStringIntegrations,
        array_merge(array($dateEffective, $dateEnd), $paramArrayIntegrations)
    );
    //$numRows = $result->num_rows;

    //$resultArr = array();

    while ($row = $result->fetch_assoc()) {
        $resultArr[$row["day"]] = (int)$row["numberOfCalls"];
    }

    return $resultArr;
}


function mkWhereClauseFromParams($queryArr)
{
    $today = getLastDate();

    $include = '';
    if (isset($queryArr['include'])) {
        $include = $queryArr['include'];
    }

    // Build a WHERE clause based on the filter parameters
    $typeStringIntegrations = '';
    $paramArrayIntegrations = array();
    $whereClauseIntegrations = 'WHERE TRUE '; // This way we know there can always be a WHERE clause

    $legalParams = array(
        'firstPlattformId',
        'middlePlattformId',
        'lastPlattformId',
        'contractId',
        'domainId',
        'logicalAddressId',
        'consumerId',
        'producerId'
    );

    foreach ($legalParams as $param) {
        if (isset($queryArr[$param])) {
            $idString = $queryArr[$param];
            list($whereClauseDelta, $typeStringDelta, $idArrDelta) = mkWhereClause($param, $idString);

            foreach ($idArrDelta as $idValue) {
                $paramArrayIntegrations[] = $idValue;
            }
            $typeStringIntegrations .= $typeStringDelta;

            if (strlen($whereClauseIntegrations) > 7) {
                $whereClauseIntegrations .= ' AND ';
            }
            $whereClauseIntegrations .= $whereClauseDelta;
        }
    }

    // These three values will be used to select the list of dates when the filter have changed
    $whereClauseIntegrationsWithoutDates = $whereClauseIntegrations;
    $paramArrayIntegrationsWithoutDates = $paramArrayIntegrations;
    $typeStringIntegrationsWithoutDates = $typeStringIntegrations;

    // Now we add the two date parameters
    $dateEffective = null;
    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    $whereClauseIntegrations .= ' dateEffective <= ?';
    if (isset($queryArr['dateEffective'])) {
        $dateEffective = $queryArr['dateEffective'];
    } else {
        $dateEffective = $today;
    }
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEffective;
    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    //$whereClauseIntegrations .= 'dateEffective <= ?';
    $whereClauseIntegrations .= 'dateEnd >= ?';

    if (isset($queryArr['dateEnd'])) {
        $dateEnd = $queryArr['dateEnd'];
    } else {
        // Default for the dateEnd parameters is the last day the TAK is updated
        $dateEnd = $today;
    }

    // Add clause for the two dateEnd which are always included
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEnd;

    return array($dateEffective, $dateEnd, $whereClauseIntegrations, $typeStringIntegrations, $paramArrayIntegrations, $include,
        $whereClauseIntegrationsWithoutDates, $paramArrayIntegrationsWithoutDates, $typeStringIntegrationsWithoutDates);
}

function mkWhereClauseFromParamsForStatistics($queryArr) {

    if (! isset($queryArr['dateEffective'])) {
        echo "*** Error, mandatory parameter dateEffective not specified!";
        return null;
    }
    if (! isset($queryArr['dateEnd'])) {
        echo "*** Error, mandatory parameter dateEnd not specified!";
        return null;
    }

    // The call might refer to lastPlattformId instead of plattformId
    if (isset($queryArr['lastPlattformId'])) {
        $queryArr['plattformId'] = $queryArr['lastPlattformId'];
        unset($queryArr['lastPlattformId']);
    }

    // Build a WHERE clause based on the filter parameters
    $typeStringIntegrations = '';
    $paramArrayIntegrations = array();
    $whereClauseIntegrations = 'WHERE TRUE '; // This way we know there can always be a WHERE clause

    $legalParams = array(
        'plattformId',
        'contractId',
        'domainId',
        'logicalAddressId',
        'consumerId',
        'producerId'
    );

    foreach ($legalParams as $param) {
        if (isset($queryArr[$param])) {
            $idString = $queryArr[$param];
            list($whereClauseDelta, $typeStringDelta, $idArrDelta) = mkWhereClause($param, $idString);

            foreach ($idArrDelta as $idValue) {
                $paramArrayIntegrations[] = $idValue;
            }
            $typeStringIntegrations .= $typeStringDelta;

            if (strlen($whereClauseIntegrations) > 7) {
                $whereClauseIntegrations .= ' AND ';
            }
            $whereClauseIntegrations .= $whereClauseDelta;
        }
    }

    // Now we add the two date parameters
    $dateEffective = null;
    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    $whereClauseIntegrations .= ' date >= ?';
    $dateEffective = $queryArr['dateEffective'];
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEffective;

    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    $whereClauseIntegrations .= 'date <= ?';
    $dateEnd = $queryArr['dateEnd'];
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEnd;

    return array($dateEffective, $dateEnd, $whereClauseIntegrations, $typeStringIntegrations, $paramArrayIntegrations);
}

function incDate($inDate)
{
    $date = date_create($inDate);
    date_add($date, date_interval_create_from_date_string('1 days'));
    return date_format($date, 'Y-m-d');
}

?>