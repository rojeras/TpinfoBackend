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

docker run -d -p 8080:80 rojeras/tpinfo-frontend:latest-$envir

