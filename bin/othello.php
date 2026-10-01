#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

exit((new Othello\Console())->run(array_slice($argv, 1)));
