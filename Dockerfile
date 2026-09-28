FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && printf '<Directory /var/www/html/public>\n  FallbackResource /index.php\n  Require all granted\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
 && a2enconf app \
 && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/app.ini
COPY . /var/www/html
RUN chmod +x /var/www/html/start.sh
CMD ["sh", "/var/www/html/start.sh"]
