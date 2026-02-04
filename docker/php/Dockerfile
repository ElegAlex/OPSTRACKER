# docker/php/Dockerfile
# OpsTracker - Dockerfile multi-stage optimisé pour production
FROM php:8.3-fpm-alpine AS base

# Métadonnées
LABEL maintainer="CPAM 92 - DSI"
LABEL description="OpsTracker - Gestion d'opérations IT"
LABEL version="2.2"

# Variables d'environnement
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1

# Dépendances système
RUN apk add --no-cache \
    # PostgreSQL
    postgresql-dev \
    postgresql-client \
    # Intl (requiert icu)
    icu-dev \
    icu-libs \
    # ZIP
    libzip-dev \
    zip \
    unzip \
    # Git (pour Composer)
    git \
    # Supervisor (optionnel pour workers)
    supervisor \
    # fcgi pour healthcheck
    fcgi \
    # Build tools (temporaire)
    $PHPIZE_DEPS \
    linux-headers

# Extensions PHP (seulement celles NON incluses dans l'image de base)
# Note: ctype, iconv, session, xml, simplexml, tokenizer, pdo sont DEJA inclus
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        intl \
        opcache \
        zip

# Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# APCu pour cache local
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# Nettoyage pour réduire taille image
RUN apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Configuration PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Script healthcheck
COPY docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Répertoire de travail
WORKDIR /var/www/html

# ============================================
# STAGE BUILD
# ============================================
FROM base AS builder

# Copier composer.json d'abord (cache layer)
COPY composer.json composer.lock ./
COPY symfony.lock* ./

# Installer dépendances PHP (sans scripts, sans dev)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-progress \
    --no-interaction

# Copier le reste du code
COPY . .

# Générer autoloader optimisé
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ============================================
# STAGE PRODUCTION
# ============================================
FROM base AS production

# Copier l'application buildée
COPY --from=builder /var/www/html /var/www/html

# Créer les répertoires nécessaires
RUN mkdir -p var/cache var/log var/sessions var/share uploads \
    && chown -R www-data:www-data var uploads

# Warmup du cache Symfony (en prod)
RUN php bin/console cache:warmup --env=prod --no-debug || true

# Asset Mapper - installer les assets
RUN php bin/console importmap:install --env=prod || true \
    && php bin/console asset-map:compile --env=prod || true

# Permissions
RUN chmod -R 775 var uploads

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# Utilisateur non-root
USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# ============================================
# STAGE DEVELOPMENT (optionnel)
# ============================================
FROM base AS development

# Arguments pour UID/GID dynamiques
ARG USER_ID=1000
ARG GROUP_ID=1000

# Créer utilisateur avec même UID/GID que l'hôte
RUN addgroup -g ${GROUP_ID} appgroup || true \
    && adduser -u ${USER_ID} -G appgroup -D -s /bin/sh appuser || true

# Symfony CLI pour le dev
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.alpine.sh' | sh \
    && apk add --no-cache symfony-cli

# Xdebug pour le debug
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

# Config PHP dev
RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

USER appuser

WORKDIR /var/www/html

CMD ["php-fpm"]
