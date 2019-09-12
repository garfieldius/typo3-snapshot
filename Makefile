
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
	&& php -r "if (hash_file('sha384', 'composer-setup.php') === 'a5c698ffe4b8e849a443b120cd5ba38043260d5c4023dbf93e1558871f1f07f58274fc6f4c93bcfd858c6bd0775cd8d1') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
	&& php composer-setup.php --install-dir=bin/ \
	&& php -r "unlink('composer-setup.php');"
