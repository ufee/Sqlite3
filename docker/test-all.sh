#!/usr/bin/env bash
# Run PHPUnit in docker for every supported PHP version.
# Usage: docker/test-all.sh [phpunit args...]
# PHP_SERVICES="php74 php84" docker/test-all.sh --filter SelectTest
set -u
cd "$(dirname "$0")/.."

# Git Bash on Windows: do not convert /app-like arguments to Windows paths
export MSYS_NO_PATHCONV=1

SERVICES=${PHP_SERVICES:-"php74 php80 php82 php84"}

if [ ! -f vendor/autoload.php ]; then
	docker compose run --rm -T composer install --no-interaction --no-progress || exit 1
fi

declare -A RESULTS
FAILED=0
for service in $SERVICES; do
	echo "=== $service ==="
	if docker compose run --rm -T "$service" vendor/bin/phpunit "$@"; then
		RESULTS[$service]=OK
	else
		RESULTS[$service]=FAIL
		FAILED=1
	fi
done

echo
echo "=== Summary ==="
for service in $SERVICES; do
	echo "$service: ${RESULTS[$service]}"
done
exit $FAILED
