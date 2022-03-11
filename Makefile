include makeutil/baseConfig.mk

git:
	type $@ > /dev/null 2>&1 || ( echo $@ is not installed; exit 10 )

#
makeutil/baseConfig.mk: git
	test -f $@																					||	\
		git clone "https://phabricator.nichework.com/source/makefile-skeleton" makeutil

include $(shell echo makeutil/composer.m*k | grep \\.mk$$)

# Name of the extension under test
mwExtensionUnderTest ?= Set-mwExtensionUnderTest
mwTestFilter ?= ${mwExtensionUnderTest}
packagistUnderTest ?= $(shell test ! -f composer.json || ( jq -Mr .name < composer.json ) )
packagistVersion ?= dev-master

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

mwVer ?= 1.35
phpIni ?= ${mwCiPath}/php-settings.ini
mwCiPath ?= ${PWD}/conf
mwBranch ?= $(shell echo ${mwVer} | (echo -n REL; tr . _))
dockerCli ?= podman
miniSudo ?= podman unshare
mwImgVersion ?= docker.io/library/mediawiki:${mwVer}
memcImgVersion ?= docker.io/library/memcached:latest
mwDomain ?= localhost
mwWebPort ?= 8000
mwContainer ?= mediawiki-${mwExtensionUnderTest}
mwWebUser ?= www-data:www-data
mwDbPath ?= ${mwCiPath}/sqlite-data
mwVendor ?= ${mwCiPath}/vendor
mwAptPath ?= ${mwCiPath}/apt
mwDotComposer ?= ${mwCiPath}/dot-composer
mwExtensions ?= ${mwCiPath}/extensions
thisRepoCopy ?= ${mwExtensions}/${mwExtensionUnderTest}
contPath ?= /var/www/html
compPath ?= ${contPath}/composer
extPath ?= ${contPath}/extensions/${mwExtensionUnderTest}
importData ?= test-data/import.xml
phpunitOptions ?= --testdox

# Comma seperated list of extensions to install
installExtensions ?= Scribunto,TemplateStyles,Cargo,ParserFunctions,WikiEditor

lsPath=${mwCiPath}/LocalSettings.php
mwCompLocal=${mwCiPath}/composer.local.json

updateComposerLocal=(																				\
	require="$$(jq -c '.require | select( . != null)' < ${mwCiPath}/$(1) )"						&&	\
	test -n "$$require" || require="{}"															&&	\
	requireDev="$$(jq -c '.["require-dev"] | select( . != null)' < ${mwCiPath}/$(1) )"		&&	\
	test -n "$$requireDev" || requireDev="{}"													&&	\
	test -s ${mwCompLocal} || echo '{}' > ${mwCompLocal}										&&	\
	test ! -f ${mwCiPath}/$(1) -o "$${arg}" = "null"									||	(		\
		echo updating composer.local.json 													&&		\
		jq -Mr --argjson require "$${require}" --argjson requireDev "$${requireDev}"				\
			'. += { "require": $$require, "require-dev": $$requireDev }' ${mwCompLocal}			\
		| $(call sponge,${mwCompLocal})			)	)

getRepoUrl=https://gerrit.wikimedia.org/r/mediawiki/extensions/$(1)
getContainerID=${dockerCli} ps -f name=$(1) -f status=running -q
rmContainer=${dockerCli} rm -f $(1)
mountExists=$(if $(shell test -e $(1) -a -n "$(2)" && echo yes),-v $(1):$(2))
runMemcContainer=test -n "$(shell $(call getContainerID,$(1)))"									&&	\
			$(call getContainerID,$(1))															||	\
			${dockerCli} run --rm=true -d -p "11211:11211" --name=$(1) ${memcImgVersion}
runWebContainer=test -n "$(shell $(call getContainerID,$(1)))"									&&	\
			$(call getContainerID,$(1))															||	\
			${dockerCli} run --rm=true -d -p "${mwWebPort}:80" --name=$(1)							\
				$(call mountExists,${mwDbPath},${MW_DATA_DIR})										\
				$(call mountExists,${phpIni},/usr/local/etc/php/conf.d/mediawiki.ini)				\
				$(call mountExists,${mwVendor},${contPath}/vendor)									\
				$(call mountExists,${mwExtensions},${contPath}/extensions)							\
				$(call mountExists,${lsPath},${contPath}/LocalSettings.php)							\
				$(call mountExists,${PWD}/composer,${compPath})										\
				$(call mountExists,${mwAptPath},/var/cache/apt)										\
				$(call mountExists,${mwDotComposer},/root/.cache/composer)							\
				$(call mountExists,${mwCompLocal},${contPath}/composer.local.json)					\
				$(call mountExists,${PWD}/favicon.ico,${contPath}/favicon.ico)						\
				$(call mountExists,/tmp/temp,/temp)						\
				-e MW_WEB_PORT="${mwWebPort}"														\
				${mwImgVersion}

# Restart the wiki container
.PHONY: restartWiki
restartWiki: stopWiki startWiki

