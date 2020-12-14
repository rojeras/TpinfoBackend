#!/bin/sh
if [ "$#" -ne "2" ]; then
	echo "Sepecify two parameters:"
	echo
	echo "       $0 tag port"
	echo
	exit 1;
fi

tag="$1"
port="$2"

#ENVIRFILE=src/backend-envir.lst

#set -o allexport
#source $ENVIRFILE
#set +o allexport

export DBSERVER=localhost

#export DBSERVER=localhost 
export DBSERVER=host.docker.internal 

docker container stop $(docker container ls -q --filter name=rojeras/tpinfo-backend*)
docker run --env-file=bin/backend-envir.lst -d -e DBSERVER -e DBUSER -e DBPWD -e DBNAME -p $port:80 $tag


