NAME := {{__ORG__}}/{{__REPO__}}
VERSION := $(or $(VERSION),$(VERSION),'latest')
PLATFORM := $(shell uname -s)

all: base

build: all

behat:
	./docker/bin/behat -c behat.yml --colors

clean:
	rm -rf {{{__DOCROOT__}},vendor}
	rm -f composer.lock
	composer clear-cache

clean_docker:
	docker rm $$(docker ps --all -q -f status=exited)

drupal_cs:
	cp docker/conf/phpcs.xml {{__DOCROOT__}}/core/phpcs.xml
	cp docker/conf/phpunit.xml {{__DOCROOT__}}/core/phpunit.xml

base:
	docker build -f docker/Dockerfile \
               -t $(NAME):$(VERSION) \
               --no-cache \
               --build-arg http_proxy=$$HTTP_PROXY \
               --build-arg HTTP_PROXY=$$HTTP_PROXY \
               --build-arg https_proxy=$$HTTP_PROXY \
               --build-arg HTTPS_PROXY=$$HTTP_PROXY .

drupal_install:
	docker exec {{__REPO_SHORT__}}_web bash /var/www/docker/bin/cli drupal-first-run {{__PROFILE__}}

drupal_migrate:
	docker exec {{__REPO_SHORT__}}_web bash /var/www/docker/bin/cli drupal-migrate {{__PROFILE__}}

drush_archive:
	./docker/bin/drush archive-dump --destination="/var/www/files_private/drupal$$(date +%Y%m%d_%H%M%S).tgz" \
                                  --generator="Drupal"

env:
	eval $$(docker-machine env default)

lint:
	./docker/bin/lint

# http://stackoverflow.com/questions/4219255/how-do-you-get-the-list-of-targets-in-a-makefile
list:
	@$(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$' | xargs

phpcs: drupal_cs
	./docker/bin/phpcs --config-set installed_paths /var/www/vendor/drupal/coder/coder_sniffer

	./docker/bin/phpcs --standard=/var/www/{{__DOCROOT__}}/core/phpcs.xml \
              --extensions=php,module,inc,install,test,profile,theme \
              --report=full \
              --colors \
              /var/www/{{__DOCROOT__}}/modules/custom \
              /var/www/{{__DOCROOT__}}/themes/custom

phpunit:
	./docker/bin/phpunit --colors=always \
                --testsuite=kernel \
                --group {{__PROFILE__}}

	./docker/bin/phpunit --colors=always \
                --testsuite=unit \
                --group {{__PROFILE__}}

test: lint phpcs phpunit behat

up:
	docker-machine start default
	eval $$(docker-machine env default)
	docker-compose up -d

update: base
	git pull origin 8.x
	composer update
	docker-compose build --no-cache
	docker-compose up -d

release: tag_latest
	@if ! docker images $(NAME) | awk '{ print $$2 }' | grep -q -F $(VERSION); then echo "$(NAME) version $(VERSION) is not yet built. Please run 'make build'"; false; fi
	docker push $(NAME)
	@echo "*** Don't forget to create a tag. git tag rel-$(VERSION) && git push origin rel-$(VERSION)"

tag_latest:
	docker tag -f $(NAME):$(VERSION) $(NAME):latest

.PHONY: \
	all \
	base \
	behat \
	build \
	clean \
	drupal_cs \
	drupal_install \
	drupal_migrate \
	drush_archive \
	env \
	lint \
	list \
	phpcs \
	phpunit \
	release \
	tag_latest \
	test \
	up \
	update
