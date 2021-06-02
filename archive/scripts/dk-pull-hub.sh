#!/bin/sh

# Stop all containers
docker stop `docker container ls -q`

# Bruatal delete of all images
docker image rm -f `docker image ls -qa`

# And download the images
docker pull rojeras/tpinfo-frontend:latest-qa
docker pull rojeras/tpinfo-backend:latest-qa
