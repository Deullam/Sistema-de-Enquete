#!/bin/sh
# Gera o .env que Database::loadEnv() exige, a partir das variáveis do compose,
# e então entrega o controle ao apache2-foreground da imagem oficial.
set -eu
: "${DB_HOST:?DB_HOST obrigatório}" "${DB_NAME:?DB_NAME obrigatório}" "${DB_USER:?DB_USER obrigatório}" "${DB_PASSWORD:?DB_PASSWORD obrigatório}"
printf "DB_HOST=%s\nDB_NAME=%s\nDB_USER=%s\nDB_PASSWORD=%s\n" "$DB_HOST" "$DB_NAME" "$DB_USER" "$DB_PASSWORD" > /var/www/html/.env
exec docker-php-entrypoint "$@"
