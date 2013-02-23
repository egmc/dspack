<?php
require __DIR__ .'/vendor/autoload.php';

use Symfony\Component\Yaml\Parser;
use DsPack\DsPack;

(new DsPack((new Parser())->parse(file_get_contents(__DIR__ . "/dspack.yaml"))))->run();
