FROM alpine:edge

RUN apk add --no-cache --virtual=build g++ autoconf util-linux-dev make php7-pear php7-dev postgresql-dev \
    && apk add --no-cache libuuid libpq php7-json \
    && pecl install uuid && echo extension=uuid.so > /etc/php7/conf.d/60_uuid.ini \
    && pecl install raphf && echo extension=raphf.so > /etc/php7/conf.d/50_raphf.ini \
    && pecl install pq && echo extension=pq.so > /etc/php7/conf.d/90_pq.ini \
    && apk del build

RUN apk add --no-cache php7

RUN echo display_errors=stderr >> /etc/php7/php.ini
RUN echo error_reporting=E_ALL >> /etc/php7/php.ini
