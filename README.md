# drupal-scaffold-docker

[![Build Status][ci-badge]][ci]

Composer plugin for automatically downloading and instantiating a Docker based
Drupal infrastructure.

Detailed documentation explaining this workflow can be found in the README.md
file located in the `template/docker` directory or by clicking the link below:

- [template/docker/README.md][docker-readme]

> Note: Currently there are some hard constraints in order for the generated
docker scaffold files to work with a `composer.json` file.

It is required that the vendor directory be placed in its default location
at the project root and that the webroot directory name is in the composer file.
`drupal-scaffold-docker` will generate a `docker` folder along with several
controller files (see detailed documentation) in your project root. Currently
the package will only install whenever the `drupal/core` package is installed
and / or updated.

## Usage

### Existing project

Run the following command in your composer project before installing or
updating `drupal/core`.

```
composer require drupalwxt/drupal-scaffold-docker:dev-master PROJECT_NAME
cd PROJECT_NAME
```

Once `drupal-scaffold-docker` is required by your project, it will
automatically update your scaffold files whenever `composer update` changes the
version of `drupal/core` installed.

Note: Only the `docker` directory will always be recreated. The controller
files at project root won't override existing any files if they are already
present in the root directory.

### Custom command

The plugin by default is only downloading the scaffold files when installing or
updating `drupal/core`. If you want to call it manually, you have to add the
command callback to the `scripts` section of your root `composer.json` file:

```json
{
  "scripts": {
    "drupal-scaffold-docker": "DrupalComposer\\DrupalScaffoldDocker\\Plugin::scaffold"
  }
}
```

After that you can manually download the scaffold files according to your
configuration by using `composer drupal-scaffold-docker`.

It is assumed that the scaffold files will be committed to the repository, to
ensure that the correct files are leveraged on a CI server. After running
`composer install` for the first time commit the scaffold files to your
repository.


[ci]:                   https://travis-ci.org/drupalwxt/drupal-scaffold-docker
[ci-badge]:             https://travis-ci.org/drupalwxt/drupal-scaffold-docker.svg?branch=master
[docker-readme]:        template/docker/README.md
