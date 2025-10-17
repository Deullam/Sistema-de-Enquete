FROM php:8.4-apache

# Instala extensões necessárias
RUN docker-php-ext-install pdo pdo_mysql

# Habilita mod_rewrite para .htaccess funcionar
RUN a2enmod rewrite

# Copia o código para dentro do container
COPY public/ /var/www/html/
COPY app/ /var/www/html/app/
COPY tests/ /var/www/html/tests/

# Permite que .htaccess sobescreva e libera acesso
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/z-allow-override.conf \
    && a2enconf z-allow-override

WORKDIR /var/www/html

# Inicia o Apache em primeiro plano (modo servidor)
CMD ["apache2-foreground"]
