#!/bin/bash

CURRENTDIR=$(pwd)

TOPDIR=/home/leo/tmp/dev/tpinfo-backend
ENVIRFILE=/home/leo/Documents/protected/security/backend-envir.lst
RUNDIR=/home/leo/tmp/runbackend

mkdir $RUNDIR 2> /dev/null
cd $RUNDIR

rm tpdb

ln -s $TOPDIR/src tpdb 2> /dev/null
ln -s /tmp/history history 2> /dev/null
# ln -s ../../statapicache/active statapifiles 2> /dev/null

set -o allexport
source $ENVIRFILE
set +o allexport
# export DBSERVER=localhost

# echo "Use http://localhost:5555/hippo/hippo.html or http://localhost:5555/stat/statistics.html"
echo "Backend running and listening to port 5555"

php -S localhost:5555 -t .

cd $CURRENTDIR

