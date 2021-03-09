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
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 2000);

// Report all errors
error_reporting(E_ALL);
// done: Add transactions and commit/rollback for each TAK per day
// todo: Create a mechanism where the program notifies if it encounters problems (mail?)

// This pgm update the tpdb cf today
// Intended to be run by cron

//require $_SERVER['DOCUMENT_ROOT'].'/scripts/leolib_sql.php';

require 'leolib_sql.php';
require_once 'leolib.php';

$TAKAPI_URL = 'http://api.ntjp.se/coop/api/v1/';

$STATFILESPATH = leoGetenv('STATFILESPATH');

echo "STATAPIROOT: " . $STATFILESPATH . "\n";

$SYNONYMFILE = 'MetaSynonym.csv';
define('SYNONYMFILE', $SYNONYMFILE);

// Get a connection to the DB
$DBCONN = sqlConnectEnvs();

echo "Start! \n";

// echo("Check if DB updated\n");
// upgrade_62_to_64();

echo("Load TAK data\n");
// emptyDatabase("ALL"); !!!
loadTakData();

// emptyDatabase("StatData");
loadStatistics($STATFILESPATH);  //!!!!

$DBCONN->close();

echo("Clean cache\n");
cleanCache();

echo 'Klart!';
echo '';

return; // End of main program


function loadTakData()
{
    global $DBCONN;

    $apiConnectionPointsJSON = getConnectionPoints();
    echo $apiConnectionPointsJSON;
    echo array_reverse($apiConnectionPointsJSON);
    exit(1);

    for ($i = 0; $i < count($apiConnectionPointsJSON); $i++) {

        $item = $apiConnectionPointsJSON[$i];
        $connectionPoint = $item['id'];

        $plattform = $item['platform'];
        $environment = $item['environment'];
        $timeStamp = substr($item['snapshotTime'], 0, 10);

        //echo "Found: " . $plattform . " " . $environment . " : " . $timeStamp . "\n";

        /*
        if ($timeStamp > '2018-02-06') { // !!!!
            continue;
        }
        */

        $lastSnapshotTimeInDb = getLastSnapshotTimeInDb($plattform, $environment);

        // if ($timeStamp <= $lastSnapshotTimeInDb) { // +++++
        if ($timeStamp < $lastSnapshotTimeInDb) { // Do re-run with todays data, but not with older. Make it possible to have multiple daily runs.
            continue;
        }

        // For test purposes
        /*
        if ($plattform == "NTJP"
            AND $environment == "PROD"
            //OR $timeStamp < '2016-12-30'
            //OR $timeStamp > '2017-01-10'
        ) {
            continue;
        }
        */

        // Exclude first days of 2017 due to an error in the naming of the files
        if ('2017-01-01' <= $timeStamp and $timeStamp <= '2017-01-08') {
            continue;
        }

        echo "-------------------------------------------------------\n";

        $cooperations = getCooperations($connectionPoint);

        $productions = getProductions($connectionPoint);

        if ($cooperations and $productions) {


            $DBCONN->begin_transaction();

            $plattformId = ensurePlattform($plattform, $environment, $timeStamp);

            echo "Will update: " . $plattform . " " . $environment . " : " . $timeStamp . " (latest update made: " . $lastSnapshotTimeInDb . ")\n";

            ensureCallAuthorization($plattformId, $timeStamp, $cooperations, $lastSnapshotTimeInDb);
            ensureRouting($plattformId, $timeStamp, $productions, $lastSnapshotTimeInDb);

            $DBCONN->commit();
        } else {
            echo "*** Error reading json file(s) for connectionPoint=" . $connectionPoint . ", " . $plattform . " " . $environment . " : " . $timeStamp . "\n";
        }
    }


// Need to do an update of TakIntegration here at the end of the processing
// It is enough to do it once since it picks up the dates from the underlying routings and call authorizations.
    echo("End of days detected, will do a final takIntegration update\n");

    $currentSnapshotTimeInDb = getLastSnapshotTimeInDb('%', '%');
    $lastTimeInIntegrations = getLastSnapshotTimeInIntegrations();

    $DBCONN->begin_transaction();
    ensureIntegration($currentSnapshotTimeInDb, $lastTimeInIntegrations);
    $DBCONN->commit();
}

// todo: Add a similar function to read Ineras CSV files
function loadStatistics($statFiles)
{
    echo "Will load statistics from path: " . $statFiles . "\n";

    // We will need an array of which dates the TakRouting has been updated to calculate "dateBefore"
    // Needed when we analyze TakRouting to find the producer for a certain log record
    $routingDates = getRoutingUpdateDates();

    global $DBCONN;

    $DBCONN->begin_transaction();

    //foreach (glob($STATAPIROOT . "*.NEW.json") as $file) {
    // foreach (glob($statFiles . "/SLL-PROD_2019-06*.json") as $file) {
    foreach (glob($statFiles . "/*.*.json") as $file) {

        $fileData = file_get_contents($file);
        $fileArr = json_decode($fileData, true);

        $plattform = $fileArr['plattform'];

        if ($plattform == "SLL_QA") {
            $plattform = "SLL-QA";
        }
        if ($plattform == "SLL_PROD") {
            $plattform = "SLL-PROD";
        }

        $startDate = $fileArr["startDate"];
        $endDate = $fileArr["endDate"];
        $updateDateBefore = getUpdateDateBefore($routingDates, $startDate);
        $updateDateAfter = getUpdateDateAfter($routingDates, $startDate);

        //echo "updateBeforeDate = " . $updateDateBefore . "\n";

        $statisticsArr = $fileArr["statistics"];
        echo "Process " . $file . ", number of entries: " . count($statisticsArr) . "\n";


        for ($i = 0; $i < count($statisticsArr); $i++) {
            $item = $statisticsArr[$i];

            $firstPlattformHsaId = '%';
            $originalConsumerId = $item["originalConsumerId"];
            $consumerId = $item["consumerId"];

            // In old ELK null is used to indicate local consumer, in new ELK originalConsumer is set equal to consumer
            if ($originalConsumerId != "null" and $originalConsumerId != $consumerId) {
                $firstPlattformHsaId = $item["consumerId"];
                $consumerId = $originalConsumerId;
            }

            if (!$consumerId) {
                echo "*** Error, consumerId is null!";
            }
            // Calculate and use mean number of calls per day
            $allCalls = $item["calls"];
            $noOfDays = dateDifference($startDate, $endDate) + 1;
            $calls = intdiv($allCalls, $noOfDays);

            // There might be response time data in the files
            $meanResponsTime = null;
            if (isset($item["averageResponseTime"])) {
                $meanResponsTime = $item["averageResponseTime"];
            }

            $namespace = $item["namespace"];
            $logicalAddress = $item["logicalAddress"];

            ensureStatisticsV2($firstPlattformHsaId, $plattform, $consumerId, $calls, $meanResponsTime, $namespace, $logicalAddress, $startDate, $endDate, $updateDateBefore, $updateDateAfter);
        }
    }

    // This function currently not used
    // ensureStatisticsV2PostProcess();

    $DBCONN->commit();
}

