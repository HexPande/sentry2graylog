FROM phpswoole/swoole:php8.1-alpine

COPY ./composer.json /build/composer.json
COPY ./composer.lock /build/composer.lock
RUN cd /build && composer i

COPY . /var/www
RUN rm -rf /var/www/vendor && \
    mv /build/vendor /var/www/vendor

RUN cd /var/www && composer du

ENTRYPOINT ["php", "server.php"]

EXPOSE 80
