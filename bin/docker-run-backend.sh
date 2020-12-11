#!/bin/sh

image="$1"

ENVIRFILE=src/backend-envir.lst

set -o allexport
source $ENVIRFILE
set +o allexport
export DBSERVER=localhost

#export DBSERVER=localhost 
export DBSERVER=host.docker.internal 

docker run -d -e DBSERVER -e DBUSER -e DBPWD -e DBNAME -p 7777:80 $image