# Start the wiki
.PHONY: startWiki
startWiki: ${lsPath}
	cid=$(shell $(call getContainerID,${mwContainer}))											&&	\
	test -n "$${cid}"																	||	(		\
		echo -n "${indent}Starting up wiki: ${MW_SITE_NAME} ... "							)	&&	\
	test -n "$${cid}"																			||	\
		cid=`$(call runWebContainer,${mwContainer})`											&&	\
	echo $${cid}

# Run phpunit against the wiki
testWiki: devVendor ${lsPath}
	cid=`$(call runWebContainer,${mwContainer})`												&&	\
	${dockerCli} exec $${cid} sh -c 'test -e ${extPath}/vendor									||	\
		 ln -s ${contPath}/vendor ${extPath}/vendor'											&&	\
	${dockerCli} exec $${cid} ${php} composer 										\
		--filter ${mwTestFilter} ${phpunitOptions}												&&	\
	${dockerCli} exec $${cid} ${php} tests/phpunit/phpunit.php										\
		--filter ${mwTestFilter} ${phpunitOptions}												&&	\
	$(call rmContainer,$${cid})

# Run composer fix against the wiki
fixWiki: devVendor startWiki
	cid=$(shell $(call getContainerID,${mwContainer}))											&&	\
	${dockerCli} exec $${cid} ${php} ${compPath} -d${extPath} fix								&&	\
	$(call rmContainer,$${cid})

.PHONY: startMemcached
startMemcached:
	cid=$(shell $(call getContainerID,memcached))												&&	\
	test -n "$${cid}"																		||	(	\
		echo -n "${indent}Starting up memcached ... "											&&	\
		cid=`$(call runMemcContainer,memcached)`												&&	\
		echo $${cid}																			)

.PHONY: stopMemcached
stopMemcached:
	cid=$(shell $(call getContainerID,memcached))												&&	\
	test -z "$${cid}"																		||	(	\
		echo -n "${indent}Stopping memcached ($${cid})... "										&&	\
		$(call rmContainer,$${cid})	> /dev/null													&&	\
		echo done.																				)

# Stop the wiki
.PHONY: stopWiki
stopWiki:
	cid=$(shell $(call getContainerID,${mwContainer}))											&&	\
	test -z "$${cid}"																		||	(	\
		echo -n "${indent}Stopping wiki: ${MW_SITE_NAME} ($${cid})... "							&&	\
		$(call rmContainer,$${cid})	> /dev/null													&&	\
		echo done.																				)

# Remove the wiki
rmWiki: stopWiki rmDB
	test ! -d ${mwCiPath}																	||	(	\
		echo -n "${indent}Removing wiki: ${MW_SITE_NAME} ... "									&&	\
		${miniSudo} rm -rf ${mwCiPath}															&&	\
		echo done.																				)

# Run update.php
updatePhp: ${lsPath}
	echo "${indent}Running update.php ..."
	cid=`$(call runWebContainer,${mwContainer})`												&&	\
	${dockerCli} exec $${cid} sh -c "php maintenance/update.php --quick"						&&	\
	$(call rmContainer,$${cid})

# Run update.php
runJobs: ${lsPath}
	echo "${indent}Running jobs.php ..."
	cid=`$(call runWebContainer,${mwContainer})`												&&	\
	${dockerCli} exec $${cid} sh -c "php maintenance/runJobs.php ${args}"						&&	\
	$(call rmContainer,$${cid})

# Run composer update
composerUpdate: devVendor

LocalSettings.php: ${lsPath}

#
.PHONY: ${lsPath}
${lsPath}: ${phpIni} MW_VENDOR MW_EXTENSIONS MW_DB_PATH
	test -s "$@"																				||	\
		rm -f "$@"
	test -z "$(shell $(call getContainerID,${mwContainer}))"									||	\
		$(call rmContainer,${mwContainer})
	test -s "$@"																			||	(	\
		${make} composerUpdate																	&&	\
		echo -n ${indent}Creating LocalSettings.php for ${MW_SITE_NAME} "... "					&&	\
		cid=`$(call runWebContainer,${mwContainer})`											&&	\
		${dockerCli} exec $${cid} sh -c "php maintenance/install.php --dbtype=${MW_DB_TYPE}			\
				--dbname=${MW_DB_NAME} --pass=${MW_PASSWORD} --scriptpath=${MW_SCRIPTPATH}			\
				--dbpath=${MW_DATA_DIR} --server='http://${mwDomain}:${mwWebPort}'					\
				--extensions=${mwExtensionUnderTest},${installExtensions}							\
				 ${MW_SITE_NAME} ${MW_WIKI_USER}"												&&	\
		${dockerCli} cp $${cid}:LocalSettings.php $@											&&	\
		$(call rmContainer,$${cid})																)
	${miniSudo} chown -R ${mwWebUser} ${mwDbPath}