function cleanCache()
{
    $cache_path = 'cache/';

    // Delete the base item files - must be updated each day
    array_map('unlink', glob($cache_path . "*.statPlattforms.cache"));
    array_map('unlink', glob($cache_path . "*.plattforms.cache"));
    array_map('unlink', glob($cache_path . "*.plattformChains.cache"));
    array_map('unlink', glob($cache_path . "*.logicalAddress.cache"));
    array_map('unlink', glob($cache_path . "*.domains.cache"));
    array_map('unlink', glob($cache_path . "*.dates.cache"));
    array_map('unlink', glob($cache_path . "*.contracts.cache"));
    array_map('unlink', glob($cache_path . "*.components.cache"));

    $secondsInWeek = 604800;
    $secondsIn15Weeks = $secondsInWeek * 15;
    $secondsInYear = $secondsInWeek * 52;

    // integration files are removed after a week
    cleanCacheBasedOnFileType($cache_path, "integrations", $secondsInWeek);

    // statistics files are quite small and kept 15 weeks
    cleanCacheBasedOnFileType($cache_path,"statistics", $secondsIn15Weeks);

    // history files are very small and kept a year
    cleanCacheBasedOnFileType($cache_path, "history", $secondsInYear);
}

function cleanCacheBasedOnFileType($cache_path, $fileType, $acceptedAge) {

    foreach (glob($cache_path . "*." . $fileType . ".cache") as $file) {
        $fileAge = time() - filemTime($file);
        if ($fileAge > $acceptedAge) {
            echo "Cache file will be deleted: " . $file . " age: " . $fileAge . " seconds\n";
            unlink($file);
        }
    }
}

