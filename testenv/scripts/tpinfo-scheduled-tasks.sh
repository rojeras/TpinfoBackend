#!/bin/sh

# This script is scheduled to run daily at 3 am on QA and 4 am on the production server.

# This call will invoke a db update, based on TAK-api and statistic files
curl localhost:8081/tpdb/tpdbupdate.php > logfile 2>&1
