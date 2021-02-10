#/bin/sh

echo
echo --- All running containers ---
echo
docker ps

echo 
echo --- Existing containers ---
echo
docker container ls -a

echo
echo --- Existing images ---
echo
docker image ls -a

echo
echo --- Stop and delete everything ---
echo 
docker stop `docker ps -q`
docker container rm `docker container ls -qa`
docker image rm -f `docker image ls -qa`