// Ensure a certain plattform, identified by name and environment, exists in the Plattform table
function ensurePlattform($name, $environment, $timeStamp)
{

    $insert = "
        INSERT INTO TakPlattform 
        (name, environment, lastSnapshot) 
        VALUES  (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          id=LAST_INSERT_ID(id), -- This is a trick to get the correct id back when no update is done
          lastSnapshot=VALUES(lastSnapshot)
        ";

    $plattformId = sqlInsertPrep($insert, "sss", array($name, $environment, $timeStamp));

    return $plattformId;

}

function ensureCallAuthorization($plattformId, $timeStamp, $itemList, $lastSnapshotTimeInDb)
{
    $serviceConsumerCache = array();
    $logicalAddressCache = array();
    $serviceContractCache = array();

    // todo: Add a check for a valid namespace first in this function. If not valid it should not be stored. A valid ns should at least have 5 ":" (length six)
    foreach ($itemList as $item) {
        //var_dump($item);
        $consumerHsaIdArr = $item["serviceConsumer"];
        $consumerHsaId = getValue($consumerHsaIdArr, "hsaId");
        if (!array_key_exists($consumerHsaId, $serviceConsumerCache)) {
            $consumerDescription = getValue($consumerHsaIdArr, "description");
            $serviceConsumerCache[$consumerHsaId] = ensureServiceComponent($consumerHsaId, $consumerDescription);
        }
        $serviceComponentId = $serviceConsumerCache[$consumerHsaId];

        $logicalAddressArr = $item["logicalAddress"];
        $logicalAddress = getValue($logicalAddressArr, "logicalAddress");
        if (!array_key_exists($logicalAddress, $logicalAddressCache)) {
            $logicalAddressDescription = getValue($logicalAddressArr, "description");
            $logicalAddressCache[$logicalAddress] = ensureLogicalAddress($logicalAddress, $logicalAddressDescription);
        }
        $logicalAddressId = $logicalAddressCache[$logicalAddress];

        $serviceContractArr = $item["serviceContract"];
        $namespace = getValue($serviceContractArr, "namespace");
        $major = getValue($serviceContractArr, "major");
        $minor = getValue($serviceContractArr, "minor");
        $cacheKey = $namespace . '-' . $minor;
        if (!array_key_exists($cacheKey, $serviceContractCache)) {
            //$serviceContractCache[$cacheKey] = ensureServiceContract($namespace, $major);
            $serviceContract = ensureServiceContract($namespace, $major);
            if (!$serviceContract) {
                continue;
            }
            $serviceContractCache[$cacheKey] = $serviceContract;
        }
        $serviceContractId = $serviceContractCache[$cacheKey];

        $select = "
            SELECT DISTINCT id
            FROM TakCallAuthorization
            WHERE 
                  serviceContractId = ? 
              AND logicalAddressId = ? 
              AND serviceComponentId = ?
              AND plattformId = ? 
              AND dateEnd = ?
        ";

        $result = sqlSelectPrep($select, "iiiis", array($serviceContractId, $logicalAddressId, $serviceComponentId, $plattformId, $lastSnapshotTimeInDb));
        $numRows = $result->num_rows;

        switch ($numRows) {
            case 0:
                // New record, insert it
                $insert = "
              INSERT INTO TakCallAuthorization
                     (serviceContractId, 
                     logicalAddressId, 
                     serviceComponentId, 
                     plattformId,  
                     dateEffective, 
                     dateEnd 
                     ) 
              VALUES (?, ?, ?, ?, ?, ?) 
              ";
                $callAuthId = sqlInsertPrep($insert, "iiiiss", array($serviceContractId, $logicalAddressId, $serviceComponentId, $plattformId, $timeStamp, $timeStamp));
                break;
            case 1:
                $row = $result->fetch_assoc();
                $authId = $row['id'];
                // Record exist, update the dateEnd
                $update = "
            UPDATE TakCallAuthorization
              SET dateEnd = ? 
            WHERE 
                 id = ? 
             ";
                $dummy = sqlUpdatePrep($update, "si", array($timeStamp, $authId));
                break;
            default:
                // Error case, more than one record found
                echo "*** Error - found multiple overlapping TakCallAuths: ";
                while ($row = $result->fetch_assoc()) {
                    echo '"', $row['id'], '" ';
                }
                echo "\n";
                break;
        }
    }
}

function ensureRouting($plattformId, $timeStamp, $itemList, $lastSnapshotTimeInDb)
{
    $serviceProducerCache = array();
    $logicalAddressCache = array();
    $serviceContractCache = array();

    foreach ($itemList as $item) {

        $producerHsaIdArr = $item["serviceProducer"];
        $producerHsaId = getValue($producerHsaIdArr, "hsaId");

        // Patch of HSA-id where HVAL has been used to represent SLL RTP (both prod and QA)
        if ($producerHsaId == 'HVAL') {

            if ($plattformId == 5) {
                $producerHsaId = 'SE2321000016-7P35';
            } elseif ($plattformId == 2) {
                $producerHsaId = 'SE2321000016-A2G4';
            } else {
                echo " *** Error - found HVAL HSA id in plattform id=" . $plattformId . "\n";
            }
        }

        if (!array_key_exists($producerHsaId, $serviceProducerCache)) {
            $producerDescription = getValue($producerHsaIdArr, "description");
            $serviceComponentId = ensureServiceComponent($producerHsaId, $producerDescription);
            $serviceProducerCache[$producerHsaId] = $serviceComponentId;
        }
        $serviceComponentId = $serviceProducerCache[$producerHsaId];

        $logicalAddressArr = $item["logicalAddress"];
        $logicalAddress = getValue($logicalAddressArr, "logicalAddress");
        if (!array_key_exists($logicalAddress, $logicalAddressCache)) {
            $logicalAddressDescription = getValue($logicalAddressArr, "description");
            $logicalAddressId = ensureLogicalAddress($logicalAddress, $logicalAddressDescription);
            $logicalAddressCache[$logicalAddress] = $logicalAddressId;
        }
        $logicalAddressId = $logicalAddressCache[$logicalAddress];

        $serviceContractArr = $item["serviceContract"];
        $namespace = getValue($serviceContractArr, "namespace");
        $major = getValue($serviceContractArr, "major");
        $minor = getValue($serviceContractArr, "minor");
        $cacheKey = $namespace . '-' . $minor;
        if (!array_key_exists($cacheKey, $serviceContractCache)) {
            //$serviceContractCache[$cacheKey] = ensureServiceContract($namespace, $major);
            $serviceContract = ensureServiceContract($namespace, $major);
            if (!$serviceContract) {
                continue;
            }
            $serviceContractCache[$cacheKey] = $serviceContract;
        }
        $serviceContractId = $serviceContractCache[$cacheKey];

        // todo: url and rivtaprofile should also be saved in their own history tables (like minor)
        // Currently the latest values are just written to the TakRouting table
        $url = getValue($item, "physicalAddress");
        $rivtaProfile = getValue($item, "rivtaProfile");


        // if a record exist with dateEnd = lastSnapshot --> update dateEnd = timeStamp
        // else insert a new record
        $select = "
            SELECT DISTINCT id
            FROM TakRouting
            WHERE 
                  serviceContractId = ? 
              AND logicalAddressId = ? 
              AND serviceComponentId = ?
              AND plattformId = ? 
              AND dateEnd = ?
             ";

        $result = sqlSelectPrep($select, "iiiis", array($serviceContractId, $logicalAddressId, $serviceComponentId, $plattformId, $lastSnapshotTimeInDb));
        $numRows = $result->num_rows;

        switch ($numRows) {
            //echo "New routing record, id = " . $routingId . "\n";
            case 0:
                // New record, insert it
                $insert = "
              INSERT INTO TakRouting
                     (serviceContractId, 
                     logicalAddressId, 
                     serviceComponentId, 
                     plattformId,  
                     dateEffective, 
                     dateEnd 
                     ) 
              VALUES (?, ?, ?, ?, ?, ?) 
              ";

                $routingId = sqlInsertPrep($insert, "iiiiss", array($serviceContractId, $logicalAddressId, $serviceComponentId, $plattformId, $timeStamp, $timeStamp));
                break;
            case 1:
                // Existing record, just update dateEnd
                $row = $result->fetch_assoc();
                $routingId = $row['id'];

                $update = "
              UPDATE TakRouting
              SET 
                dateEnd = ?
              WHERE id = ?
            ";
                $dummy = sqlUpdatePrep($update, "si", array($timeStamp, $routingId));
                break;
            default:
                // Error case, more than one record found
                $routingId = null;
                echo "*** Error - found multiple overlapping TakRoutings: ";
                while ($row = $result->fetch_assoc()) {
                    echo '"', $row['id'], '" ';
                }
                echo "\n";
                break;
        }
        // And verify/update/insert in Routing and Url tables
        if ($routingId) {
            //ensureUrl($routingId, $url, $timeStamp, $lastSnapshotTimeInDb);
            ensureRivtaProfile($routingId, $rivtaProfile, $timeStamp, $lastSnapshotTimeInDb);
        }
    }
}

// endoreIntegration(), use the ViewIntegrationMulti and use it as a base to updated the table with integration info
function ensureIntegration($timestamp, $lastSnapshotTimeInDb)
{
    // Go through all records returned from the integration view, and insert/update the integration table
    $selectFromView = "
    SELECT
        firstPlattformId,
        middlePlattformId,
        lastPlattformId,
        logicalAddressId,
        contractId,
        domainId,
        dateEffective,
        dateEnd,
        consumerId,
        producerId
    FROM ViewIntegrationMulti";

    $resultFromView = sqlSelectPrep($selectFromView, "", array());
    $numRowsFromView = $resultFromView->num_rows;

    for ($i = 0; $i < $numRowsFromView; $i++) {
        // Iterate over all records in the view
        $row = $resultFromView->fetch_assoc();

        $firstPlattformId = $row['firstPlattformId'];
        $middlePlattformId = $row['middlePlattformId'];
        $lastPlattformId = $row['lastPlattformId'];
        $logicalAddressId = $row['logicalAddressId'];
        $contractId = $row['contractId'];
        $domainId = $row['domainId'];
        $dateEffective = $row['dateEffective'];
        $dateEndInView = $row['dateEnd'];
        $consumerId = $row['consumerId'];
        $producerId = $row['producerId'];

        //echo($firstPlattformId . "-" . $lastPlattformId);

        // Check if this record exist in the Integration Table
        $selectFromTable = "
            SELECT DISTINCT id, dateEnd
            FROM TakIntegration
            WHERE 
                    firstPlattformId = ?
                AND ((middlePlattformId IS NULL) OR (middlePlattformId = ?))
                AND lastPlattformId = ?
                AND logicalAddressId = ?
                AND contractId = ?
                AND domainId = ?
                AND dateEffective = ?     
                AND consumerId = ?
                AND producerId = ?
             ";

        $resultFromTable = sqlSelectPrep($selectFromTable, "iiiiiisii", array(
            $firstPlattformId,
            $middlePlattformId,
            $lastPlattformId,
            $logicalAddressId,
            $contractId,
            $domainId,
            $dateEffective,
            $consumerId,
            $producerId
        ));
        $numRowsFromTable = $resultFromTable->num_rows;

        /*
        // Temp stuff
        if ($consumerId == 969) {
            echo "\n";
            echo "consumerId = " . $consumerId . "\n";
            echo "numOfRowsFromTable = " . $numRowsFromTable . "\n";
            echo "lastSnapshotTimeInDb = " . $lastSnapshotTimeInDb . "\n";
            echo "timeStamp = " . $timestamp . "\n";
        }
        */

        // Should never be more than one answer row
        if ($numRowsFromTable == 0) {
            //echo ("Inserting new record in TakIntegration\n");
            // New record, insert it

            $insert = "
              INSERT INTO TakIntegration
                     (
                        firstPlattformId,
                        middlePlattformId,
                        lastPlattformId,
                        logicalAddressId,
                        contractId,
                        domainId,
                        dateEffective,
                        dateEnd,
                        consumerId,
                        producerId
                     ) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
              ";

            $dummy = sqlInsertPrep($insert, "iiiiiissii", array(
                $firstPlattformId,
                $middlePlattformId,
                $lastPlattformId,
                $logicalAddressId,
                $contractId,
                $domainId,
                $dateEffective,
                $dateEndInView,
                $consumerId,
                $producerId
            ));

        } elseif ($numRowsFromTable == 1) {
            //echo("Record exist in TakIntegration. ");
            // Existing record(s), if it has an dateEnd = $lastSnapshotTimeInDb in the TakIntegration table - update the field to $timestamp
            // But, it should only be updated if the dateEnd from the view is equal to $timestamp -- 2018-02-09
            $rowFromTable = $resultFromTable->fetch_assoc();
            $dateEndInTable = $rowFromTable['dateEnd'];

            /*
            if ($consumerId == 969) {
                echo "dateEndInTable = " . $dateEndInTable . "\n";
                echo "dateEndInView = " . $dateEndInView . "\n";
            }
            */

            //echo("Date in table=" . $dateEndInTable . "  lastSnapshotTimeInDb=" . $lastSnapshotTimeInDb . "\n");
            // Existing record(s), if it has an dateEnd = $lastSnapshotTimeInDb in the TakIntegration table - update the field to $timestamp
            // But, it should only be updated if the dateEnd from the view is equal to $timestamp -- 2018-02-09
            if ($dateEndInView == $timestamp) {
                // 2018-10-25: below from == to <= . So, if a record is found (including specific dateEffective, its endDate should always move to today (timeStamp) (even thought i might be gaps)
                if ($dateEndInTable <= $lastSnapshotTimeInDb) {
                    $integrationId = $rowFromTable['id'];
                    $update = "
                  UPDATE TakIntegration
                  SET 
                    dateEnd = ?
                  WHERE id = ?
                ";
                    $dummy = sqlUpdatePrep($update, "si", array($timestamp, $integrationId));
                }
            }
        } else {
            // $numRowsFromTable > 1 - Should be an internal error
            die("ERROR - $numRowsFromTable > 1 in ensureIntegration()");
        }
    }
}

function ensureServiceComponent($value, $description)
{

    // Check if this item already exist in the table
    $select = "
            SELECT DISTINCT id, description
            FROM TakServiceComponent 
            WHERE value = ?               
             ";

    $result = sqlSelectPrep($select, "s", array($value));
    $numRows = $result->num_rows;
    if ($numRows < 1) {
        // New component, insert it
        $insert = "
          INSERT INTO TakServiceComponent
           (value , description)
          VALUES  (?, ?) 
      ";
        $id = sqlInsertPrep($insert, "ss", array($value, $description));
    } else {
        // Component exists, check if it needs to be updated
        $row = $result->fetch_assoc();
        $id = $row['id'];

        // If description has changed we need to update
        if ($row['description'] != $description) {
            $update = "
              UPDATE TakServiceComponent
              SET description = ? 
              WHERE id = ?
            ";
            $dummy = sqlUpdatePrep($update, "si", array($description, $id));
            //echo "Component description changed from " . $row['description'] . " --to-- " . $description . "\n";
        }
    }
    return $id;
}

function ensureLogicalAddress($value, $description)
{

    // Check if this item already exist in the table
    $select = "
            SELECT DISTINCT id, description
            FROM TakLogicalAddress 
            WHERE value = ?               
             ";

    $result = sqlSelectPrep($select, "s", array($value));
    $numRows = $result->num_rows;
    if ($numRows < 1) {
        // New item, insert it
        $insert = "
          INSERT INTO TakLogicalAddress
           (value , description)
          VALUES  (?, ?) 
      ";
        $id = sqlInsertPrep($insert, "ss", array($value, $description));
    } else {
        // Item exists, check if it needs to be updated
        $row = $result->fetch_assoc();
        $id = $row['id'];

        // If description has changed we need to update
        if ($row['description'] != $description) {
            $update = "
              UPDATE TakLogicalAddress
              SET description = ? 
              WHERE id = ?
            ";
            $dummy = sqlUpdatePrep($update, "si", array($description, $id));
            //echo "LA description changed from " . $row['description'] . " --to-- " . $description . "\n";
        }
    }

    return $id;
}


function ensureServiceContract($namespace, $major)
{

    // todo: Managing of minor must be moved out to a separate table - eller strunta i det. Vi vet ändock inte vilken som konsumenter och producenter realiserat
    list($domainName, $contractName) = extractDomainContractName($namespace);
    if (!$domainName) {
        return false;
    }

    $domainId = ensureServiceDomain($domainName);

    // Check if this item already exist in the table
    $select = "
            SELECT DISTINCT id
            FROM TakServiceContract 
            WHERE namespace = ?                
             ";

    $result = sqlSelectPrep($select, "s", array($namespace));
    $numRows = $result->num_rows;

    if ($numRows >= 1) {
        $row = $result->fetch_assoc();
        $id = $row['id'];

    } else {
        // New item, insert it
        $insert = "
          INSERT INTO TakServiceContract
           (serviceDomainId, namespace, major, contractName)
          VALUES  (?, ?, ?, ?) 
        ";
        $id = sqlInsertPrep($insert, "isis", array($domainId, $namespace, $major, $contractName));
        // Then insert the minor
    }

    return $id;

}

function ensureServiceDomain($domainName)
{
    // Check if this item already exist in the table

    $select = "
            SELECT DISTINCT id
            FROM TakServiceDomain 
            WHERE domainName = ?                
             ";

    $result = sqlSelectPrep($select, "s", array($domainName));
    $numRows = $result->num_rows;

    if ($numRows >= 1) {
        $row = $result->fetch_assoc();
        $id = $row['id'];
    } else {
        // New item, insert it
        $insert = "
          INSERT INTO TakServiceDomain
           (domainName)
          VALUES  (?) 
        ";

        $id = sqlInsertPrep($insert, "s", array($domainName));
    }

    return $id;
}

/*
function ensureUrl($routingId, $url, $timeStamp, $lastSnapshotTimeInDb)
{


    $update = "
            UPDATE TakUrl
            SET dateEnd = ?
            WHERE
                routingId = ?
              AND url = ?
              AND dateEnd = ?
          ";
    $numRows = sqlUpdatePrep($update, "siss", array($timeStamp, $routingId, $url, $lastSnapshotTimeInDb));

    if ($numRows == 0) {
        // Record does not exist, insert it
        $insert = "
            INSERT INTO TakUrl
            (routingId, url, dateEffective, dateEnd)
            VALUES (?, ?, ?, ?)
        ";
        $dummy = sqlInsertPrep($insert, "isss", array($routingId, $url, $timeStamp, $timeStamp));
    }
}
*/
function ensureRivtaProfile($routingId, $rivtaProfile, $timeStamp, $lastSnapshotTimeInDb)
{
    /*
    $select = "
        SELECT id
        FROM TakRivtaProfile
        WHERE
          routingId = ?
          AND rivtaProfile = ?
          AND dateEnd = ? 
    ";
    $result = sqlSelectPrep($select, "iss", array($routingId, $rivtaProfile, $lastSnapshotTimeInDb));
    $numRows = $result->num_rows;

    if ($numRows >= 1) {
        // Record exist, update dateEnd
        $row = $result->fetch_assoc();
        $rivtaProfileId = $row['id'];

        $update = "
            UPDATE TakRivtaProfile
            SET dateEnd = ?
            WHERE id = ?
        ";
        $dummy = sqlUpdatePrep($update, "si", array($timeStamp, $rivtaProfileId));

        */

    $update = "
            UPDATE TakRivtaProfile
            SET dateEnd = ?
            WHERE
                  routingId = ?
              AND rivtaProfile = ?
              AND dateEnd = ?
        ";
    $numRows = sqlUpdatePrep($update, "siss", array($timeStamp, $routingId, $rivtaProfile, $lastSnapshotTimeInDb));

    if ($numRows == 0) {
        // Record does not exist, insert it
        $insert = "
        INSERT INTO TakRivtaProfile
        (routingId, rivtaProfile, dateEffective, dateEnd)
        VALUES (?, ?, ?, ?)
        ";
        $dummy = sqlInsertPrep($insert, "isss", array($routingId, $rivtaProfile, $timeStamp, $timeStamp));
    }
}


function ensureStatisticsV2($firstPlattformHsaId, $plattform, $consumerHsa, $calls, $averageResponsTime, $namespace, $logicalAddressString, $startDate, $endDate, $updateDateBefore, $updateDateAfter)
{
    /*
 * todo: Manage error in the indata files, see example below
 * Felaktiga HSA-id, ta bort
* ${httpHeaderHsaId
* 444
* SE2321000016-1HZ3
* SE2321000016-I1B6
 */

    if ($consumerHsa == '${httpHeaderHsaId' ||
        $consumerHsa == '444') {
        return;
    }


    $firstPlattformId = getTakRecordId("MetaPlattformHsaId", "takPlattformId", "hsaId", $firstPlattformHsaId);
    $plattformId = getPlattformId($plattform);
    $consumerId = getTakRecordId("TakServiceComponent", "id", "value", $consumerHsa);
    if (!$consumerId) {

        echo "Unknown consumer with HSA-id = " . $consumerHsa . " will be added.\n";
        $consumerId = ensureServiceComponent($consumerHsa, "*** Okänd tjänstekomponent - använd i faktiskt anrop");
    }
    $contractId = getTakRecordId("TakServiceContract", "id", "namespace", $namespace);

    // Handle the case where the logical address field contains a "#" to separate two addresses
    // We only take the rightmost part of a concatenated LA (by reversing the array and extract the first)
    $hashLogicalAddressId = null;
    $seLogicalAddressId = null;

    $logicalAddressArr = array_reverse(explode("#", $logicalAddressString));
    if (array_key_exists(1, $logicalAddressArr)) {
        $logicalAddress = $logicalAddressArr[0];
        $hashLogicalAddressId = getTakRecordId("TakLogicalAddress", "id", "value", $logicalAddressArr[1]);
    } else {
        $logicalAddress = $logicalAddressString;
    }
    $logicalAddressId = getTakRecordId("TakLogicalAddress", "id", "value", $logicalAddress);
    if (!$logicalAddressId) {
        echo "*** Unknown logical address = " . $logicalAddress . " will be added\n";
        $logicalAddressId = ensureLogicalAddress($logicalAddress, "*** Okänd logisk adress - använd i faktiskt anrop");
    }

    // Lets also add support for default routing, both using SE and *
    $seLogicalAddressId = getTakRecordId("TakLogicalAddress", "id", "value", "SE");
    if (!$seLogicalAddressId) {
        $seLogicalAddressId = getTakRecordId("TakLogicalAddress", "id", "value", "*");
    }

    $producerId = searchProducerId($plattformId, $logicalAddressId, $hashLogicalAddressId, $seLogicalAddressId, $contractId, $updateDateBefore, $startDate, $updateDateAfter);

    if ($producerId == null) {
        echo "\n*** Producer NOT found; TP=" . $plattform . ", LA arr=" . $logicalAddressString . ", NS=" . $namespace . "\n";
        //echo "updateDateBefore=" . $updateDateBefore . ", updateDate=" . $startDate . " updateDateAfter=" . $updateDateAfter . "\n";
    }

    $noOfDays = dateDifference($startDate, $endDate) + 1;
    /*
sed 's/echo/echo\n/g' FILE | grep -c "echo"
    */

    // Loop through the dates and verify
    $date = $startDate;
    while ($date <= $endDate) {

        $selectStat = "
                SELECT 
                    id AS statisticsId, 
                    basedOnNumberDays,
                    averageResponseTime
                FROM
                  StatDataTable 
                WHERE 
                        date = ?
                    AND plattformId = ?
                    AND consumerId = ?
                    AND logicalAddressId = ?
                    AND contractId = ?
                ";

        $resultStat = sqlSelectPrep($selectStat, "sssss", array($date, $plattformId, $consumerId, $logicalAddressId, $contractId));
        $numRowsStat = $resultStat->num_rows;

        if ($numRowsStat == 0) {
            //Insert the record
            $insertStat = "
                    INSERT INTO StatDataTable
                      (date, 
                       plattformId, 
                       firstPlattformId, 
                       consumerId, 
                       logicalAddressId, 
                       contractId,
                       producerId, 
                       calls, 
                       averageResponseTime, 
                       basedOnNumberDays) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";

            $dummy = sqlInsertPrep($insertStat,
                "siiiiiiiii",
                array(
                    $date,
                    $plattformId,
                    $firstPlattformId,
                    $consumerId,
                    $logicalAddressId,
                    $contractId,
                    $producerId,
                    $calls,
                    $averageResponsTime,
                    $noOfDays
                )
            );
            if ($dummy == 0) {
                echo "Could not Insert into StatDataTable, dummy=" . $dummy . "\n";
                echo 'firstPlattformHsaId: ' . $firstPlattformHsaId . "\n";
                echo '$plattform: ' . $plattform . "\n";
                echo '$consumerId: ' . $consumerId . "\n";
                echo '$calls: ' . $calls . "\n";
                echo '$meanRespons ' . $averageResponsTime . "\n";
                echo '$namespace: ' . $namespace . "\n";
                echo '$logicalAddress: ' . $logicalAddress . "\n";
                var_dump($logicalAddressArr);
                echo '$date: ' . $date . "\n";

            }
        } else if ($numRowsStat == 1) {
            // Verify basedOnNumberDays to see if the record should be updated
            $rowStat = $resultStat->fetch_assoc();
            $statisticsId = $rowStat['statisticsId'];
            $basedOnNumberDays = $rowStat['basedOnNumberDays'];
            $responseTimeInDb = $rowStat['averageResponseTime'];

            if ($noOfDays < $basedOnNumberDays) {
                // We should update this statistics record if the data in file is based on a smaller number of days than the current record
                $updateStat = "
                        UPDATE StatDataTable
                        SET 
                          calls = ?,
                          basedOnNumberDays = ?
                        WHERE 
                          id = ?     
                    ";
                $dummy = sqlUpdatePrep($updateStat, "iii", array($calls, $noOfDays, $statisticsId));
            }

            if ($averageResponsTime) {
                if (($noOfDays < $basedOnNumberDays) or !$responseTimeInDb) {
                    $updateStat = "
                        UPDATE StatDataTable
                        SET 
                          averageResponseTime = ?
                        WHERE 
                          id = ?     
                    ";
                    $dummy = sqlUpdatePrep($updateStat, "iii", array($averageResponsTime, $statisticsId));
                }
            }

        }

        $date = dateInc($date);
    }

    return;
}

function ensureStatisticsV2PostProcess()
{
// Lets start by listing the records where producerId is null
    echo "\n\n";
    echo "Post processing \n";

    $select = "
    SELECT DISTINCT
           sdt.date as date,
           sdt.plattformId as plattformId,
           comp.value as consumer,
           cont.contractName as contract,
           la.value as logicalAddress
    FROM 
         StatDataTable sdt,
         TPDB.TakServiceComponent comp,
         TPDB.TakServiceContract cont,
         TPDB.TakLogicalAddress la
    WHERE 
        sdt.logicalAddressId = la.id
        AND sdt.contractId = cont.id
        AND sdt.consumerId = comp.id
        AND producerId is null
    ";

    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    echo "Records without producers\n";
    while ($row = $result->fetch_assoc()) {
        echo "Producer not found: " . $row['plattformId'] . " " . $row['date'] . " " . $row['consumer'] . " " . $row['contract'] . " " . $row['logicalAddress'] . "\n";
    }
    // -----------------------------------------------------------------------
    // Will now update records - if needed

    // -----------------------------------------------------------------------
    // Will now remove all records without producers
    $result = sqlSelectPrep($select, "", array());

    $resultArr = array();
    echo "Remaining records without producers\n";
    while ($row = $result->fetch_assoc()) {
        echo "Producer not found: " . $row['plattformId'] . " " . $row['date'] . " " . $row['consumer'] . " " . $row['contract'] . " " . $row['logicalAddress'] . "\n";
    }


    $delete = "
    DELETE FROM StatDataTable
    WHERE producerId is null
    ";

    /* todo: Activate
    $stmt = sqlStmt($delete, "", array());
    echo "Remaining records without producers has been removed removed\n";
    */
}

// This function tries different logical addresses and dates to try to find a producer for this routing
function searchProducerId($plattformId, $logicalAddressId, $hashLogicalAddressId, $seLogicalAddressId, $contractId, $updateDateBefore, $updateDate, $updateDateAfter)
{

    // We use the start date to check the TakRouting table. It is a slight simplification, but so far start and end dates are always the same in the input files
    // But, dateEffective in TakRouting reflects the day AFTER a TAK-change is made and transactions started to flow. Need to decrease the date with one day (one date of TAK-updates)
    // That date is stored in $updateDateBefore
    $producerId = getProducerId($plattformId, $logicalAddressId, $contractId, $updateDateBefore);
    if (!$producerId && $hashLogicalAddressId) {
        //echo "Will try to use # logical address default to identify producer\n";
        $producerId = getProducerId($plattformId, $hashLogicalAddressId, $contractId, $updateDateBefore);
        //echo "----- Tries to set producerId by # ( " . $hashLogicalAddressId . ") \n";
        if (!$producerId && $seLogicalAddressId) {
            $producerId = getProducerId($plattformId, $seLogicalAddressId, $contractId, $updateDateBefore);
            echo "----- Tries to set producerId by SE ( " . $seLogicalAddressId . ") \n";
        }
    }

    // If no producer we try by using the startDate - updateDate
    if ($producerId == null) {
        $producerId = getProducerId($plattformId, $logicalAddressId, $contractId, $updateDate);
        if (!$producerId && $hashLogicalAddressId) {
            //echo "Will try to use # logical address default to identify producer\n";
            $producerId = getProducerId($plattformId, $hashLogicalAddressId, $contractId, $updateDate);
            //echo "----- Tries to set producerId by # ( " . $hashLogicalAddressId . ") \n";
            if (!$producerId && $seLogicalAddressId) {
                $producerId = getProducerId($plattformId, $seLogicalAddressId, $contractId, $updateDate);
                echo "----- Tries to set producerId by SE ( " . $seLogicalAddressId . ") \n";
            }
        }
    }

    // Finally we try the next date - uodateDateAfter
    if ($producerId == null) {
        $producerId = getProducerId($plattformId, $logicalAddressId, $contractId, $updateDateAfter);
        if (!$producerId && $hashLogicalAddressId) {
            //echo "Will try to use # logical address default to identify producer\n";
            $producerId = getProducerId($plattformId, $hashLogicalAddressId, $contractId, $updateDateAfter);
            //echo "----- Tries to set producerId by # ( " . $hashLogicalAddressId . ") \n";
            if (!$producerId && $seLogicalAddressId) {
                $producerId = getProducerId($plattformId, $seLogicalAddressId, $contractId, $updateDateAfter);
                echo "----- Tries to set producerId by SE ( " . $seLogicalAddressId . ") \n";
            }
        }
    }

    // IF we still have not found a producer, the last try is to see if there ever has been one, and only one,
    // TAK configuration for a producer. Then that will be used. It assumes the TAK history is wrong (which has happend)
    if (!$producerId) {
        $producerId = getProducerIdSingleAnyDate($plattformId, $logicalAddressId, $contractId);
        if (!$producerId) {
            $producerId = getProducerIdSingleAnyDate($plattformId, $hashLogicalAddressId, $contractId);
            if (!$producerId) {
                $producerId = getProducerIdSingleAnyDate($plattformId, $seLogicalAddressId, $contractId);
            }
        }
    }

    return $producerId;
}

// This function needs to identify local and remote (downstream) producers
function getProducerId($currentPlattformId, $logicalAddressId, $contractId, $date, $depth = 0)
{

    if ($depth > 3) {
        //echo "ERROR in getProducerHsa(), depth > 3, LA=" . $logicalAddress . ", NS=" . $namespace . "\n";
        return null;
    }

    // First step is to find all producers for this LA och TK (routes)
    $select = "
        SELECT 
             serviceComponentId AS producerId,
             plattformId AS 'producerPlattformId'
        FROM
             TakRouting rout
        WHERE
                rout.logicalAddressId = ?
            AND rout.serviceContractId = ?
            AND rout.dateEffective <= ? 
            AND rout.dateEnd >= ?
    ";

    $result = sqlSelectPrep($select, "iiss", array($logicalAddressId, $contractId, $date, $date));

    $producerArr = array();
    while ($row = $result->fetch_assoc()) {
        $producerArr[$row['producerPlattformId']] = $row['producerId'];
    }

    if (!array_key_exists($currentPlattformId, $producerArr)) {
        return null;
    }

    $producerId = $producerArr[$currentPlattformId];
    if (!$producerId) {
        return null;
    }

    $nextTpId = getPlattformComponent($producerId);

    if ($nextTpId) {
        $nextProducerId = getProducerId($nextTpId, $logicalAddressId, $contractId, $date, $depth + 1);
        if ($nextProducerId) {
            return $nextProducerId;
        } else {
            return $producerId;
        }
    } else {
        return $producerId;
    }
}

function getProducerIdSingleAnyDate($currentPlattformId, $logicalAddressId, $contractId)
{

    // DISTINCT is important since the same producer might be added and removed over time
    $select = "
        SELECT DISTINCT 
             serviceComponentId AS producerId
        FROM
             TakRouting rout
        WHERE
                rout.logicalAddressId = ?
            AND rout.serviceContractId = ?
            AND rout.plattformId = ?
    ";

    $result = sqlSelectPrep($select, "iii", array($logicalAddressId, $contractId, $currentPlattformId));
    $numRows = $result->num_rows;

    if ($numRows == 1) {
        $row = $result->fetch_assoc();
        echo "=========== Found producerId through getProducerIdSingleAnyDate(): " . $row['producerId'] . "\n";
        return $row['producerId'];
    }
    return null;
}

function getProducerHsa($currentPlattform, $logicalAddress, $namespace, $depth = 0)
{

    if ($depth > 3) {
        //echo "ERROR in getProducerHsa(), depth > 3, LA=" . $logicalAddress . ", NS=" . $namespace . "\n";
        return null;
    }

    // First step is to find all producers for this LA och TK (routes)
    $select = "
        SELECT 
             comp.value AS producerHsa,
             CONCAT(tp.name, '-', tp.environment) AS 'producerTp'
        FROM
             TakServiceComponent comp,
             TakLogicalAddress la,
             TakServiceContract tk,
             TakRouting rout,
             TakPlattform tp
        WHERE
                la.value = ?
            AND tk.namespace = ?
            AND rout.plattformId = tp.id
            AND rout.serviceComponentId = comp.id
            AND rout.logicalAddressId = la.id
            AND rout.serviceContractId = tk.id
    ";

    $result = sqlSelectPrep($select, "ss", array($logicalAddress, $namespace));

    $producerHsaArr = array();
    $producerTpNameArr = array();
    while ($row = $result->fetch_assoc()) {
        $producerHsaArr[$row['producerTp']] = $row['producerHsa'];
    }


    if (!array_key_exists($currentPlattform, $producerHsaArr)) {
        return null;
    }
    $producerHsa = $producerHsaArr[$currentPlattform];
    if (!$producerHsa) {
        return null;
    }

    $nextTp = getPlattformName($producerHsa);

    if ($nextTp) {
        $nextProducerHsa = getProducerHsa($nextTp, $logicalAddress, $namespace, $depth + 1);
        if ($nextProducerHsa) {
            return $nextProducerHsa;
        } else {
            return $producerHsa;
        }
    } else {
        return $producerHsa;
    }
}

// This function returns a plattformId if a componentId represents a plattform, otherwise null is returned
function getPlattformComponent($componentId)
{

    if ($componentId == null) {
        return null;
    }

    $select = "
        SELECT 
            mp.takPlattformId AS plattformId
        FROM
            MetaPlattformHsaId mp,
            TakServiceComponent comp
        WHERE
                comp.id = ?
            AND comp.value = mp.hsaId
    ";

    $result = sqlSelectPrep($select, "i", array($componentId));
    $numRows = $result->num_rows;

    if ($numRows != 1) {
        //echo "No plattform name found in getPlattformMane() for hsaid=" . $tpHsaId . "\n";
        return null;
    }

    return $result->fetch_assoc()["plattformId"];

}

function getPlattformName($tpHsaId)
{

    if ($tpHsaId == null) {
        return null;
    }

    $select = "
        SELECT CONCAT(tp.name, '-', tp.environment) AS 'name'
        FROM
            TakPlattform tp,
            MetaPlattformHsaId mp
        WHERE
                mp.takPlattformId = tp.id
            AND mp.hsaId = ?
    ";

    $result = sqlSelectPrep($select, "s", array($tpHsaId));
    $numRows = $result->num_rows;

    if ($numRows != 1) {
        //echo "No plattform name found in getPlattformMane() for hsaid=" . $tpHsaId . "\n";
        return null;
    }

    return $result->fetch_assoc()["name"];

}

function getPlattformId($plattform)
{
    $plattformName = strtok($plattform, "-");
    $plattformEnvir = strtok("-");

    $select = "
      SELECT DISTINCT 
        id 
      FROM  
        TakPlattform 
      WHERE 
            name = ? 
        AND environment = ?
      ORDER BY id 
       ";

    $result = sqlSelectPrep($select, "ss", array($plattformName, $plattformEnvir));

    if ($result->num_rows == 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return $row['id'];

}

function getLastSnapshotTimeInDb($plattform, $environment)
{
    $select = "SELECT DISTINCT
                MAX(lastSnapshot) AS lastSnapshot
              FROM TakPlattform
              WHERE name LIKE ?
                AND environment LIKE ?
         ";

    $result = sqlSelectPrep($select, "ss", array($plattform, $environment));
    $numRows = $result->num_rows;

    if ($numRows == 1) {
        $row = $result->fetch_assoc();
        $lastSnapshot = $row["lastSnapshot"];
        return $lastSnapshot;
    } else {
        return "1900-01-01";
    }
}

function getLastSnapshotTimeInIntegrations()
{
    $select = "SELECT DISTINCT
                MAX(dateEnd) AS date
              FROM TakIntegration
         ";

    $result = sqlSelectPrep($select, "", array());
    $numRows = $result->num_rows;

    if ($numRows == 1) {
        $row = $result->fetch_assoc();
        return $row["date"];
    } else {
        return "1900-01-01";
    }
}

function getTakRecordId($table, $selectId, $field, $value)
{

    $select = "
      SELECT DISTINCT 
        " . $selectId . " 
      FROM  
        " . $table . "  
      WHERE 
        " . $field . " = ?
      ORDER BY id 
       ";

    $result = sqlSelectPrep($select, "s", array($value));

    if ($result->num_rows == 0) {
        return null;
    }

    $row = $result->fetch_assoc();
    return $row[$selectId];
}

function getValue($arr, $key)
{
    if (array_key_exists($key, $arr)) {
        $result = preg_replace('/[\x00-\x1F]/', '', $arr[$key]);
        return $result;
    } else {
        //echo "getValue(): key=" . $key . " värde saknas i följande item:\n";
        //var_dump($arr);
        return '** värde saknas: ' . $key . ' **';
    }

}

function emptyDatabase($scope)
{

    $i = 0;

    if ($scope === "StatData" || $scope === "ALL") {

        $sql[$i] = "DELETE FROM StatDataTable WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE StatDataTable AUTO_INCREMENT = 1";
        $i++;

    }

    if ($scope === "ALL") {

        $sql[$i] = "DELETE FROM TakIntegration WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakIntegration AUTO_INCREMENT = 1";
        $i++;
        /*
                $sql[$i] = "DELETE FROM TakUrl WHERE id <> 0";
                $i++;
                $sql[$i] = "ALTER TABLE TakUrl AUTO_INCREMENT = 1";
                $i++;
        */
        $sql[$i] = "DELETE FROM TakRivtaProfile WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakRivtaProfile AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "DELETE FROM TakCallAuthorization WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakCallAuthorization AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "DELETE FROM TakRouting WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakRouting AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "DELETE FROM TakLogicalAddress WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakLogicalAddress AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "DELETE FROM TakServiceContract WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakServiceContract AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "DELETE FROM TakServiceDomain WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakServiceDomain AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "DELETE FROM TakServiceComponent WHERE id <> 0";
        $i++;
        $sql[$i] = "ALTER TABLE TakServiceComponent AUTO_INCREMENT = 1";
        $i++;

        $sql[$i] = "UPDATE TakPlattform SET lastSnapshot = '2016-01-01' WHERE id <> 0";
        $i++;

    }

    for ($i = 0; $i < count($sql); $i++) {
        echo $sql[$i] . "\n";
        sqlStmt($sql[$i], "", array());
    }


}


function getConnectionPoints()
{
    global $TAKAPI_URL;
    $cmdUrl = $TAKAPI_URL . 'connectionPoints';
    //return json_decode(file_get_contents($cmdUrl), true); // +++++
    return json_decode(callTakApi($cmdUrl), true);
}

/*
function getConsumers($connectionPointId)
{
    global $BASEURL;
    $cmdUrl = $BASEURL . 'serviceConsumers?connectionPointId=' . $connectionPointId;
    return json_decode(file_get_contents($cmdUrl), true);
}

function getProducers($connectionPointId)
{
    global $BASEURL;
    $cmdUrl = $BASEURL . 'serviceProducers?connectionPointId=' . $connectionPointId;
    return json_decode(file_get_contents($cmdUrl), true);
}

function getLogicalAddresses($connectionPointId)
{
    global $BASEURL;
    $cmdUrl = $BASEURL . 'logicalAddresss?connectionPointId=' . $connectionPointId;
    return json_decode(file_get_contents($cmdUrl), true);
}

function getServiceContracts($connectionPointId)
{
    global $BASEURL;
    $cmdUrl = $BASEURL . 'serviceContracts?connectionPointId=' . $connectionPointId;
    return json_decode(file_get_contents($cmdUrl), true);
}
*/
function getCooperations($connectionPointId)
{
    global $TAKAPI_URL;
    $cmdUrl = $TAKAPI_URL . 'cooperations?connectionPointId=' . $connectionPointId . '&include=serviceConsumer,serviceContract,logicalAddress';

    //return json_decode(file_get_contents($cmdUrl), true);
    // $content = file_get_contents($cmdUrl); // +++++
    $content = callTakApi($cmdUrl);
    if (strlen($content) < 100) {
        echo "*** Error, cooperations string to short: " . $content . "\n";
        return false;
    }

    return json_decode($content, true);
}

function getProductions($connectionPointId)
{
    global $TAKAPI_URL;
    //$cmdUrl = $TAKAPI_URL . 'serviceProductions?connectionPointId=' . $connectionPointId . '&include=serviceContract,logicalAddress,serviceProducer,physicalAddress';
    // Do not download full URLs anymore. Security reasons.
    $cmdUrl = $TAKAPI_URL . 'serviceProductions?connectionPointId=' . $connectionPointId . '&include=serviceContract,logicalAddress,serviceProducer';
    //return json_decode(file_get_contents($cmdUrl), true);

    // $content = file_get_contents($cmdUrl); // +++++
    $content = callTakApi($cmdUrl);
    if (strlen($content) < 100) {
        echo "*** Error, serviceProduction string to short: " . $content . "\n";
        return false;
    }

    return json_decode($content, true);
}

function getUniqueComponents($consumers, $producers)
{
    // If both consumers and producers are provided, remove duplicate and return unique list (duplicare producers are removed)
    // If either only consumers och producers provided, return them

    if ($consumers && $producers) {

        $components = $consumers;

        foreach ($producers as $prod) {
            $found = false;
            foreach ($consumers as $cons) {
                if ($cons['hsaId'] == $prod['hsaId']) {
                    $found = true;
                }
            }
            if (!$found) {
                array_push($components, $prod);
            }
        }
        return $components;
    } elseif ($consumers) {
        return $consumers;
    } else {
        return $producers;
    }

}

function extractDomainContractName($namespace)
{
    $token = strtok($namespace, ":");

    $nameArr = array($token);

    while ($token !== false) {
        $token = strtok(":");
        array_push($nameArr, $token);
    }

    $nameArrLen = count($nameArr);
    if ($nameArrLen < 6) {
        echo "*** Name space error, too few commas: " . $namespace . "\n";
        return array(false, false);
        //return array("Illegal namespace", $namespace);
    }

    $domain = $nameArr[2];
    for ($i = 2 + 1; $i < $nameArrLen - 3; $i++) {
        $domain = $domain . ":" . $nameArr[$i];
    }
    $contract = $nameArr[$nameArrLen - 3];

    if (substr($contract, strlen($contract) - 9, strlen($contract)) !== "Responder") {
        echo "*** Name space error, does not end with Responder: " . $namespace . "\n";
        return array(false, false);
    }

    $contract = substr($contract, 0, strlen($contract) - 9);
    return array($domain, $contract);

}

function dateDifference($date_1, $date_2)
{
    $datetime1 = date_create($date_1);
    $datetime2 = date_create($date_2);

    $interval = date_diff($datetime1, $datetime2);

    return $interval->format('%a');

}

function dateInc($date)
{
    $date1 = date_create($date);
    $diff1Day = new DateInterval('P1D');
    $date1->add($diff1Day);
    return $date1->format('Y-m-d');
}

function getRoutingUpdateDates()
{
    $select = " 
        SELECT DISTINCT 
          dateEffective AS date
        FROM
          TPDB.TakRouting 
        
        UNION DISTINCT 
        SELECT DISTINCT 
          dateEnd AS date
        FROM
          TPDB.TakRouting
          
        ORDER BY date DESC  
        ";

    $result = sqlSelectPrep($select, "", array());

    $dateArr = array();
    while ($row = $result->fetch_assoc()) {
        $dateArr[] = $row['date'];
    }

    return $dateArr;
}

function getUpdateDateBefore($routingDates, $serachDate)
{
    foreach ($routingDates as $value) {
        if ($value < $serachDate) {
            return $value;
        }
    }
    return $serachDate;
}

function getUpdateDateAfter($routingDates, $serachDate)
{
    $rDates = array_reverse($routingDates);
    foreach ($rDates as $value) {
        if ($value > $serachDate) {
            return $value;
        }
    }
    return $serachDate;
}

function csv_to_array($filename = '', $delimiter = ';')
{
    if (!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE) {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            // Lets trim all values
            for ($i = 0; $i < sizeof($row); $i++) {
                $row[$i] = trim($row[$i]);
            }

            if (!$header) {
                $header = $row;
                $header[0] = 'type';  // Hack to get rid if a possible BOM
            } else
                $data[] = array_combine($header, $row);
        }
        fclose($handle);
    }
    return $data;
}

function upgrade_62_to_64()
{

    // Perform only if table MetaSource does not exist

    $dbname = leoGetenv('DBNAME');
    $VERSION = "6.4";
    $DEPLOY_DATE = "2019-06-01";

    $select = "
    SELECT * 
    FROM information_schema.tables
    WHERE table_schema = '" . $dbname . "'  
    AND table_name = 'StatDataTable'
    LIMIT 1";

    $result = sqlSelectPrep($select, "", array());
    $numRows = $result->num_rows;

    if ($numRows > 0) {
        echo "StatDataTable does exist - upgrade already done\n";
        return;
    }

    echo "StatDataTable does NOT exist - will upgrade DB\n";

    $create = "
    create table StatDataTable
(
    id                  mediumint auto_increment
        primary key,
    date                date      not null,
    plattformId         mediumint not null,
    firstPlattformId    mediumint null,
    consumerId          mediumint not null,
    logicalAddressId    mediumint not null,
    contractId          mediumint not null,
    producerId          mediumint null,
    calls               mediumint not null,
    averageResponseTime mediumint null,
    basedOnNumberDays   mediumint not null,
    constraint StatDataTable_TakLogicalAddress_id_fk
        foreign key (logicalAddressId) references TakLogicalAddress (id),
    constraint StatDataTable_TakPlattform_id_fk
        foreign key (firstPlattformId) references TakPlattform (id),
    constraint StatDataTable_TakPlattform_id_fk_2
        foreign key (plattformId) references TakPlattform (id),
    constraint StatDataTable_TakServiceComponent_id_fk
        foreign key (consumerId) references TakServiceComponent (id),
    constraint StatDataTable_TakServiceComponent_id_fk_2
        foreign key (producerId) references TakServiceComponent (id),
    constraint StatDataTable_TakServiceContract_id_fk
        foreign key (contractId) references TakServiceContract (id)
)
	comment 'The new statistics data table'
    ";

    $index1 = "create index StatDataTable_index on StatDataTable (date, plattformId, consumerId, logicalAddressId, contractId)";
    $index2 = "create index StatDataTable_date_index on StatDataTable (date)";


    sqlStatementWithPrep($create, "", array());
    sqlStatementWithPrep($index1, "", array());
    sqlStatementWithPrep($index2, "", array());

    // Update version
    $updateStat = "
                        UPDATE TPDB.MetaVersion
                        SET 
                          version = ?,
                          deployDate = ?
                    ";
    $dummy = sqlUpdatePrep($updateStat, "ss", array($VERSION, $DEPLOY_DATE));

    // Clear out LAs added by log load
    $delete = "
    DELETE FROM TakLogicalAddress
    WHERE description LIKE '***%'
    ";
    $dummy = sqlStmt($delete, "", array());

    // Clear out components added by log load
    $delete = "
    DELETE FROM TakServiceComponent
    WHERE description LIKE '***%'
    ";
    $dummy = sqlStmt($delete, "", array());

    return;
}

?>
