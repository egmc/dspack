<?php
require __DIR__ .'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use DsPack\DsPack;

(new DsPack(Yaml::parseFile(__DIR__ . "/dspack.yaml")))->run();
