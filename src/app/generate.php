<?php
declare(strict_types=1);

require 'bootstrap.php';

// generate sligtly more records than required
$records = AbstractGenerator::RECORDS + rand(1, 999);

$mysql = new MySQLGenerator();
$mysql->generate($records);

$postgres = new PostgresGenerator();
$postgres->generate($records);

$mongo = new MongoGenerator();
$mongo->generate($records);

abstract class AbstractGenerator
{
    const RECORDS = 1000000;
    const BATCH = 100;

    /** PDO */
    protected $connection;
    /** HelloFresh\Stats\Timer */
    protected $timer;

    public function __construct()
    {
        $this->timer = new HelloFresh\Stats\Timer\Memory();
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

    public function generate(int $records)
    {
        $this->timer->start();
        $this->log('Generating records: ' . $records);

        $this->generateRecords($records);

        $elapsed = $this->timer->finish('')->elapsed()->getElapsedFormatted();
        $this->log('Done generating records: ' . $records . '. Elapsed: ' . $elapsed);
    }

    protected function generateRecords(int $records)
    {
        $values = [];
        for ($i = 0; $i < $this::BATCH; $i++) {
            $values[] = sprintf('(:data%d)', $i);
        }

        $stmtSingle = $this->connection->prepare('INSERT INTO benchmark (data) VALUES (:data)');
        $stmtBatch = $this->connection->prepare('INSERT INTO benchmark (data) VALUES ' . implode(', ', $values));

        $values = [];
        for ($i = 0, $batch = 0; $i < $records; $i++) {
            $values['data' . $batch++] = uniqid();
            if ($batch % $this::BATCH === 0) {
                $stmtBatch->execute($values);

                $values = [];
                $batch = 0;
            }

            if ($i && !($i % 10000)) {
                $this->log('Generated records: ' . $i);
            }
        }

        foreach ($values as $data) {
            $stmtSingle->execute(['data' => $data]);
        }
    }

    abstract protected function getDriver() : string;
    abstract protected function getHost() : string;
    abstract protected function getUser() : string;

    protected function log(string $msg)
    {
        echo sprintf("[%s][%s]> %s\n", (new DateTime())->format(DateTime::ISO8601), $this->getDriver(), $msg);
    }
}

class MySQLGenerator extends AbstractGenerator
{
    protected function getDriver() : string
    {
        return "mysql";
    }

    protected function getHost() : string
    {
        return "mysql";
    }

    protected function getUser() : string
    {
        return "root";
    }
}

class PostgresGenerator extends AbstractGenerator
{
    protected function getDriver() : string
    {
        return "pgsql";
    }

    protected function getHost() : string
    {
        return "postgres";
    }

    protected function getUser() : string
    {
        return "benchmark";
    }
}

class MongoGenerator extends AbstractGenerator
{
    protected function initConnection()
    {
        $dsn = sprintf("%s://%s", $this->getDriver(), $this->getHost());

        $this->log("Connecting to: $dsn");
        $this->connection = (new MongoDB\Client($dsn))->{DB_NAME}->{"benchmark"};
    }

    protected function generateRecords(int $records)
    {
        $values = [];
        for ($i = 0, $batch = 0; $i < $records; $i++) {
            $values[] = ['data' => uniqid()];
            if ($batch % $this::BATCH === 0) {
                $this->connection->insertMany($values);

                $values = [];
                $batch = 0;
            }

            if ($i && !($i % 10000)) {
                $this->log('Generated records: ' . $i);
            }
        }

        if ($values) {
            $this->connection->insertMany($values);
        }
    }

    protected function getDriver() : string
    {
        return "mongodb";
    }

    protected function getHost() : string
    {
        return "mongodb";
    }

    protected function getUser() : string
    {
        return "mongodb";
    }
}
