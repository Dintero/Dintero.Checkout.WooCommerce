#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/dintero-checkout-express/bin/install-wp-tests.sh

echo "Running the tests..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/dintero-checkout-express/vendor/bin/phpunit \
	--coverage-clover .clover.xml \
	--configuration /var/www/html/wp-content/plugins/dintero-checkout-express/phpunit.xml.dist \
	$*
