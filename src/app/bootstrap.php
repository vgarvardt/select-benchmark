<?php
declare(strict_types=1);

require 'vendor/autoload.php';

define("DB_NAME", "benchmark");
define("DB_PASS", "benchmark");

srand(time());

$output = new Symfony\Component\Console\Output\ConsoleOutput();
$output->setVerbosity($output::VERBOSITY_VERY_VERBOSE);

function out($context, $msg)
{
    global $output;
    $output->writeln(sprintf("[%s][%s]> %s", (new DateTime())->format('Y-m-d H:i:s'), $context, $msg));
}
