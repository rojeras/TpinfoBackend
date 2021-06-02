#!/bin/bash

CURRENTDIR=$(pwd)

TOPDIR=$HOME/tmp/dev/tpinfo-backend
ENVIRFILE=$HOME/tmp/dev/tpinfo-backend/bin/backend-envir.lst
RUNDIR=$HOME/tmp/runbackend

rm -rf $RUNDIR
mkdir $RUNDIR
cd $RUNDIR

ln -s $TOPDIR/src tpdb 2> /dev/null
ln -s /tmp/history history 2> /dev/null
# ln -s ../../statapicache/active statapifiles 2> /dev/null

set -o allexport
source $ENVIRFILE
set +o allexport
# export DBSERVER=localhost

# echo "Use http://localhost:5555/hippo/hippo.html or http://localhost:5555/stat/statistics.html"
echo "Backend running and listening to port 5555"
echo "Try http://localhost:5555/tpdb/tpdbapi.php/api/v1/version"

php -S localhost:5555 -t .

cd $CURRENTDIR