importData: ${lsPath}
	cid=`$(call runWebContainer,${mwContainer})`												&&	\
	baseName=`basename ${importData}`															&&	\
	${dockerCli} cp ${importData} $$cid:/$${baseName}											&&	\
	${dockerCli} exec -it $${cid} php maintenance/importDump.php /$${baseName}					&&	\
	${dockerCli} exec -it $${cid} php maintenance/rebuildrecentchanges.php

# Update vendor with dev dependencies
.PHONY: devVendor
devVendor: MW_DB_PATH MW_VENDOR MW_APT_CACHE MW_COMPOSER composer ${phpIni}
	test -f "${mwVendor}/composer/autoload_static.php"											-a	\
		-z "`find ${mwVendor}/composer/autoload_static.php -mmin +1440 -print > /dev/null`"	||	(	\
		echo ${indent}Updating vendor for today with dev deps for ${MW_SITE_NAME} ...			&&	\
		export cid=`$(call runWebContainer,${mwContainer})`										&&	\
		echo -n ${indent}- Ensuring unzip is installed for composer "... "						&&	\
		${dockerCli} exec $${cid} sh -c 'test -x /usr/bin/unzip	 		||	(						\
			apt update && apt-get install -y unzip							) > /dev/null 2>&1'	&&	\
		echo ok																					&&	\
		${dockerCli} exec $${cid} php composer update --prefer-source							&&	\
		$(call rmContainer,$${cid})																)

# Remove the DB
.PHONY: rmDB
rmDB:
	test "${MW_DB_TYPE}" = "sqlite"															||	(	\
		echo "We do not know how to remove DB of type '${MW_DB_TYPE}'"							&&	\
		exit 2																					)
	${miniSudo} rm -rf ${mwDbPath}

#
.PHONY: MW_DB_PATH
MW_DB_PATH:
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
		cid=`${dockerCli} run --rm=true -d ${mwImgVersion}`										&&	\
		${dockerCli} cp "$${cid}:vendor" ${mwVendor}/											&&	\
		rm -f ${mwVendor}/composer/autoload_static.php											&&	\
		$(call rmContainer,$${cid})																)

.PHONY: copyCoreExtensions
copyCoreExtensions:
	test -d "${mwExtensions}"															||	(		\
		export cid=`${dockerCli} run --rm=true -d ${mwImgVersion}`								&&	\
		echo -n ${indent}Copying extensions from image "... "									&&	\
		${dockerCli} cp "$${cid}:extensions" ${mwExtensions}/									&&	\
		$(call rmContainer,$${cid})																)

.PHONY: checkoutOtherExtensions
checkoutOtherExtensions:
	for ext in `echo ${installExtensions} | tr -s ', \n\t' ' '`; do								(	\
		echo ${indent}Verifying installation of the $$ext extension ...							&&	\
		export ext																				&&	\
		test -d ${mwExtensions}/$$ext													||	(		\
			git clone -b ${mwBranch} --recurse-submodules											\
				$(call getRepoUrl,$$ext) ${mwExtensions}/$$ext								)	&&	\
		test ! -f ${mwExtensions}/$$ext/composer.json											||	\
			$(call updateComposerLocal,extensions/$$ext/composer.json)							)	\
	done

.PHONY: copyThisExtension
copyThisExtension: composer
	test -d "${thisRepoCopy}"																&&	(	\
		echo ${indent}Updating checkout for ${thisRepoCopy}										&&	\
		cd ${thisRepoCopy}																		&&	\
		stashID=`date | md5sum`																	&&	\
		git stash -q																			&&	\
		git pull origin																			&&	\
		test "`git stash list | wc -l`" -eq 0													||	\
			git stash pop																			\
	)	||	(																						\
		echo ${indent}Fresh checkout for ${thisRepoCopy}										&&	\
		git clone . ${thisRepoCopy}																&&	\
		export origURL=`git remote get-url origin`												&&	\
		cd ${thisRepoCopy}																		&&	\
		git remote set-url origin $$origURL														&&	\
		git pull origin																			)
	test ! -f ${thisRepoCopy}/composer.json														||	\
			$(call updateComposerLocal,../composer.json)
	test -z "${packagistUnderTest}"															||	(	\
		COMPOSER=${mwCompLocal} ${php}																\
			composer require --no-update ${packagistUnderTest}:${packagistVersion}				)

PHONY: MW_EXTENSIONS
MW_EXTENSIONS:
	${make} copyCoreExtensions
	${make} checkoutOtherExtensions
	${make} copyThisExtension

.PHONY: MW_APT_CACHE
MW_APT_CACHE:
	test -d "${mwAptPath}"																		||	\
		mkdir -p ${mwAptPath}

.PHONY: MW_COMPOSER
MW_COMPOSER:
	test -d "${mwDotComposer}"																	||	\
		mkdir -p ${mwDotComposer}

${phpIni}: MW_CI_PATH
	test -z "$@" -o -f "$@"															||	(			\
		echo '[PHP]'																			&&	\
		echo 'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE'			)	>	$@
