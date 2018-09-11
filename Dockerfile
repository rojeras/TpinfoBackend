FROM php:7.2-apache
#Added this comment to trigger autobuild of image on docker hub.
# LEO added comment to verify git functionality - can be removed
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
#RUN mkdir -p /var/www/html/tpdb/history
RUN mkdir -p /var/www/html/tpdb/history && chown www-data:www-data /var/www/html/tpdb/history

#ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf
