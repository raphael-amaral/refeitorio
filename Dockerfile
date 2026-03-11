FROM php:8.2-apache

# Instala as extensões do banco de dados
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilita o módulo de reescrita de URL (necessário para o .htaccess funcionar)
RUN a2enmod rewrite

# Altera a raiz do servidor web para a pasta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html