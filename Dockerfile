FROM php:8.2-apache

# Esconde o header X-Powered-By (PHP)
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini

# Esconde a versão do Apache e o SO (Server: Apache em vez de Apache/2.4.66 Debian)
RUN sed -i 's/ServerTokens OS/ServerTokens Prod/' /etc/apache2/conf-available/security.conf && \
    sed -i 's/ServerSignature On/ServerSignature Off/' /etc/apache2/conf-available/security.conf

RUN echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/security.ini

# Instala cliente MySQL (necessário para mysqldump no backup questor)
RUN apt-get update && apt-get install -y --no-install-recommends default-mysql-client && rm -rf /var/lib/apt/lists/*

# Instala as extensões do banco de dados
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilita o módulo de reescrita de URL (necessário para o .htaccess funcionar)
RUN a2enmod rewrite

# Dependências Python são instaladas no serviço python_app (compose.yaml)

# Altera a raiz do servidor web para a pasta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html