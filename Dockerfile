FROM php:8.2-apache

# Install ekstensi PHP untuk database
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Aktifkan fitur rewrite apache
RUN a2enmod rewrite

# Copy file project ke dalam container
COPY . /var/www/html/

# Atur izin folder cache
RUN chown -R www-data:www-data /var/www/html/cache \
    && chmod -R 755 /var/www/html/cache