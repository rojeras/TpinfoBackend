#!/bin/sh

envir="$1"

if [ "$#" -eq "1" ]; then	
	echo "One parameter specified"
fi

if [[ $envir == "qa" ]]; then 
	echo "qa specified"
elif [[ "$envir" == "prod" ]]; then
       echo "prod specified"
else 
	echo "Unknown parameter, pls specify \"qa\" or \"prod\"."
	exit 1;
fi


TOPDIR=/Users/leo/Documents/data/Eternal/development/tpinfo
ENVIRFILE=$TOPDIR/backend-envir.lst

set -o allexport
source $ENVIRFILE
set +o allexport
export DBSERVER=localhost

#export DBSERVER=localhost 
export DBSERVER=host.docker.internal 

docker run -d -e DBSERVER -e DBUSER -e DBPWD -e DBNAME -p 8081:80 rojeras/tpinfo-backend:latest-$envir


