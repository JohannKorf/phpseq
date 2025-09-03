#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSeq\CLI\GenerateCommand;
use Symfony\Component\Console\Application;

$application = new Application('phpseq', '0.1.0');
$application->add(new GenerateCommand());
$application->setDefaultCommand('phpseq:generate', true);
$application->run();
