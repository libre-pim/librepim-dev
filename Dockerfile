FROM httpd:2.4-bullseye AS base

LABEL maintainer="LibrePIM Contributors" \
      description="LibrePIM - Long-Term Support Fork of Akeneo PIM Community Edition" \
      version="8.2.0" \
      repository="https://github.com/libre-pim/librepim-dev"

ENV PHP_VERSION=8.3 \
    PHP_CONF_DATE_TIMEZONE=UTC \
    PHP_CONF_MEMORY_LIMIT=512M \
    PHP_CONF_MAX_EXECUTION_TIME=60 \
    PHP_CONF_OPCACHE_VALIDATE_TIMESTAMP=0 \
    PHP_CONF_MAX_INPUT_VARS=1000 \
    PHP_CONF_UPLOAD_LIMIT=40M \
    PHP_CONF_MAX_POST_SIZE=40M

RUN echo 'APT::Install-Recommends "0"; APT::Install-Suggests "0";' > /etc/apt/apt.conf.d/01-no-recommended && \
    apt-get update && \
    apt-get install -y \
      apt-transport-https \
      ca-certificates \
      curl \
      wget \
      supervisor \
      gnupg \
      lsb-release && \
    wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    echo "deb https://packages.sury.org/php/ bullseye main" > /etc/apt/sources.list.d/php.list && \
    apt-get update && \
    apt-get install -y \
      imagemagick \
      ghostscript \
      php8.3-fpm \
      php8.3-cli \
      php8.3-intl \
      php8.3-opcache \
      php8.3-mysql \
      php8.3-zip \
      php8.3-xml \
      php8.3-gd \
      php8.3-grpc \
      php8.3-curl \
      php8.3-mbstring \
      php8.3-bcmath \
      php8.3-imagick \
      php8.3-apcu \
      php8.3-exif \
      php8.3-memcached \
      openssh-client \
      aspell \
      aspell-en aspell-es aspell-de aspell-fr && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN ln -s /usr/sbin/php-fpm8.3 /usr/local/sbin/php-fpm && \
    sed -i "s#listen = .*#listen = 9000#g" /etc/php/8.3/fpm/pool.d/www.conf && \
    usermod --uid 1000 www-data && groupmod --gid 1000 www-data && \
    mkdir -p /run/php /srv/pim

COPY docker/build/akeneo.ini /etc/php/8.3/cli/conf.d/99-librepim.ini
COPY docker/build/akeneo.ini /etc/php/8.3/fpm/conf.d/99-librepim.ini

CMD ["/usr/bin/supervisord", "-c", "docker/supervisord.conf"]

# ---------- DEV IMAGE ----------
FROM base AS dev

ENV PHP_CONF_OPCACHE_VALIDATE_TIMESTAMP=1
ENV COMPOSER_MEMORY_LIMIT=4G

RUN apt-get update && apt-get install -y \
      git \
      unzip \
      procps \
      default-mysql-client \
      php8.3-xdebug && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/build/xdebug.ini /etc/php/8.3/cli/conf.d/99-librepim-xdebug.ini
COPY docker/build/xdebug.ini /etc/php/8.3/fpm/conf.d/99-librepim-xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN mkdir -p /var/www/.composer /var/www/.cache && \
    chown -R www-data:www-data /var/www

VOLUME /srv/pim