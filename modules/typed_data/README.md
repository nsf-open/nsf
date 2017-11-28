# Typed Data API module

[![Build Status](https://travis-ci.org/fago/typed_data.svg?branch=8.x-1.x)](https://travis-ci.org/fago/typed_data)

Extends the core Typed Data API with new APIs and features.

* Project homepage: https://www.drupal.org/project/typed_data
* For example usage, see the Rules module: https://www.drupal.org/project/rules

## Contributing

Always use the  [Typed Data module issue queue](https://www.drupal.org/project/issues/typed_data).

Development happens on GitHub using the pull request model:
in case you are not familiar with that, please take a few minutes to read the
[GitHub article](https://help.github.com/articles/using-pull-requests) on using
pull requests.

There are a few conventions that should be followed when contributing:

* Always create an issue in the [drupal.org Typed Data issue queue](https://www.drupal.org/project/issues/typed_data)
  for every pull request you are working on.
* Always cross-reference the Issue in the Pull Request and the Pull Request in
  the issue.
* Always create a new branch for every pull request: its name should contain a
  brief summary of the ticket and its issue id, e.g **readme-2276369**.
* Try to keep the history of your pull request as clean as possible by squashing
  your commits: you can look at the [Symfony documentation](http://symfony.com/doc/current/cmf/contributing/commits.html)
  or at the [Git book](http://git-scm.com/book/en/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages)
  for more information on how to do that.

For further information on how to contribute please refer to
[our documentation](https://thefubhy.gitbooks.io/typed_data/content/).

## Checking coding style

The module comes with a phpcs setup that is verifying a correct coding style.
To run the check just execute the following command from the rules module
directory:

    # Install phpcs as local dev dependency, then run it:
    composer install
    ./vendor/bin/phpcs

    # If there are some coding style violations that can be fixed
    # automatically, use the code beautifier:
    ./vendor/bin/phpcbf

## Executing the automated tests

This module comes with PHPUnit tests. You need a working Drupal 8 installation
and a checkout of the Rules module in the modules folder.


Make sure to use your DB connection details for the SIMPLETEST_DB and the URL to
your local Drupal installation for SIMPLETEST_BASE_URL.

    cd /path/to/drupal-8/core
    export SIMPLETEST_DB=mysql://drupal-8:password@localhost/drupal-8
    export SIMPLETEST_BASE_URL=http://drupal-8.localhost
    ../vendor/bin/phpunit ../modules/typed_data
