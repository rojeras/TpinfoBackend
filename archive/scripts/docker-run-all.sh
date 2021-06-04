#!/bin/bash

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

echo "Stopping all executing containers"
docker container stop $(docker container ls -q)

TOPDIR=/Users/leo/Documents/data/Eternal/development/tpinfo

echo "Starting backend"
$TOPDIR/common/scripts/docker-run-backend.sh $envir

echo "Staring frontend"
$TOPDIR/common/scripts/docker-run-frontend.sh $envir

echo
echo "Use URL: http://hippohost:8888/hippo/hippo.html or http://stathost:8888/stat/statistics.html" 
