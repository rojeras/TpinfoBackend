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
//error_reporting(E_ALL ^ E_WARNING);
//error_reporting(E_ALL ^ E_NOTICE);
// A small edit to trigger rebuild again
error_reporting(E_ALL);
ini_set('memory_limit', '256M');

require 'leolib_sql.php';
require_once 'leolib.php';

$serverName = $_SERVER['SERVER_NAME'];

$DBCONN = sqlConnectEnvs();

header('Access-Control-Allow-Origin: *');
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

// Parse the input params
parse_str($queryString, $queryArr);

// Definitions of the API
switch ($TYPE) {
    case "plattforms":
        $resultArr = getConnectionPointArray();
        echo json_encode($resultArr);
        break;

    case "dates":
        $resultArr = getDateArray();

        $answer = array(
            "dates" => $resultArr,
        );
        echo json_encode($answer);
        break;

    case "components":
        $resultArr = getComponentArray();
        echo json_encode($resultArr);
        break;

    case "contracts":
        $resultArr = getServiceContractArray();
        echo json_encode($resultArr);
        break;
    case "domains":
        $resultArr = getServiceDomainArray();
        echo json_encode($resultArr);
        break;

    case "logicalAddress":
        $resultArr = getLogicalAddressArray();
        echo json_encode($resultArr);
        break;

    case "plattformChains":
        $resultArr = getPlattformChainArray();
        echo json_encode($resultArr);
        break;

    case "counters":
        $resultArr = getMaxCountersArray('2018-05-27', '2018-05-29');
        echo json_encode($resultArr);
        break;

    // Returns information about which plattforms have statistics data
    case "statPlattforms":
        $answerArr = getStatPlattformArray();
        echo json_encode($answerArr);
        break;

    case "integrations":
        $answerArr = getIntegrationArray($queryArr);
        echo json_encode($answerArr);
        break;

    case "currentItems":
        $answerArr = getCurrentItemsArray($queryArr);
        echo json_encode($answerArr);
        break;

    case "version":
        $resultArr = getVersion();
        echo json_encode($resultArr);
        break;

    case "ping":
        echo "PING ANSWER...";
        break;
    default:
        die($scriptName . ": *** ERROR - unknown item: " . $TYPE);
}

//outputJsonFromDB($result);

$DBCONN->close();

return;

// End of main program

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

    $select = "SELECT DISTINCT day FROM StatData ORDER BY day";
    $result = sqlSelectPrep($select, "", array());

    $statDateArr = array();
    while ($row = $result->fetch_assoc()) {
        $statDateArr[] = $row['day'];
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
            concat(firstPlattformId, '-', lastPlattformId) AS id
        FROM
            TakIntegration
        WHERE
            firstPlattformId <> lastPlattformId
                        
        UNION
        SELECT  id
            FROM TakPlattform
            
        ORDER BY id   
    ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['id']
        );
        $resultArr[] = $recordArr;
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
      ti . lastPlattformId,
      tp . name,
      tp . environment
    FROM
      TakIntegration ti,
      TakPlattform tp,
      StatData sd
    WHERE
          sd . integrationId = ti . id
          AND ti . lastPlattformId = tp . id
    ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "id" => $row['lastPlattformId'],
            "platform" => $row['name'],
            "environment" => $row['environment']
        );
        $resultArr[] = $recordArr;
    }

    return $resultArr;
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
    $selectStat1 = "
      SELECT
        integrationId, 
        SUM(calls) AS numberOfCalls,
        AVG(averageResponseTime) DIV 1 AS averageResponseTime
      FROM
        StatData
      WHERE
            day >= ?
        AND day <= ?
        AND integrationId IN(
    ";

    $selectStat2 = "
)
      GROUP BY integrationId
      ";

    $selectStat = $selectStat1 . $selectIntegrations1 . $selectIntegrations3 . $whereClauseIntegrations . $selectStat2;

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


function getVersion()
{

    $select = "
         SELECT  
          version, 
          deployDate
         FROM MetaVersion
         ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    while ($row = $result->fetch_assoc()) {
        $recordArr = array(
            "version" => $row['version'],
            "deployDate" => $row['deployDate']
        );
        $resultArr[] = $recordArr;
    }

    return $resultArr;
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

    $selectStat1 = "
        SELECT
          day,
          SUM(calls) AS numberOfCalls,
          AVG(averageResponseTime) DIV 1 AS averageResponseTime
        FROM
          StatData
        WHERE
          day >= ?
          AND day <= ?
          AND integrationId IN(
    SELECT id
              FROM TakIntegration  
          ";

    $selectStat2 = "
        )
      GROUP BY day
      ";

    $selectStat = $selectStat1 . $whereClauseIntegrations . $selectStat2;

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

    //$whereClauseIntegrations .= ' dateEnd >= ?';

    /*
    echo "queryArr = \n";
    var_dump($queryArr);
    echo "dateEffective = " . $dateEffective . "\n";
    echo "dateEnd = " . $dateEnd . "\n";
    echo "whereClauseIntegrations = " . $whereClauseIntegrations . "\n";
    echo "whereClauseIntegrationsWithoutDate = " . $whereClauseIntegrationsWithoutDates . "\n";
    echo "paramArrayIntegrations = \n";
    var_dump($paramArrayIntegrations);
    */

    return array($dateEffective, $dateEnd, $whereClauseIntegrations, $typeStringIntegrations, $paramArrayIntegrations, $include,
        $whereClauseIntegrationsWithoutDates, $paramArrayIntegrationsWithoutDates, $typeStringIntegrationsWithoutDates);
}

function incDate($inDate)
{
    $date = date_create($inDate);
    date_add($date, date_interval_create_from_date_string('1 days'));
    return date_format($date, 'Y-m-d');
}

?>