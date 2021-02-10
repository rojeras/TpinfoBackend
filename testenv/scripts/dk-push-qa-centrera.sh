#!/bin/bash

docker tag rojeras/tpinfo-frontend:latest-qa docker-registry.centrera.se:443/sll-tpinfo/frontend:latest-qa
docker tag rojeras/tpinfo-backend:latest-qa docker-registry.centrera.se:443/sll-tpinfo/backend:latest-qa
docker push docker-registry.centrera.se:443/sll-tpinfo/frontend:latest-qa
docker push docker-registry.centrera.se:443/sll-tpinfo/backend:latest-qa

