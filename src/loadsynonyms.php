<?php
ini_set('memory_limit', '1024M');
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2018-01-29
 */

/*
 SQL to extract info about serviceComponents and their synonyms (if any)

SELECT
  'serviceComponent',
  sc.value,
  ms.synonym,
  sc.description
FROM
  TakServiceComponent sc LEFT JOIN MetaSynonym ms
    ON ms.originalIdentifier = sc.value;
-----------------------------------------
SELECT DISTINCT
  'serviceComponent',
  comp.value,
  comp.description
FROM
  TakServiceComponent comp,
  TakIntegration integ,
  StatData stat
WHERE
  stat.integrationId = integ.id
  AND
  (integ.consumerId = comp.id
    OR integ.producerid = comp.id)
ORDER BY
  comp.description
;

 */
require 'leolib_sql.php';

/* TARGET::NOGUI */ $iniFile = "/home/leoroj/ini/statdbupdate.ini";
/* TARGET::AWS */ $iniFile = "../lib/statdbupdate.ini";
/* TARGET::LOCAL AWS */ $iniFile = "../lib/statdbupdate.ini";

$ini_array = parse_ini_file($iniFile, true);

$dbEnvironment = '[[DATABASE]]'; // Will be substituted by build.py

/* TARGET::REMOVE_DURING_BUILD */ $dbEnvironment = 'DB-LOCAL';

$ini_values = $ini_array[$dbEnvironment];

$INI_dbserver = $ini_values['dbserver'];
$INI_dbuser = $ini_values['dbuserrw'];
$INI_dbpassword = $ini_values['dbpasswordrw'];
$INI_dbname = $ini_values['dbname'];

$STATAPIROOT = '../statapicache/';
echo "STATAPIROOT: " . $STATAPIROOT . "\n";
define('STATAPIROOT', $STATAPIROOT);

$SYNONYMFILE = 'MetaSynonym.csv';
define('SYNONYMFILE', $SYNONYMFILE);

// Report all errors
error_reporting(E_ALL);

// Get a connection to the DB
$DBCONN = sqlConnect($INI_dbserver, $INI_dbuser, $INI_dbpassword, $INI_dbname);

echo "DB: " . $INI_dbserver . "\n";

echo "Start! \n";

echo("Load synonyms\n");
loadSynonyms();

echo 'Klart!';
echo '';

// End of main program

function loadSynonyms()
{
    GLOBAL $STATAPIROOT;
    GLOBAL $DBCONN;
    GLOBAL $SYNONYMFILE;

    $DBCONN->begin_transaction();

    $file = $STATAPIROOT . $SYNONYMFILE;

    $csvData = csv_to_array($file);

    //var_dump($csvData);

    foreach ($csvData as $item) {
        $type = trim($item['type'], "\xFE..\xFF");
        $originalIdentifier = $item['originalIdentifier'];
        $synonym = $item['synonym'];

        echo $type . ' - ' . $originalIdentifier . ' - ' . $synonym;

        // Check if this synonym exist i the DB

        $select = "
        SELECT 
            id,
            synonym
        FROM MetaSynonym
        WHERE 
              type = ?
          AND originalIdentifier = ?
        ";

        $result = sqlSelectPrep($select, "ss", array($type, $originalIdentifier));
        $numRows = $result->num_rows;

        // We have two cases
        // 1. If the synonym is empty, then ensure the record is deleted from the DB
        if ( strlen($synonym)==0 ) {
            $sql = "
              DELETE FROM MetaSynonym
              WHERE
                    type = ?               
                AND originalIdentifier = ? 
             ";

            $dummy = sqlStmt($sql, "ss", array($type, $originalIdentifier));
            echo " -- Remove synonym\n";
        }
        // 2. Else insert/update
        else {
            $sql = "
                INSERT INTO MetaSynonym (type, originalIdentifier, synonym)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                  id=LAST_INSERT_ID(id),
                  synonym=VALUES(synonym)
            ";
            $dummy = sqlInsertPrep($sql, "sss", array($type, $originalIdentifier, $synonym));
            echo " -- Insert/Update synonym - rc = " . $dummy . "\n";
        }

    }

    $DBCONN->commit();

}
// Ensure a certain plattform, identified by name and environment, exists in the Plattform table

function csv_to_array($filename='')
{
    // This function only reads the first three columns from the CSV
    $noOfColumns = 3;
    $delimiter=';';

    if(!file_exists($filename) || !is_readable($filename))
        return FALSE;

    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE)
    {
        while (($rowIn = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
        {
            // Lets trim all values
            //for ($i = 0; $i < sizeof($row); $i++) {
            for ($i = 0; $i < $noOfColumns; $i++) {
                $row[$i] = trim($rowIn[$i]);
            }

            //var_dump($row);

            if(!$header) {
                $header = $row;
                $header[0] = 'type';  // Hack to get rid if a possible BOM
            }
            else
                $data[] = array_combine($header, $row);
        }

        fclose($handle);
    }

    var_dump($data);
    return $data;
}

?>
