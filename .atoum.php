<?php

use mageekguy\atoum;

// because of SQLite, we avoid concurrency
$runner->setMaxChildrenNumber(1);

$script->addDefaultReport();

$cloverWriter = new atoum\writers\file('./build/logs/clover.xml');
$cloverReport = new atoum\reports\asynchronous\clover();
$cloverReport->addWriter($cloverWriter);

$runner->addReport($cloverReport);
