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
ini_set('memory_limit', '1024M');

// Report all errors
error_reporting(E_ALL);

require 'leolib_sql.php';
require_once 'leolib.php';

$historyFilePath = leoGetenv('HISTORYFILEPATH');

// Get a connection to the DB
$DBCONN = sqlConnectEnvs();

echo "Start! \n";

echo("Generate statistic files\n");
mkStatHistory($historyFilePath);

echo 'Done!';
echo '';

// End of main program

function mkStatHistory($path)
{
    GLOBAL $DBCONN;

    // RTP-PROD_statistik_YYYY-MM.csv;

    // Find out for which months we have statistics in the db

    $select = "
    SELECT DISTINCT
      SUBSTRING(date, 1, 7) AS MONTH
    FROM StatDataTable
    ORDER BY MONTH;
    ";

    $result = sqlSelectPrep($select, "", array());

    while ($row = $result->fetch_assoc()) {
        //$resultArr[$row["day"]] = (int)$row["numberOfCalls"];
        $month = $row["MONTH"];
        //echo $month . "\n";
        createHistoryFile($path, $month);
    }

    return;

}

function createHistoryFile($path, $month) {
    $file = $path . "/RTP-PROD_statistik_" . $month . ".csv";
    echo  date(DATE_ATOM) . " " . $file;
    //unlink($file);

    $sql = "
    SELECT
       'Date',
       'ConsumerHSA',
       'ConsumerDescription',
       'Domain',
       'Contract',
       'LogicalAddress',
       'LogicalAddressDescription',
       'ProducerHSA',
       'ProducerDescription',
       'Calls'
    UNION ALL
    (SELECT DISTINCT
                stats.date            AS Date,
                consumer.value        AS ConsumerHSA,
                consumer.description  AS ConsumerDescription,
                domain.domainName     AS Domain,
                contract.contractName AS Contract,
                la.value              AS LogicalAddress,
                la.description        AS LogicalAddressDescription,
                producer.value        AS ProducerHSA,
                producer.description  AS ProducerDescription,
                stats.calls           AS Calls
    FROM
     TakServiceComponent consumer,
     TakServiceComponent producer,
     TakServiceContract contract,
     TakServiceDomain domain,
     TakLogicalAddress la,
     StatDataTable stats
    WHERE
          stats.consumerId = consumer.id
      AND stats.contractId = contract.id
      AND contract.serviceDomainId = domain.id
      AND stats.logicalAddressId = la.id
      AND stats.producerid = producer.id
      AND stats.plattformId = 3
      AND stats.producerId IS NOT NULL
        -- AND (stats.day between DATE_FORMAT(NOW(), '%Y-%m-01') AND NOW())
      AND DATE_FORMAT(stats.date, '%Y-%m') LIKE ?
    -- AND stats.day = '2018-04-10'
    ORDER BY Date, Domain, Contract
    )";


    $result = sqlSelectPrep($sql, "s", array($month));

    $wfile = fopen($file, "w") or die("Unable to open file: " . $file . "\n");

    $maxDate = '1900-01-01';

    while ($row = $result->fetch_assoc()) {
        $thisDate = $row['Date'];

        fwrite($wfile, $thisDate . ';');
        fwrite($wfile, $row['ConsumerHSA'] . ';');
        fwrite($wfile, $row['ConsumerDescription'] . ';');
        fwrite($wfile, $row['Domain'] . ';');
        fwrite($wfile, $row['Contract'] . ';');
        fwrite($wfile, $row['LogicalAddress'] . ';');
        fwrite($wfile, $row['LogicalAddressDescription'] . ';');
        fwrite($wfile, $row['ProducerHSA'] . ';');
        fwrite($wfile, $row['ProducerDescription'] . ';');
        fwrite($wfile, $row['Calls']);
        fwrite($wfile, "\n");

        if ($thisDate !== "Date" && $thisDate > $maxDate) {
            $maxDate = $thisDate;
        }

    }

    echo " Max date = " . $maxDate . "\n";

    return;
}

?>
