FROM php:7.2-apache

LABEL maintainer="SLL-IT suupport@sll.se"

#ENV VIRTUAL_HOST=tpinfo.se

## The following environment variables must be set to run this container. Included here just for documentation purpose.
ENV DBSERVER localhost
ENV DBUSER <database-user>
ENV DBPWD <database-pw>
ENV DBNAME <database-name>
ENV STATFILESPATH <path-to-dir-containing-stat-files>
ENV SYNONYMFILE <path-and-name-of-synonym-file>
ENV LOGDIR <path-of-log-dir>

RUN docker-php-ext-install mysqli

EXPOSE 80 443

COPY src/* /var/www/html/tpdb/



