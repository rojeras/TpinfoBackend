#!/bin/bash

TOPDIR=/home/leo/Documents/data/Eternal/development/tpinfo
ENVIRFILE=$TOPDIR/backend/bin/backend-envir.lst

cd $TOPDIR
mkdir public_html 2> /dev/null
cd public_html

rm tpdb
ln -s ../backend/src tpdb 2> /dev/null
ln -s /tmp/history history 2> /dev/null
ln -s ../../statapicache/active statapifiles 2> /dev/null

set -o allexport
source $ENVIRFILE
set +o allexport
# export DBSERVER=localhost

echo "Use http://localhost:5555/hippo/hippo.html or http://localhost:5555/stat/statistics.html"
echo

php -S localhost:5555 -t .


