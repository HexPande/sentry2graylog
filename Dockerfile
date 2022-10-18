FROM phpswoole/swoole:php8.1-alpine

COPY . /var/www
CMD rm -rf /var/www/vendor

RUN composer i

ENTRYPOINT ["php", "/var/www/server.php"]

EXPOSE 80
