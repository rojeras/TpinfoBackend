<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 3/8/16
 * Time: 8:14 PM
 * Cache the TAKAPI output to local files
 *
 * This program fetch and create cache files of the TAK-api.
 * The API is normally updated on a daily basis. A file is created per tak per day which store the output of the secen apu calls.
 * Then an index file is created, which contain information about the cache files,
 *
 * Name standard:
 *   takapi_NTJP-QA_2016-03-09.json (one per day per tak)
 *   takapicache_content.json
 *   tak
 *
 * Every time this pgm is run it feteches the connectionPoints, Then it compare the time stamps with the time stamps stored in
 * the takapicache file. If the takapi has been updated all api calls are executed and new cache files are created.
 */


// todo: Use prep stmts. Check if those can be saved between calls

//error_reporting(E_ALL ^ E_WARNING);
//error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL);

require 'leolib_sql.php';
require 'leolib_debug.php';

$serverName = $_SERVER['SERVER_NAME'];

/* TARGET::NOGUI */ $iniFile = "/home/leoroj/ini/statdb52.ini";
/* TARGET::AWS */ $iniFile = "../lib/statdb52.ini";
/* TARGET::LOCAL */ $iniFile = "../lib/statdb52.ini";

$ini_array = parse_ini_file($iniFile, true);

$environment = '[[DATABASE]]'; // Will be substituted by build.py
/* TARGET::REMOVE_DURING_BUILD */ $environment = 'DB-LOCAL';

// !!! TEMPTEMPTEMP
/// $environment = 'DB-AWS';
// TEMPTEMPTEMP

$ini_values = $ini_array[$environment];

$INI_dbserver = $ini_values['dbserver'];
$INI_dbuser = $ini_values['dbuserro'];
$INI_dbpassword = $ini_values['dbpasswordro'];
$INI_dbname = $ini_values['dbname'];

header('Access-Control-Allow-Origin: *');
//header('Access-Control-Allow-Methods: GET');

$DBCONN = sqlConnect($INI_dbserver, $INI_dbuser, $INI_dbpassword, $INI_dbname);

$command = "";
$scriptName = basename(__FILE__, 'takapidb.php');

$uriPrefix = '/newversion/statdb/statdb.php/api/v1/';
$requestURI = $_SERVER['REQUEST_URI'];

if (isset($_SERVER['QUERY_STRING'])) {
    $queryString = $_SERVER['QUERY_STRING'];
} else {
    $queryString = '';
}

$docRoot = $_SERVER['DOCUMENT_ROOT'];

$TYPE = str_replace($uriPrefix, "", $requestURI);
$TYPE = str_replace('?' . $queryString, "", $TYPE);
define('TYPE', $TYPE);

// Parse the input params
parse_str($queryString, $queryArr);

//$includeParam = $queryArr['include'];

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
            "domains"   => $row['domains'],
            "plattformChains" => $row['plattformChains'],
            "logicalAddress" => $row['logicalAddress'],
            "producers" => $row['producers']
        );
        //$resultArr[] = $recordArr;
    }

    return $recordArr;
    //return $resultArr;
}

//function getIntegrationArray($firstPlattformId, $middlePlattformId, $lastPlattformId, $dateEffective, $dateEnd, $consumerId, $producerId, $contractId, $domainId, $laId) {
function getIntegrationArray($queryArr)
{

    list($dateEffective,
        $dateEnd,
        $whereClauseIntegrations,
        $typeStringIntegrations,
        $paramArrayIntegrations,
        $include) = mkWhereClauseFromParams($queryArr);

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

    return $answerArr;
}

function getStatPlattformArray()
{

    $select = "
    SELECT DISTINCT
      ti.lastPlattformId,
      tp.name,
      tp.environment
    FROM
      TakIntegration ti,
      TakPlattform tp,
      StatData sd
    WHERE
          sd.integrationId = ti.id
      AND ti.lastPlattformId = tp.id
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
        AND integrationId IN ( 
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

function getHistoryArray($queryArr)
{

    list($dateEffective,
        $dateEnd,
        $whereClauseIntegrations,
        $typeStringIntegrations,
        $paramArrayIntegrations,
        $include) = mkWhereClauseFromParams($queryArr);

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
          AND integrationId IN ( 
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
    $include = '';
    if (isset($queryArr['include'])) {
        $include = $queryArr['include'];
    }

    // Build a WHERE clause based on the filter parameters
    $typeStringIntegrations = '';
    $paramArrayIntegrations = array();
    $whereClauseIntegrations = 'WHERE '; // We know there will always be a WHERE clause, the dates are mandatory (with default values)

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

    $dateEffective = null;
    if (isset($queryArr['dateEffective'])) {
        $dateEffective = $queryArr['dateEffective'];
        $typeStringIntegrations .= 's';
        $paramArrayIntegrations[] = $dateEffective;
        if (strlen($whereClauseIntegrations) > 7) {
            $whereClauseIntegrations .= ' AND ';
        }
        //$whereClauseIntegrations .= 'dateEffective <= ?';
        $whereClauseIntegrations .= 'dateEnd >= ?';
    }
    if (isset($queryArr['dateEnd'])) {
        $dateEnd = $queryArr['dateEnd'];
    } else {
        // Default for the dateEnd parameters is the last day the TAK is updated
        $dateArr = getDateArray();
        $dateEnd = $dateArr['integrations'][0];
    }

    // Add clause for the two dateEnd which are always included
    $typeStringIntegrations .= 's';
    $paramArrayIntegrations[] = $dateEnd;
    if (strlen($whereClauseIntegrations) > 7) {
        $whereClauseIntegrations .= ' AND ';
    }
    //$whereClauseIntegrations .= ' dateEnd >= ?';
    $whereClauseIntegrations .= ' dateEffective <= ?';

    return array($dateEffective, $dateEnd, $whereClauseIntegrations, $typeStringIntegrations, $paramArrayIntegrations, $include);
}

?>