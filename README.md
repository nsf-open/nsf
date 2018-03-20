# nsf
This is the working repository for the NSF Site Redesign &amp; Content Discovery project. 

## Developing locally

We use [Docker](https://www.docker.com/) to get a local environment running
quickly. Once we have cloned this repository to a local directory and have
[installed
Docker](https://store.docker.com/search?type=edition&offering=community), we
can download PHP dependencies. We'll be using the bash-friendly scripts in
`bin`, but they wouldn't need to be modified substantially for Windows or
other environments.

```
cd path/to/nsf
bin/composer install
```

This command will start by building a Docker image with the PHP modules we
need, unless the image already exists. It will then use
[Composer](https://getcomposer.org/) to install dependencies from our
`composer.lock` file. We can ignore the warning about running as root, as the
"root" in question is the root user _within_ the container. Should we need to
add dependencies in the future, we can use `bin/composer require` as described
in Composer's [docs](https://getcomposer.org/doc/03-cli.md#require).

Next, we can start our application:

```
docker-compose up
```

This will start up the database (MySQL) and then run our bootstrap script to
install Drupal. The initial installation and configuration import will take
several minutes, but we should see status updates in the terminal.

After we see a message about `apache2 -D FOREGROUND`, we're good to go.
Navigate to [http://localhost:8080/](http://localhost:8080) and log in as the
root user (username and password are both "root").

To stop the service, press `ctrl-c` in the terminal. The next time we start
it, we'll see a similar bootstrap process, but it should be significantly
faster.

As the service runs, we can directly modify the PHP files in our app and see
our changes in near-real time.

### Other commands

Within the `bin` directory, there are a handful of helpful scripts to make
running `drupal`, `drush`, etc. within the context of our Dockerized app
easier. As noted above, they are written with bash in mind, but should be easy
to port to other environments.
