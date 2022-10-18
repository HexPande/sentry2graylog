FROM phpswoole/swoole:php8.1-alpine

COPY ./composer.json /build/composer.json
COPY ./composer.lock /build/composer.lock
RUN cd /build && \
    composer i

COPY . /var/www
RUN rm -rf /var/www/vendor && \
    mv /build/vendor /var/www/vendor && \
    composer du

ENTRYPOINT ["php", "/var/www/server.php"]

EXPOSE 80
