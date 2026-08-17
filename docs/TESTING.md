# Testing and contributing

Thold uses Pest for unit tests and Cacti's Composer toolchain for dependencies.
Keeping one toolchain avoids a second dependency graph inside the plugin and
ensures local tests exercise the same versions used by Cacti.

## Repository policy

- Write new unit and regression tests as Pest tests under `tests/Unit`.
- Do not add a plugin-local `composer.json`, `composer.lock`, or `vendor`
  directory.
- Keep `phpunit.xml`. Pest reads this compatibility configuration for bootstrap,
  suite, and coverage settings; its filename does not mean PHPUnit is run
  directly.
- Run the complete unit suite before opening or updating a pull request.

## Run the tests locally

Place the plugin at `plugins/thold` in a Cacti checkout whose Composer
dependencies are installed, then run from the Cacti root:

```console
composer test -- --configuration=plugins/thold/phpunit.xml plugins/thold/tests/Unit
```

To run one test file while developing:

```console
composer test -- --configuration=plugins/thold/phpunit.xml plugins/thold/tests/Unit/TholdRpnCdefTest.php
```

The pull request workflow is the reproducible reference environment. It builds
Cacti's pinned Docker test image, mounts Thold into a pinned Cacti runtime, runs
PHP linting, runs Pest with coverage, and requires full coverage of lines added
by a pull request. Review `.github/workflows/pest-tests.yml` when reproducing
the exact CI commands or pinned Cacti revisions.

## Pull request checklist

Before pushing a branch:

1. Run the Pest suite and any focused tests for the changed behavior.
2. Confirm every changed PHP file passes the Cacti Composer lint script.
3. Confirm `composer.json`, `composer.lock`, `vendor`, and generated coverage
   output are not included in the diff.
4. Rebase or update the branch against the current target branch and resolve
   conflicts before requesting review.
