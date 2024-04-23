help:                                                                           ## shows this help
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_\-\.]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

vendor: composer.lock
	composer install

.PHONY: phpcs-check
phpcs-check: vendor                                                             ## run phpcs
	vendor/bin/phpcs

.PHONY: phpcs-fix
cs: vendor                                                                      ## run phpcs fixer
	vendor/bin/phpcbf

.PHONY: phpstan
phpstan: vendor                                                                 ## run phpstan static code analyser
	vendor/bin/phpstan analyse

.PHONY: phpstan-baseline
phpstan-baseline: vendor                                                        ## run phpstan static code analyser
	vendor/bin/phpstan analyse --generate-baseline

.PHONY: psalm
psalm: vendor                                                                   ## run psalm static code analyser
	vendor/bin/psalm

.PHONY: psalm-baseline
psalm-baseline: vendor                                                          ## run psalm static code analyser
	vendor/bin/psalm --update-baseline --set-baseline=baseline.xml


.PHONY: phpunit
phpunit: vendor                                                                 ## run phpunit tests
	XDEBUG_MODE=coverage vendor/bin/phpunit

.PHONY: static
static: psalm phpstan phpcs-check                                               ## run static analyser

test: phpunit                                                                   ## run tests

.PHONY: dev
dev: static test                                                                ## run dev tools

.PHONY: docs
docs: mkdocs                                                                          ## run mkdocs
	cd docs && python3 -m mkdocs serve

.PHONY: mkdocs
mkdocs:                                                                         ## run mkdocs
	cd docs && pip3 install -r requirements.txt

.PHONY: docs-extract-php
docs-extract-php:
	bin/docs-extract-php-code

.PHONY: docs-inject-php
docs-inject-php:
	bin/docs-inject-php-code

.PHONY: docs-format
docs-format: docs-phpcs docs-inject-php

.PHONY: docs-php-lint
docs-php-lint: docs-extract-php
	php -l docs_php/*.php

.PHONY: docs-phpcs
docs-phpcs: docs-extract-php
	vendor/bin/phpcbf docs_php --exclude=SlevomatCodingStandard.TypeHints.DeclareStrictTypes || true

.PHONY: docs-psalm
docs-psalm: docs-extract-php
	vendor/bin/psalm --config=psalm_docs.xml
