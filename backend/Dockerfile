FROM php:8.3-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure Apache to listen on port 8080 and serve /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf \
    && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:8080>/g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    FallbackResource /index.php\n\
    CGIPassAuth On\n\
</Directory>\n' > /etc/apache2/conf-available/defman.conf \
    && a2enconf defman

WORKDIR /var/www/html

# Copy application source
COPY public/ ./public/
COPY src/ ./src/
COPY bin/ ./bin/

# Configure entrypoint (normalize CRLF for Windows hosts)
COPY docker-entrypoint.sh /usr/local/bin/defman-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/defman-entrypoint.sh \
    && chmod +x /usr/local/bin/defman-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

EXPOSE 8080

ENTRYPOINT ["defman-entrypoint.sh"]
CMD ["apache2-foreground"]
