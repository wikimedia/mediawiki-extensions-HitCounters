include makeutil/baseConfig.mk

git:
	type $@ > /dev/null 2>&1 || ( echo $@ is not installed; exit 10 )

#
makeutil/baseConfig.mk: git
	test -f $@																					||	\
		git clone "https://phabricator.nichework.com/source/makefile-skeleton" makeutil

include $(shell echo makeutil/composer.m*k | grep \\.mk$$)

# Name of the extension under test
mwExtensionUnderTest ?= $(basename ${PWD})

#
MW_DB_TYPE ?= sqlite
MW_DB_NAME ?= my_wiki
MW_DATA_DIR ?= /var/www/data
MW_PASSWORD ?= ugly123456

# Name of the wiki
MW_SITE_NAME ?= ${mwExtensionUnderTest}

#
MW_WIKI_USER ?= WikiSysop
MW_SCRIPTPATH ?= ""

phpIni ?= ${mwCiPath}/php-settings.ini
mwCiPath ?= ${PWD}/ci
dockerCli ?= podman
miniSudo ?= podman unshare
mwImgVersion ?= docker.io/library/mediawiki:latest
mwDomain ?= localhost
mwWebPort ?= 8000
mwContainer ?= mediawiki
mwWebUser ?= www-data:www-data
mwDbPath ?= ${mwCiPath}/sqlite-data
mwVendor ?= ${mwCiPath}/vendor
mwAptPath ?= ${mwCiPath}/apt
mwDotComposer ?= ${mwCiPath}/dot-composer
contPath ?= /var/www/html

lsPath=${mwCiPath}/LocalSettings.php

getContainerID=${dockerCli} ps -f name=$(1) -f status=running -q
rmContainer=${dockerCli} rm -f $(1)

# Start the wiki
.PHONY: startWiki
startWiki: ${lsPath}
	cid=$(shell $(call getContainerID,${mwContainer}))											&&	\
	test -n "$${cid}"																	||	(		\
		echo -n "${indent}Starting up wiki: ${MW_SITE_NAME} ... "							)	&&	\
	test -n "$${cid}"																			||	\
		cid=`${dockerCli} run --rm=true -d -p "${mwWebPort}:80" --name=${mwContainer}				\
				-v "${mwDbPath}:${MW_DATA_DIR}"														\
				-v "${mwVendor}:${contPath}/vendor"													\
				-v "${PWD}:${contPath}/extensions/${mwExtensionUnderTest}"							\
				-v "${lsPath}:${contPath}/LocalSettings.php"										\
				-e MW_WEB_PORT="${mwWebPort}"														\
				${mwImgVersion}`																&&	\
	echo ${cid}

# Run phpunit against the wiki
testWiki: devVendor startWiki
	cid=$(shell $(call getContainerID,${mwContainer}))											&&	\
	${dockerCli} exec -it $${cid} ${php} tests/phpunit/phpunit.php									\
		--filter ${mwExtensionUnderTest} --testdox												&&	\
	$(call rmContainer,$${cid})

# Stop the wiki
.PHONY: stopWiki
stopWiki:
	cid=$(shell $(call getContainerID,${mwContainer}))											&&	\
	test -z "$${cid}"																		||	(	\
		echo -n "${indent}Stopping wiki: ${MW_SITE_NAME} ($${cid})... "							&&	\
		$(call rmContainer,$${cid})																&&	\
		echo done.																				)

# Remove the wiki
rmWiki: stopWiki rmDB
	test ! -d ${mwCiPath}																	||	(	\
		echo -n "${indent}Removing wiki: ${MW_SITE_NAME} ... "									&&	\
		${miniSudo} rm -rf ${mwCiPath}															)

# Run update.php
updatePhp: ${lsPath}
	echo "${indent}Running update.php ..."
	lsc=`${dockerCli} run --rm=true -d																\
		-v "${mwDbPath}:${MW_DATA_DIR}"																\
		-v "${PWD}:${contPath}/extensions/${mwExtensionUnderTest}"									\
		-v "${phpIni}:/usr/local/etc/php/conf.d/mediawiki.ini"										\
		-v "${lsPath}:${contPath}/LocalSettings.php"												\
		-v "${mwVendor}:${contPath}/vendor"  ${mwImgVersion}`									&&	\
	${dockerCli} exec $${lsc} sh -c "php maintenance/update.php --quick"						&&	\
	$(call rmContainer,$${lsc})

