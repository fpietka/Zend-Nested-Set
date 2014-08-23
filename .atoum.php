<?php

use mageekguy\atoum;

// because of SQLite, we avoid concurrency
$runner->setMaxChildrenNumber(1);
