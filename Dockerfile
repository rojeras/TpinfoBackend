FROM php:7.2-apache
# LEO added comment to verify git functionality - can be removed
LABEL maintainer="SLL-IT suupport@sll.se"

#ENV VIRTUAL_HOST=tpinfo.se

## The following environment variables must be set to run this container. Included here just for documentation purpose.
ENV DBSERVER localhost
ENV DBUSER database-user
ENV DBPWD database-pw
ENV DBNAME database-name
ENV STATFILESPATH path-to-dir-containing-stat-files
ENV SYNONYMFILE path-and-name-of-synonym-file

RUN docker-php-ext-install mysqli

#RUN apt-get update && apt-get install -y python

EXPOSE 80 443

COPY src/* /var/www/html/tpdb/

#ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf

