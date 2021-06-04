#!/bin/bash

docker tag rojeras/tpinfo-frontend:latest-prod docker-registry.centrera.se:443/sll-tpinfo/frontend:latest-prod
docker tag rojeras/tpinfo-backend:latest-prod docker-registry.centrera.se:443/sll-tpinfo/backend:latest-prod
docker push docker-registry.centrera.se:443/sll-tpinfo/frontend:latest-prod
docker push docker-registry.centrera.se:443/sll-tpinfo/backend:latest-prod

