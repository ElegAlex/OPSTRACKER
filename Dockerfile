FROM php:8.3-fpm

# Arguments
ARG USER_ID=1000
ARG GROUP_ID=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash \
    && apt-get install -y symfony-cli

# Create user with same UID/GID as host
RUN groupadd -g ${GROUP_ID} appgroup || true \
    && useradd -u ${USER_ID} -g ${GROUP_ID} -m appuser || true

# Set working directory
WORKDIR /var/www/html

# Configure PHP
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/docker.ini \
    && echo "upload_max_filesize=50M" >> /usr/local/etc/php/conf.d/docker.ini \
    && echo "post_max_size=50M" >> /usr/local/etc/php/conf.d/docker.ini

USER appuser

# Configure git for appuser
RUN git config --global user.email "opstracker@example.com" \
    && git config --global user.name "OpsTracker"
