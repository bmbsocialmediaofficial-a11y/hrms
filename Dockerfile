# Use official PHP with Apache
FROM php:8.2-apache

# Enable commonly used PHP extensions (you can add more if needed)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy your PHP files into Apache's root directory
COPY . /var/www/html/

# Give Apache permission
RUN chown -R www-data:www-data /var/www/html

# Expose HTTP port
EXPOSE 80
