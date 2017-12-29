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

abstract class DBAccessor
{
    /** @var PDO|MongoDB\Collection */
    protected $connection;
    /** @var Symfony\Component\Console\Output\OutputInterface */
    protected $output;

    public function __construct(Symfony\Component\Console\Output\OutputInterface $output)
    {
        $this->output = $output;

        $this->initConnection();
    }

    protected function initConnection()
    {
        $dsn = sprintf("%s:host=%s;dbname=%s", $this->getDriver(), $this->getHost(), DB_NAME);
        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->log("Connecting to: $dsn");
        $this->connection = new PDO($dsn, $this->getUser(), DB_PASS, $opt);
    }

    abstract protected function getDriver(): string;

    abstract protected function getHost(): string;

    abstract protected function getUser(): string;

    protected function log(string $msg)
    {
        out($this->getDriver(), $msg);
    }
}

trait MySQLDBAccessor
{
    protected function getDriver(): string
    {
        return "mysql";
    }

    protected function getHost(): string
    {
        return "mysql";
    }

    protected function getUser(): string
    {
        return "root";
    }
}

trait PgSQLDBAccessor
{
    protected function getDriver(): string
    {
        return "pgsql";
    }

    protected function getHost(): string
    {
        return "postgres";
    }

    protected function getUser(): string
    {
        return "benchmark";
    }
}

trait MongoDBAccessor
{
    protected function getDriver(): string
    {
        return "mongodb";
    }

    protected function getHost(): string
    {
        return "mongodb";
    }

    protected function getUser(): string
    {
        return "mongodb";
    }

    protected function initConnection()
    {
        $dsn = sprintf("%s://%s", $this->getDriver(), $this->getHost());

        $this->log("Connecting to: $dsn");
        $this->connection = (new MongoDB\Client($dsn))->{DB_NAME}->{"benchmark"};
    }
}
