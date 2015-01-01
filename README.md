[![Build Status](https://scrutinizer-ci.com/g/fpietka/Zend-Nested-Set/badges/build.png?b=master)](https://scrutinizer-ci.com/g/fpietka/Zend-Nested-Set/build-status/master) [![Code Coverage](https://scrutinizer-ci.com/g/fpietka/Zend-Nested-Set/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/fpietka/Zend-Nested-Set/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fpietka/Zend-Nested-Set/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fpietka/Zend-Nested-Set/?branch=master)

INSTALLATION
=======================================================================

The minimum requirement is that your Web server supports PHP 5.4.

This application requires that you either have Zend Framework on your
include_path, or that you will be symlinking your Zend Framework library
into the library directory. If you do not yet have Zend Framework, you
can get it from one of the following sources:

  * Official Release:
http://framework.zend.com/downloads/latest#ZF1

  * Using Subversion; use either the current trunk or the 1.12.7 release branch:
<pre>
svn checkout http://framework.zend.com/svn/framework/standard/trunk/library/Zend
svn checkout http://framework.zend.com/svn/framework/standard/branches/release-1.12/library/Zend
</pre>

  * Using Git; use either the current master or the 1.12.7 release branch:
<pre>
git clone https://github.com/zendframework/zf1
git checkout release-1.12.7
</pre>

DEPENDENCY
=======================================================================

* PHP Package
<pre>php5 php-pear php5-sqlite php5-curl php5-xmlrpc php5-json</pre>

* PHAR Package
<pre>wget https://phar.phpunit.de/phpunit.phar</pre>

DOCUMENTATION
======================================================================

To lauch unit tests:
    php mageekguy.atoum.phar -d tests/

REQUESTS
=======================================================================
If you have any feature requests, feel free to send them to:

    François Pietka
    francois [at] pietka [dot] fr

I may or may not honor them. :)

LICENSE
=======================================================================
Please see COPYING
