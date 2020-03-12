#!/bin/sh

SPEC=swagger php -S 127.0.0.1:8080 tests/rest/app.php &
trap "kill $?" EXIT

SPEC=openapi php -S 127.0.0.1:8081 tests/rest/app.php &
trap "kill $?" EXIT

vendor/bin/phpunit "$@"

