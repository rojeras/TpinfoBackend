# Overview
This repo contains the TPDB-api. It is the backend, server part, of tpinfo (where hippo and SLL Statistik make up the frontend).

# Local installation
Clone this repository. 

## Prerequisites
To be able to run the application the following must be fulfilled:
* A unix system; Linux or MacOS 
* Database engine; MariaDB (preferred) or MySQL
* PHP v7 and php-mysql (mysqli)

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



# Miljövariabler
Följande miljövariabler skall vara satta när dessa PHP-script exekverar:  

* DBSERVER
* DBUSER
* DBPWD
* DBNAME
* STATFILESPATH
* SYNONYMFILE

