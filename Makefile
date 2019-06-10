
.PHONY: build
build: vendor/autoload.php

.PHONY: clean
clean:
	@rm -f vendor/autoload.php .php_cs.cache

.PHONY: clobber
clobber: clean
	@rm -rf vendor bin

.PHONY: fix
fix: vendor/autoload.php
	@php bin/php-cs-fixer fix --config=.php_cs -vvv

.PHONY: test
test: vendor/autoload.php
	@php bin/phpunit -c phpunit.xml
	@php bin/php-cs-fixer fix --config=.php_cs -vvv --dry-run

vendor/autoload.php: bin/composer.phar
	@php bin/composer.phar install

bin/composer.phar:
	@php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php -r "if (hash_file('sha384', 'composer-setup.php') === '48e3236262b34d30969dca3c37281b3b4bbe3221bda826ac6a9a62d6444cdb0dcd0615698a5cbe587c3f0fe57a54d8f5') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
	&& php composer-setup.php --install-dir=bin/ \
	&& php -r "unlink('composer-setup.php');"
