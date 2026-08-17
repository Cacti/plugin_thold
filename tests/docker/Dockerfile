# Test runner for the Thold plugin.
#
# Pinned to PHP 8.1 because that is the oldest interpreter the CI matrix
# covers; what passes here passes on 8.2-8.4.  pcov rather than Xdebug: line
# coverage is the only debug feature the suite needs and pcov is far cheaper.
FROM php:8.1-cli-alpine@sha256:7949370448b0b4d9787776dc5968e0fd8d48763292344b5fbf21539441228a98

# git is needed by the changed-line coverage gate, which diffs against the
# base branch.
RUN apk add --no-cache git gmp-dev \
	&& docker-php-ext-install gmp \
	&& apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
	&& pecl install pcov \
	&& docker-php-ext-enable pcov \
	&& apk del .build-deps

COPY --from=composer:2@sha256:4d71c3c2109c61d5415544264b59ad4087e4c5b7244481723664138fd36d5040 /usr/bin/composer /usr/bin/composer

# The plugin lives where Cacti would put it, because thold_functions.php
# resolves its own includes through $config['base_path'] . '/plugins/thold'.
# No network or database is involved; the Cacti framework functions themselves
# are stubbed in tests/bootstrap.php.
WORKDIR /cacti/plugins/thold

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

CMD ["sh", "-c", "composer install --no-progress --no-ansi && composer test"]
