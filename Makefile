PHPCS_ARGS ?= \
	-s \
	--standard=phpcs.ruleset.xml \
	--extensions=php \
	-w dintero-hp.php includes/ \
	--ignore-annotations

lint:
	./vendor/bin/phpcs ${PHPCS_ARGS}

lint-fix:
	./vendor/bin/phpcbf ${PHPCS_ARGS}

test:
	./bin/run-tests.sh
