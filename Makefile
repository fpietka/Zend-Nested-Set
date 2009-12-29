#
# NestedSet Makefile
#
# Targets:
#  - doc
#  - syntax                     Check syntax of PHP files
#  - test                       Exec unitTest
#  - php-qa                     Exec PHP Quality reports
#  - php-phpcpd                 Exec PHP Quality Duplicate source report
#  - php-phpcs                  Exec PHP Quality syntax report
#  - php-phploc                 Exec PHP Quality stats report
#  - php-phpunit                Exec PHP unitTest
#  - php-phpunit-report         Exec PHP unitTest with coverage report
#  - php-syntax                 Check syntax of PHP files
#  - php-syntax-commit          Check syntax of non commited PHP file
#  - clean                      Remove the staged files
#  - update                     Update from current GIT repository
#
# @copyright  Copyright (c) 2009 Nextcode
# @author     Francois Pietka (fpietka)
# @license	  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)

# Binary
ZIP = zip
TAR = tar
PHP = php
PHPUNIT = phpunit
PHPCS = phpcs
PHPLOC = phploc
PHPCPD = phpcpd
DOXYGEN = doxygen

# Project ID
PROJECT_NAME = Nestedset
PROJECT_VERSION = alpha
PROJECT_MAINTAINER = Francois Pietka
PROJECT_MAINTAINER_COURRIEL = francois@pietka.fr

# Path
ROOT = .
PROJECT_LIB_PATH = $(ROOT)/library
PROJECT_LOG_PATH = $(ROOT)/data/log
PROJECT_TEST_PATH = $(ROOT)/tests

# Files Finder
FIND_PHP_SRC_FULL = find $(ROOT) -type f -iname '*.php' -o -iname '*.phtml'
FIND_PHP_SRC = find $(ROOT) -type f -iname '*.php'
FIND_CLEAN_FILES = find $(ROOT) -type f \
	-iname '*.DS_Store' \
	-o -iname '*~' \
	-o -iname '*.~*' \
	-o -iname 'static-pack-*' \
	-o -iname '*.bak' \
	-o -iname '*.marks' \
	-o -iname '*.thumb' \
	-o -iname '*Thumbs.db'

# Update Env
all: clean syntax locale-deploy static-pack
	f@echo "----------------"
	@echo "Project build complete."
	@echo ""

# Generate a new Env
install: clean config syntax locale static-pack
	@echo "----------------"
	@echo "Project install complete."
	@echo ""

# Generate the doc
doc:
	@echo "----------------"
	@echo "Generate doxygen doc :"
	@$(DOXYGEN) $(PROJECT_CONFIG_PATH)/doxygen.cnf > $(PROJECT_LOG_PATH)/doc.log
	@echo "done"

#
# Alias
#
syntax:	php-syntax
test: php-phpunit
test-report: php-phpunit-report

#
# PHP
#

# Check syntax of PHP files
php-syntax:
	@echo "----------------"
	@echo "Check PHP syntax on all php files:"
	@list=`$(FIND_PHP_SRC_FULL)`; \
	for i in $$list;do \
		$(PHP) -l $$i | grep -v "No syntax errors";\
	done
	@echo "done"

# Check syntax of non commited PHP files
php-syntax-commit:
	@echo "----------------"
	@echo "Check PHP syntax on all php files updated:"
	@list=`git-diff --name-only | grep '.ph' | tr '\n' ' '`; \
	for i in $$list;do \
		$(PHP) -l $$i | grep -v "No syntax errors";\
	done
	@echo "done"

# Exec PHP unitTest
php-phpunit:
	@echo "----------------"
	@echo "Exec PHPUnits test:"
	@cd $(PROJECT_TEST_PATH) && $(PHPUNIT) --configuration phpunit.xml
	@echo "done"

# Exec PHP unitTest with coverage report
php-phpunit-report:
	@echo "----------------"
	@echo "Exec PHPUnits test coverage report:"
	@cd $(PROJECT_TEST_PATH) && $(PHPUNIT) --configuration phpunit-report.xml
	@echo "done"

# Exec PHP Quality reports
php-qa: php-phploc php-phpcs php-phpcpd

# Exec PHP Quality stats report
php-phploc:
	@echo "----------------"
	@echo "Exec PHP Code Stats report:"
	@$(PHPLOC) $(ROOT) > $(PROJECT_LOG_PATH)/php-loc.log
	@echo "done (output: $(PROJECT_LOG_PATH)/php-loc.log)"

# Exec PHP Quality syntax report
php-phpcs:
	@echo "----------------"
	@echo "Exec PHP CodeSniffer report:"
	@$(PHPCS) --extensions=php --report=full -n $(ROOT) > $(PROJECT_LOG_PATH)/php-cs.log;
	@echo "done (output: $(PROJECT_LOG_PATH)/php-cs.log)"

# Exec PHP Quality Duplicate source report
php-phpcpd:
	@echo "----------------"
	@echo "Exec PHP Code Duplicate report:"
	@$(PHPCPD) $(ROOT) > $(PROJECT_LOG_PATH)/php-cpd.log
	@echo "done (output: $(PROJECT_LOG_PATH)/php-cpd.log)"

# Remove the staged files
clean:
	@echo "----------------"
	@echo "Cleaning useless files:"
	@list=`$(FIND_CLEAN_FILES)`; \
	for i in $$list;do \
		echo "Removed $$i"; \
		rm -f $$i; \
	done
	@echo "done"

# Update from current GIT repository
update:
	@echo "----------------"
	@echo "Update from repository:"
	@git pull

.PHONY: doc clean
