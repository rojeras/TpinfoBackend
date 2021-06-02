# Overview
This repo contains the TPDB-api. It is the backend, server part, of tpinfo (where hippo and SLL Statistik make up the frontend).

# Local installation
Clone this repository. 

## Prerequisites
To be able to run the application the following must be fulfilled:
* A unix system; Linux or MacOS 
* Database engine; MariaDB (preferred) or MySQL
* PHP v7.x, including
    * php-mysql (mysqli) (version 7.x)
    * php-curl (version 7.x)

It should be quite easy to port the startup scripts to make it possible to run tpinfo-backend in Windows, but the need has (thankfully) not yet materialized. 

## Installation of the database
To use the application the database need to exist. The simples way to install it is to obtain an sqldump of the production och QA-database of tpinfo. 

Then do the following:
1. Create the SQL user "TPDB". The follwing steps should be performed by that user.
1. The TPDB user should create a database with name "TPDB"
1. The TPDB user should load (source) the database dump. It might end in some error message which can be disregarded.
1. Verify that the tables are created and filled with data.

There are three views that must be defined in the database. Sometimes they are not imported correctly and should be (re)created. The views are:
* ViewIntegrationOne
* ViewIntegrationTwo
* ViewIntegrationMulti

Do the following:
1. Delete the three views if they exist
1. The sql statements to define the view are located in *build* folder, one SQL script for each view. Run (source) them to the TPDB database in the order they are listed above. 

# Run tpdb-backend locally

The built in PHP web server is a simple way to run the application locally. There are two files that need to be modified to be able to start it.

### bin/backend-envir.list

The first four environment variables need to be set to be able to start the api server
* DBSERVER = localhost
* DBUSER = TPDB
* DBPWD = *the password you gave the TPDB user*
* DBNAME = TPDB

### bin/local-run-backend.sh

This script should work on any unix system, but please verify it anyway. 
Observe that the startup script creates a *cache* folder in the *source* folder. Caching is activated, and cache data will be stored in files in the *cache* directory. They should not be added to the repo, and ensure to delete it if you are testing changes to the api source code. 

After *local-run-backend.sh* has been successfully started the API can be called.
Examples:

* http://localhost:5555/tpdb/tpdbapi.php/api/v1/version
* http://localhost:5555/tpdb/tpdbapi.php/api/v1/dates
* http://localhost:5555/tpdb/tpdbapi.php/api/v1/plattforms
* http://localhost:5555/tpdb/tpdbapi.php/api/v1/components
* http://localhost:5555/tpdb/tpdbapi.php/api/v1/contracts
* http://localhost:5555/tpdb/tpdbapi.php/api/v1/logicalAddress

# Updating the database
There are a number of PHP-scripts which are used to update and extract information from the database. 

### tpdbupdate.php
This script run in two steps. The first step consists of calling Ineras TAK-api (http://api.ntjp.se/coop/doc/index.html) and fetch the latest updates of the TAKs it support. 

In the second step statistics files (which are produced by Region Stockholms RTPs logstash), are read and used to update the database with statistics information. 

### loadsynonyms.php
It is used to read synonym definitions from a file and update the MetaSynonym table in the database. Synonyms are used in the Statistics application. An example synonym file can be found in the *build* directory. 

### mkstathistory.php
This script exports statistics information to CSV-files used by some organizations within Region Stockholm. 

## Running the scripts
The simplest way to run the script is to use PHP web server discussed above. 

It requires additional information in the *backend-envir.lst* file. 

* DBSERVER = localhost
* DBUSER = TPDB
* DBPWD = *the password you gave the TPDB user*
* DBNAME = TPDB
* STATFILESPATH = *path to directory containing statistics input files* 
* SYNONYMFILE = *path to synonym input file* 
* HISTORYFILEPATH = *path to **existing** directory where exported statistics files should be written.* 

Then start the **local-run-backend.sh** script. Now the different scripts can be invoked through the *curl* command (or via a web browser)

**curl http://localhost:5555/tpdb/tpdbupdate.php**

It takes a long time, and there will be quite a lot of output. Check the *snapshotTime* column in the  *TakPlattform* table, it should be updated with today's date. 

**curl http://localhost:5555/tpdb/mkstathistory.php**

Verify the files have been created in the *HISTORYFILEPATH* directory.

**curl http://localhost:5555/tpdb/loadsynonyms.php**