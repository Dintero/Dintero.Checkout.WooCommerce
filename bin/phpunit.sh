#!/usr/bin/env bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

if [ -f "phpunit.phar" ]; then php phpunit.phar --coverage-clover ../clover.xml -c phpunit.xml.dist; else ./vendor/bin/phpunit --coverage-clover ../clover.xml; fi;
pwd
