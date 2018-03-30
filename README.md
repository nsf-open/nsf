# nsf
This is the working repository for the NSF Site Redesign &amp; Content Discovery project. 

## Developing locally

We'll use [Git](https://git-scm.com/) to pull down and manage our code base.
There are [many](https://guides.github.com/introduction/git-handbook/)
[excellent](https://git-scm.com/book/en/v2/Getting-Started-Git-Basics)
[tutorials](http://git.huit.harvard.edu/guide/) for getting started with git,
so we'll defer to them here. We'll assume you have cloned our repository and
are now within it:

```
git clone https://github.com/18F/nsf.git
cd nsf
```

We use [Docker](https://www.docker.com/) to get a local environment running
quickly.
[Download](https://store.docker.com/search?type=edition&offering=community)
and install the runtime compatible with your system. Note that [Docker for
Windows](https://www.docker.com/docker-windows) requires Windows 10; use
[Docker Toolbox](https://docs.docker.com/toolbox/toolbox_install_windows/) on
older Windows environments. Docker will manage out PHP dependencies, get
apache running, and generally allow us to run an instance of our application
locally. We'll be using the
[bash](https://www.gnu.org/software/bash/)-friendly scripts in `bin`, but they
wouldn't need to be modified substantially for Windows or other environments.

Our first step is to run

```
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

### File storage

We've configured our file fields to store content in S3; in this way, they
persist between app restarts and deploys. Unfortunately, those configurations
are therefore also present locally, which can lead to unexpected results (we
*don't* include sensitive bucket credentials, so we'll see "The file could not
be uploaded."). If needing to work with  file uploads locally, modify the
relevant field's "storage" away from "Flysystem: S3" to "Public files" (which
means the local disk). This can be configured in the Drupal administration
interface, or by editing the configuration files in
`web/sites/default/config`. Notably, all instances of

```yaml
uri_scheme: s3
```

should become

```yaml
uri_scheme: public
```

and Docker restarted.

Alternatively, if testing S3 integration, it's possible to configure Docker to
use a real S3 bucket by editing `docker-compose.yml`. That file holds a series
of values under the "s3" key which will need to be modified with your access
credentials.

### Configuration workflow

Making configuration changes to the application comes in roughly eight small steps:
1. get the latest code
1. create a feature branch
1. make any dependency changes
1. edit the Drupal admin
1. export the configuration
1. commit the changes
1. push your branch to GitHub
1. create a pull request to be reviewed

To get the latest code, we can `fetch` it from GitHub.

```
git fetch origin
git checkout origin/master
```

alternatively:

```
git checkout master
git pull origin master
```

We then create a "feature" branch, meaning a branch of development that's
focused on adding a single feature. We'll need to name the branch something
unique, likely related to the task we're working on (perhaps including an
issue number, for example).

```
git checkout -b 333-add-the-whatsit
```

If we are installing a new module or otherwise updating our dependencies, we
next use composer. For example:

```
bin/composer require drupal/some-new-module
```

See the "Removing dependencies" section below for notes on that topic; it's a
bit different than installation/updates.

If we're making admin changes (including enabling any newly installed
modules), we'll need to start our app locally.

```sh
docker-compose down # stop any running instance
docker-compose up # start a new one with our code
```

Then navigate to [http://localhost:8080](http://localhost:8080) and log in as
the root/root. Modify whatever settings desired, which will modify them in
your local database. We'll next need to export those configurations to the
file system:

```
bin/drupal config:export
```

We're almost done! We next need to review all of the changes and commit those
that are relevant. Your git tool will have a diff viewer, but if you're using
the command line, try

```
git add -p
```

to interactively select changes to stage for the commit. Note that you may see
more config changes than expected
([#189](https://github.com/18F/nsf/issues/189)); only add the changes relevant
to your edits. Once the changes are staged, commit them, e.g. with

```
git commit -v
```

Be sure to add a descriptive commit message. Now we can send the changes to
GitHub:

```
git push origin 333-add-the-whatsit
```

And request a review in GitHub's interface.

### Content workflow

We'll also treat some pieces of content similar to configuration -- we want to
deploy it with the code base rather than add/modify it in individual
environments. The steps for this are very similar to the Config workflow:

1. get the latest code
1. create a feature branch
1. add/edit content in the Drupal admin
1. export the content
1. commit the changes
1. push your branch to GitHub
1. create a pull request to be reviewed

The first two steps are identical to the Config workflow, so we'll skip to the
third. Start the application:

```
docker-compose up
```

Then [http://localhost:8080/user/login](log in) as root (password: root).
Create or edit content (e.g. Aggregator feeds, pages, etc.) through the Drupal
admin.

Next, we'll export this content via Drush:

```sh
# Export all entities of a particular type
bin/drush default-content-deploy:export [type-of-entity e.g. aggregator_feed]
# Export individual entities
bin/drush default-content-deploy:export [type-of-entity] --entity-id=[ids e.g. 1,3,7]
```

Then, we'll review all of the changes and commit those that are relevant.
Notably, we're expecting new or modified files in `web/sites/default/content`.
After committing, we'll sent to GitHub and create a pull request as with
config changes.

### Removing dependencies

As we add modules to our site, they're rolled out via configuration
synchronization. This'll run the installation of new modules, including
setting up database tables. Unfortunately, removing modules isn't as simple as
deleting the PHP lib and deactivating the plugin. Modules and themes need to
be fully uninstalled, which will remove their content from the database and
perform other sorts of cleanup. Unfortunately, to do that, we need to have the
PHP lib around to run the cleanup.

Our solution is to have a step in our bootstrap script which uninstalls
modules/themes prior to configuration import. To do this, we'll need to keep
the PHP libs around so that the uninstallation hooks can be called. After
we're confident that the library is uninstalled in all our environments, we
can also remove it from the composer dependencies.

See the `module:uninstall` and `theme:uninstall` steps of the bootstrap script
to see how this is implemented.

### Common errors

#### There are more config file changes than I expected
See [#189](https://github.com/18F/nsf/issues/189)

#### Edits to `web/sites/default/xxx` won't go away
Drupal's installation changes the directory permissions for
`web/sites/default`, which can prevent git from modifying these files. As
we're working locally, those permissions restrictions aren't incredibly
important. We can revert them by granting ourselves "write" access again. In
unix environments, we can run

```
chmod u+w web/sites/default
```

#### Drush is missing many commands
We only recently added the necessary mysql client to the Dockerfile, so you
may need to rebuild it:

```
docker-compose build
```

### Start from scratch

As Docker is managing our environment, it's relatively easy to blow away our
database and start from scratch.

```
docker-compose down -v
```

Generally, `down` spins down the running environment but doesn't delete any
data. The `-v` flag, however, tells Docker to delete our data "volumes",
clearing away all the database files.

## Deploying code

We prefer deploying code through a continuous integration system. This ensures
reproducibility and allows us to add additional safe guards. Regardless of
environment, however, our steps for deploying code are more or less the same:
1. Install the `cf` executable and `autopilot` plugin (this can be done once)
1. Clone a *fresh* copy of the repository (this must be done every time)
1. Log into cloud.gov and target the appropriate environment
1. Send our new code up to cloud.gov for deployment

### Install cf/autopilot

Follow the cloud foundry
[instructions](https://docs.cloudfoundry.org/cf-cli/install-go-cli.html) for
installing the `cf` executable. This command-line interface is our primary
mechanism for interacting with cloud.gov.

Though it's not required, it's also a good idea to install the `autopilot`
plugin, which lets us deploy without downtime. `cf` will allow us to spin down
our old code and spin up new code in its place, which implies some downtime.
The `autopilot` plugin [goes
further](https://github.com/contraband/autopilot#method) by letting us spin up
a _second_ set of instances prior to deleting the old. Installation involves
downloading the latest version of the plugin, ensuring that binary is
executable, and telling `cf` about it. Below we have commands for a Linux
environment, but OSX and Windows would be similar:

```sh
# Get the binary
wget "https://github.com/contraband/autopilot/releases/download/0.0.3/autopilot-linux"
# Make it executable
chmod a+x autopilot-linux
# Tell cf it exists
cf install-plugin autopilot-linux
```

If performing a deployment manually (outside of CI), note that you'll only
need to install these executables once for use with all future deployments.

### Clone a fresh copy of the repo

In a continuous integration environment, we'll always check out a fresh copy
of the code base, but if deploying manually, it's import to make a new, clean
checkout of our repository to ensure we're not sending up additional files.
Notably, using `git status` to check for a clean environment is _not_ enough;
our `.gitignore` does not match the `.cfignore` so git's status output isn't a
guaranty that there are no additional files. If deploying manually, it makes
sense to create a new directory and perform the checkout within that
directory, to prevent conflicts with our local checkout.

```
git clone https://github.com/18F/nsf.git
```

As we don't need the full repository history, we could instead use an
optimized version of that checkout:

```
git clone https://github.com/18F/nsf.git --depth=1
```

We'll also want to **c**hange our **d**irectory to be inside the repository.

```
cd nsf
```

### Log into cloud.gov

We'll next need to log into cloud.gov and set our target environment. Our
target environment depends on whether we want to deploy to staging or
production -- we'll use `-s staging` or `-s prod` in the following commands.
These commands are also slightly different if we're doing this manually or in
a CI environment. For manual deployments, we'll use

```
cf login -a https://api.fr.cloud.gov --sso -o nsf-prototyping -s staging
```

This will prompt us to navigate to a single-singon url, which we'll need to
use to log in. That process will end with a unique token, which we can paste
into the terminal.

If we're instead logging in via continuous integration, we'll need a
[deployment
account](https://cloud.gov/docs/services/cloud-gov-service-account/)'s
credentials. With them, we can log in via

```
cf login -a https://api.fr.cloud.gov -u USERNAME -p PASSWORD -o nsf-prototyping -s staging
```

### Send our code

Finally, we'll send up our code based on our staging or production "manifest"
files. The recommended approach (using autopilot, as described above) is
either

```sh
cf zero-downtime-push -f manifest.yml   # staging
```

or

```sh
cf zero-downtime-push -f manifest-prod.yml  # production
```

If we're not using autopilot, we can instead use

```sh
cf push -f manifest.yml # for staging
```
or
```sh
cf push -f manifest-prod.yml # for production
```

## Notes on cloud.gov

Our preferred platform-as-a-service is [cloud.gov](https://cloud.gov/), due to
its
[FedRAMP-Authorization](https://cloud.gov/overview/security/fedramp-tracker/).
Cloud.gov runs the open source [Cloud Foundry](https://www.cloudfoundry.org/)
platform, which is very similar to [Heroku](https://www.heroku.com/). See
cloud.gov's excellent [user docs](https://cloud.gov/docs/) to get acquainted
with the system.

### Debugging

We'll assume you're already logged into cloud.gov. From there,

```
cf apps
```
will give a broad overview of the current application instances. We expect two
"web" instances and one "cronish" worker in our environments, as described in
our manifest files.

```
cf app web
```
will give us more detail about the "web" instances, specifically CPU, disk,
and memory usage.

```
cf logs web
```
will let us attach to the emitted apache logs of our running "web" instances.
If we add the `--recent` flag, we'll instead get output from our *recent* log
history (and not see new logs as they come in). We can use these logs to debug
500 errors. Be sure to look at cloud.gov's [logging
docs](https://cloud.gov/docs/apps/logs/) (particularly, how to use Kibana) for
more control.

If necessary, we can also `ssh` into running instances. This should generally
be avoided, however, as all modifications will be lost on next deploy. See the
cloud.gov [docs on the topic](https://cloud.gov/docs/apps/using-ssh/) for more
detail -- be sure to read the step about setting up the ssh environment.

```
cf ssh web
```

While the database isn't generally accessible outside the app's network, we
can access it by setting up an SSH tunnel, as described in the
[cf-service-connect](https://github.com/18F/cf-service-connect#readme) plugin.
Note that the `web` and `cronish` instances don't have a `mysql` client (aside
from PHP's PDO); sshing into them likely won't help.

Of course, there are many more useful commands. Explore the cloud.gov [user
docs](https://cloud.gov/docs/) to learn about more.

### Updating secrets

As our secrets are stored in a cloud.gov "user-provided service", to add new
ones (or rotate existing secrets), we'll need to call the
`update-user-provided-service` command. It can't be updated incrementally,
however, so we'll need to set all of the secrets (including those that remain
the same) at once.

To grab the previous versions of these values, we can run

```
cf env web
```

and look in the results for the credentials of our "secrets" service (it'll be
part of the `VCAP_SERVICES` section). Then, we update our `secrets` service
like so:

```
cf update-user-provided-service secrets -p '{"BRIGHTCOVE_ACCOUNT":"Some Value", "BRIGHTCOVE_CLIENT":"Another value", ...}'
```

### Setting up a new cloud.gov space

We shouldn't need to set up a cloud.gov space again (we have our `staging` and
`prod` environments already), but we document setup here, should it be needed
in the future. We'll assume we're already logged into cloud.gov and in the new
space.

First, we need to set up our three services (for database access, file
storage, and secrets storage). This is lightly documented in the manifest
files, but the first two commands are:

```
cf create-service aws-rds medium-mysql database
cf create-service s3 basic storage
```

Setting up the secrets storage is a bit different, as we need to specify each
secret in a JSON object:

```
cf create-user-provided-service secrets -p '{"BRIGHTCOVE_ACCOUNT": ..., ...}'
```

See the manifest files (or docker-compose.yml) for a full listing of secrets
the application is anticipating.

We will also likely want to set up a user to deploy our app from within CI.
Cloud.gov's docs on [service
accounts](https://cloud.gov/docs/services/cloud-gov-service-account/) describe
the general steps; one implementation is:

```
cf create-service cloud-gov-service-account space-deployer deployer
cf create-service-key deployer creds
cf service-key deployer creds
```

The last step gives us the deployer account's username and password.

Finally, if we need to recreate the .gov domain, we can follow the
[instructions](https://cloud.gov/docs/services/cdn-route/) on cloud.gov:

```sh
# Kick off the process
cf create-service cdn-route cdn-route beta.nsf.gov -c '{"domain": "beta.nsf.gov"}'
# Get the CNAME info
cf service beta.nsf.gov
```

We'll then need to create that CNAME record in the nsf.gov DNS.

### Updating PHP

We use the Cloud Foundry's
[Multi-buildpack](https://github.com/cloudfoundry/multi-buildpack) to allow us
to install a mysql client (essential for Drush). This also requires we specify
our PHP buildpack, which is unfortunate as it means we can't rely on the
cloud.gov folks to deploy it for us. Luckily, updating the PHP buildpack is
easy and we can check the latest version cloud.gov has tested.

First, we'll find the version number by querying cloud.gov.
```
cf buildpacks
```

The output will include a PHP buildpack with version number, e.g.
`php-buildpack-v4.3.51.zip`. This refers to the upstream (Cloud Foundry)
buildpack version, so we'll update our `multi-buildpack.yml` accordingly:

```yml
buildpacks:
  # We need the "apt" build pack to install a mysql client for drush
  - https://github.com/cloudfoundry/apt-buildpack#v0.1.1
  - https://github.com/cloudfoundry/php-buildpack#v4.3.51
```

We can also review cloud.gov's [release notes](https://cloud.gov/updates/) to
see which buildpacks have been updated, though it's not as timely.
