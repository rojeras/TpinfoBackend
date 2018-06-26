FROM php:7.2-apache
#Added this comment to trigger autobuild of image on docker hub.
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
ENV LOGDIR path-of-log-dir

RUN docker-php-ext-install mysqli

#RUN apt-get update && apt-get install -y python

EXPOSE 80 443

COPY src/* /var/www/html/tpdb/

# Add crontab file in the cron directory
RUN apt-get update && apt-get -y install cron
COPY build/tpdbupdate-crontab /etc/cron.d/tpdbupdate-crontab
RUN chmod 0644 /etc/cron.d/tpdbupdate-crontab
RUN touch /var/log/cron.log
#CMD cron && tail -f /var/log/cron.log
RUN service cron start

#ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf
