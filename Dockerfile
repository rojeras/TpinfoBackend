FROM php:7.2-apache
#Added this comment to trigger autobuild of image on docker hub.
# LEO added comment to verify git functionality - can be removed
LABEL maintainer="SLL-IT suupport@sll.se"
LABEL layer="backend"

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

COPY src/leolib.php /var/www/html/tpdb/
COPY src/leolib_sql.php /var/www/html/tpdb/
COPY src/LICENSE /var/www/html/tpdb/
COPY src/loadsynonyms.php /var/www/html/tpdb/
COPY src/mkstathistory.php /var/www/html/tpdb/
COPY README.md /var/www/html/tpdb/
COPY src/tpdbapi.php /var/www/html/tpdb/
COPY src/tpdbupdate.php /var/www/html/tpdb/
COPY src/versionInfo.json /var/www/html/tpdb/
RUN mkdir -p /var/www/html/tpdb/history && chown www-data:www-data /var/www/html/tpdb/history

