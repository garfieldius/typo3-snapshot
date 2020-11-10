
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
	@php -r "@mkdir('bin');" \
	&& php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php -r "if (hash_file('sha384', 'composer-setup.php') === 'c31c1e292ad7be5f49291169c0ac8f683499edddcfd4e42232982d0fd193004208a58ff6f353fde0012d35fdd72bc394') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
	&& php composer-setup.php --install-dir=bin/ \
	&& php -r "unlink('composer-setup.php');"