# Run update.php
runJobs: ${lsPath}
	echo "${indent}Running jobs.php ..."
	lsc=`${dockerCli} run --rm=true -d																\
		-v "${mwDbPath}:${MW_DATA_DIR}"																\
		-v "${PWD}:${contPath}/extensions/${mwExtensionUnderTest}"									\
		-v "${phpIni}:/usr/local/etc/php/conf.d/mediawiki.ini"										\
		-v "${lsPath}:${contPath}/LocalSettings.php"												\
		-v "${mwVendor}:${contPath}/vendor"  ${mwImgVersion}`									&&	\
	${dockerCli} exec $${lsc} sh -c "php maintenance/runJobs.php ${args}"						&&	\
	$(call rmContainer,$${lsc})

#
${lsPath}: ${phpIni} MW_DB_PATH MW_VENDOR
	test -f "$@"																			||	(	\
		echo -n "${indent}Creating LocalSettings.php for ${MW_SITE_NAME} ... "					&&	\
		lsc=`${dockerCli} run --rm=true -d															\
			-v "${mwDbPath}:${MW_DATA_DIR}"															\
			-v "${PWD}:${contPath}/extensions/${mwExtensionUnderTest}"								\
			-v "${phpIni}:/usr/local/etc/php/conf.d/mediawiki.ini"									\
			-v "${mwVendor}:${contPath}/vendor"  ${mwImgVersion}`								&&	\
		${dockerCli} exec $${lsc} sh -c "php maintenance/install.php --dbtype=${MW_DB_TYPE}			\
				--dbname=${MW_DB_NAME} --pass=${MW_PASSWORD} --scriptpath=${MW_SCRIPTPATH}			\
				--dbpath=${MW_DATA_DIR} --server=\"http://${mwDomain}:${mwWebPort}\"				\
				--extensions=${mwExtensionUnderTest}												\
				${MW_SITE_NAME} ${MW_WIKI_USER}; cat LocalSettings.php"								\
		| awk '/<\?php/,0' > $@																	&&	\
		$(call rmContainer,$${lsc})																)
	${miniSudo} chown -R ${mwWebUser} ${mwDbPath}

# Update vendor with dev dependencies
.PHONY: devVendor
devVendor: MW_VENDOR MW_APT_CACHE MW_COMPOSER composer
	test -z "`find ${mwVendor}/composer/autoload_static.php -mmin +1440 -print`"			||	(	\
		echo "${indent}Updating vendor for today with dev deps for ${MW_SITE_NAME} ... "		&&	\
		vsc=`${dockerCli} run --rm=true -d															\
			-v "${mwVendor}:${contPath}/vendor"														\
			-v "${mwDotComposer}:/root/.cache/composer"												\
			-v "${mwAptPath}:/var/cache/apt" ${mwImgVersion}`									&&	\
		${dockerCli} cp composer "$${vsc}:${contPath}"											&&	\
		echo -n "${indent}- Installing zip for composer ... "									&&	\
		${dockerCli} exec $${vsc} sh -c 'test -x /usr/bin/unzip		 	||	(						\
			apt update && apt-get install -y unzip							) > /dev/null 2>&1'	&&	\
		echo ok																					&&	\
		echo "${indent}- Running composer update ... "											&&	\
		${dockerCli} exec $${vsc} ./composer update --prefer-source								&&	\
		$(call rmContainer,$${vsc})																)

# Remove the DB
.PHONY: rmDB
rmDB:
	test "${MW_DB_TYPE}" = "sqlite"															||	(	\
		echo "We do not know how to remove DB of type '${MW_DB_TYPE}'"							&&	\
		exit 2																					)
	${miniSudo} rm -rf ${mwDbPath}

#
.PHONY: MW_DB_PATH
MW_DB_PATH: MW_CI_PATH
	test -z "${mwDbPath}" -o -d "${mwDbPath}"													||	\
		mkdir -p ${mwDbPath}

.PHONY: MW_CI_PATH
MW_CI_PATH:
	test -d "${mwCiPath}"																		||	\
		mkdir -p ${mwCiPath}

.PHONY: MW_VENDOR
MW_VENDOR:
	test -d "${mwVendor}"																	||	(	\
		echo -n "${indent}Copying vendor from image ... "										&&	\
		vid=`${dockerCli} run --rm=true -d ${mwImgVersion}`										&&	\
		${dockerCli} cp "$${vid}:vendor" ${mwVendor}/											&&	\
		$(call rmContainer,$${vid})																&&	\
		echo done.																				)

.PHONY: MW_APT_CACHE
MW_APT_CACHE:
	test -d "${mwAptPath}"																		||	\
		mkdir -p ${mwAptPath}

.PHONY: MW_COMPOSER
MW_COMPOSER:
	test -d "${mwDotComposer}"																	||	\
		mkdir -p ${mwDotComposer}

${phpIni}: MW_CI_PATH
	test -z "$@" -o -f "$@"																	||	(	\
		echo '[PHP]'																			&&	\
		echo 'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE'						\
	) > $@
