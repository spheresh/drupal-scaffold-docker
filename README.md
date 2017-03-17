# drupal-scaffold-docker

[![Build Status][ci-badge]][ci]

Composer plugin for automatically downloading pre-configured Docker + Docker
Compose scaffold files (like `Dockerfile`, `docker-compose.yml`, ...) when
using composer with a Drupal specific project.

Currently tested against:

* [`drupal-composer/drupal-project`][drupal-scaffold]

## Policy

* The `composer.json` file is authoritative (config is generated against)
* All docker images come from `Docker Hub` official images
* Workflow is aimed at addressing development / CI based infrastructure
* Full support / integration with PHPStorm
* Focus on simplicity
* Generated configuration is fully testable / working on Travis CI + GitLab CI
* Support for proxy / corporate firewalls

> Note: This isn't a strict policy but all images are built against Alpine
official images due to their small size, memory efficiency, and simplicity.

## Architecture

Detailed documentation explaining the architecture can be found in the
README.md file located in the `template/docker` directory (additionally part of
scaffolded files) or by clicking the link below:

- [template/docker/README.md][docker-readme]

## Constraints

It is required that the vendor directory be placed in its default location
at the project root and that the webroot directory name is given in the
composer file. `drupal-scaffold-docker` will generate a `docker` folder along
with several controller files (see `Architecture` above) in your project root.

## Usage

### Existing project

Run the following command in your composer project:

```
composer require drupal-composer-ext/drupal-scaffold-docker:8.x-dev PROJECT_NAME
```

Once `drupal-scaffold-docker` is required by your project, it will
automatically update your scaffold files whenever you issue the
`composer update` command.

> Note: Only the `docker` directory will always be recreated. The controller
files at project root won't override existing any files if they are already
present in the root directory. This is done to ensure any custom overrides
won't be overridden. For more information see the `Architecture` section above.

### Custom command

If you want to download the scaffold files manually, you have to add the
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

> Note: It is currently assumed that the scaffold files will be committed to
the repository.

[ci]:                   https://travis-ci.org/drupal-composer-ext/drupal-scaffold-docker
[ci-badge]:             https://travis-ci.org/drupal-composer-ext/drupal-scaffold-docker.svg?branch=8.x
[docker-readme]:        template/docker/README.md
[drupal-scaffold]:      https://github.com/drupal-composer/drupal-scaffold
