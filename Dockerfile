FROM php:7.2-apache

LABEL maintainer="SLL-IT suupport@sll.se"

#ENV VIRTUAL_HOST=tpinfo.se

#ENV DBUSER user
#ENV DBPASS pw

RUN docker-php-ext-install mysqli

RUN apt-get update && apt-get install -y python

EXPOSE 80 443

COPY public_html /var/www/html/

#ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf
