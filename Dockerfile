FROM php:8.2-apache

# 1. Instala dependências do sistema e extensões PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

# 2. Copia o binário do Composer de uma imagem oficial (Multi-stage build)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Ajusta o Timezone
RUN ln -snf /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime && echo America/Sao_Paulo > /etc/timezone

# 4. Configura o Apache para a pasta public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
RUN a2enmod rewrite

# 5. Define o diretório de trabalho e copia os arquivos do projeto
WORKDIR /var/www/html
COPY . .

# 6. Roda o composer install para gerar a pasta vendor e o autoload
# Usamos --no-interaction para não travar o build
RUN composer install --no-interaction --optimize-autoloader