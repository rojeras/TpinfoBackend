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

require_once 'leolib.php';

function sqlConnectEnvs() {
    $dbserver   = 'p:' . leoGetenv('DBSERVER'); // The "p:" adds a persistent connection
    $dbuser     = leoGetenv('DBUSER');
    $dbpassword = leoGetenv('DBPWD');
    $dbname     = leoGetenv('DBNAME');

    return sqlConnect($dbserver, $dbuser, $dbpassword, $dbname);
}

function sqlConnect($servername, $username, $password, $dbname)
{
    // Create connection

    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    if (!$conn->set_charset("utf8")) {
        printf("Error loading character set utf8: %s\n", $conn->error);
        exit();
    }

    return $conn;
}

function rollbackAndDie($message)
{
    global $DBCONN;

    $DBCONN->rollback();
    die($message);
}

function sqlStmt($sql, $paramTypes, $paramArr, $verbose = null)
{
    $prepStmt = sqlStatementWithPrep($sql, $paramTypes, $paramArr, $verbose);

    $result = $prepStmt->get_result();

    return $result;
}


function sqlSelectPrep($select, $paramTypes, $paramArr, $verbose = null)
{
    $prepStmt = sqlStatementWithPrep($select, $paramTypes, $paramArr, $verbose);

    $result = $prepStmt->get_result();

    if (!$result) {
        echo '*** Null result from prepStmt->get_result()' . "\n";
        echo $select . "\n";
        echo 'Parameter types: ' . $paramTypes . "\n";
        echo 'Parameter array: '; var_dump($paramArr);
        echo "Dump of prepStmt: ";
        var_dump($prepStmt);

        rollbackAndDie("$result is false in sqlSelectPrep\n");
    }

    if ($verbose) {
        $numRows = $result->num_rows;
        echo "Number of rows returned = " . $numRows . " \n";
    }

    return $result;
}

function sqlInsertPrep($insert, $paramTypes, $paramArr, $verbose = null)
{

    $prepStmt = sqlStatementWithPrep($insert, $paramTypes, $paramArr, $verbose);

    if ($prepStmt) {

        if ($verbose) {
            echo $insert . "\n";
        }
        $id = $prepStmt->insert_id;

        $prepStmt->reset();
        return $id;
    } else {
        echo "Error: " . $insert . " - " . $prepStmt->error . "\n";
        for ($i = 0; $i < count($paramArr); $i++) {
            echo $paramArr[$i] . " | ";
        }
        echo "\n";
        var_dump($prepStmt);
    }
}

function sqlUpdatePrep($update, $paramTypes, $paramArr, $verbose = null)
{

    global $DBCONN;

    $prepStmt = sqlStatementWithPrep($update, $paramTypes, $paramArr, $verbose);

    //$result = $conn->query($insert);
    if ($prepStmt) {
        $noRows = $prepStmt->affected_rows;

        if ($verbose) {
            echo $update . " <> No of rows: " . $noRows . "\n";
        }

        $prepStmt->reset();
        return $noRows;
    } else {
        echo "Error: " . $update . " - " . $prepStmt->error . "\n";
        for ($i = 0; $i < count($paramArr); $i++) {
            echo $paramArr[$i] . " | ";
        }
        echo "\n";
    }
}

function sqlDropTablePrep($table)
{

    $delete = "DROP TABLE " . $table;

    $prepStmt = sqlStatementWithPrep($delete, "", array());

    if (! $prepStmt) {
        die("Error DELETE TABLE " . $table . " - " . $prepStmt2->error . "\n");
    }

}


function sqlStatementWithPrep($sqlStmt, $paramTypes, $paramArr, $verbose = null)
{

    global $DBCONN;

    static $PREP_STMTS;

    if ($verbose) {
        echo "In sqlStatementPrep(): \n"
            . "  select: " . $sqlStmt . "\n"
            . "  parameter types: " . $paramTypes . "\n"
            . "  parameters: ";
        for ($i = 0; $i < count($paramArr); $i++) {
            echo $paramArr[$i] . " | ";
        }
        echo "\n";
    }

    if (!$PREP_STMTS) {
        $PREP_STMTS = array();
    }

    // If this sql has not been prepared previously we do it now
    $keys = array_keys($PREP_STMTS);
    if (!in_array($sqlStmt, $keys)) {
        if ($verbose) {
            echo "Will prepare: " . $sqlStmt . "\n";
        }

        if ( ! $prepStmt = $DBCONN->prepare($sqlStmt)) {
            echo "Prepare statement failed: ". $DBCONN->error . "\n";
            echo $sqlStmt;
            die('Giving up...');
        }

        //$prepStmt->bind_param($paramTypes, ...$paramArr);
        // And we save the statement for reuse
        $PREP_STMTS[$sqlStmt] = $prepStmt;
    } else {
        // Just reuse a prepared statement
        $prepStmt = $PREP_STMTS[$sqlStmt];
    }

    if (count($paramArr) > 0) {
            /*
            echo "In sqlStatementPrep(): \n"
                . "  select: " . $sqlStmt . "\n"
                . "  parameter types: " . $paramTypes . "\n"
                . "  parameters: ";
            for ($i = 0; $i < count($paramArr); $i++) {
                echo $paramArr[$i] . " | ";
            }
            echo "\n";
            */
        $prepStmt->bind_param($paramTypes, ...$paramArr);
    }

    $prepStmt->execute();

    return $prepStmt;

}

function mkWhereClause($param, $idString) {

        $idArr = explode(',', $idString);
        $noOfIds = count($idArr);
        $typeString = str_repeat('i', $noOfIds);
        $questionMarks = str_repeat('?,', $noOfIds);$questionMarks = rtrim($questionMarks,',');

        $whereClause = $param . ' IN (';
        $whereClause .= $questionMarks;
        $whereClause .= ')';

        return array($whereClause, $typeString, $idArr);

}

?>