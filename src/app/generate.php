<?php
declare(strict_types=1);

require 'bootstrap.php';

// generate slightly more records than required
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

    /** @var PDO|MongoDB\Collection */
    protected $connection;
    /** @var Symfony\Component\Console\Output\OutputInterface */
    protected $output;

    public function __construct()
    {
        $this->output = new Symfony\Component\Console\Output\ConsoleOutput();
        $this->output->setVerbosity($this->output::VERBOSITY_VERY_VERBOSE);

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
        $this->log('Generating records: ' . $records);

        $progress = new Symfony\Component\Console\Helper\ProgressBar($this->output, $records);
        $progress->start();

        $this->generateRecords($records, $progress);

        $progress->finish();

        $this->log('Done generating records: ' . $records);
    }

    protected function generateRecords(int $records, Symfony\Component\Console\Helper\ProgressBar $progress)
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
                $progress->advance($this::BATCH);

                $values = [];
                $batch = 0;
            }
        }

        foreach ($values as $data) {
            $stmtSingle->execute(['data' => $data]);
            $progress->advance(count($data));
        }
    }

    abstract protected function getDriver(): string;

    abstract protected function getHost(): string;

    abstract protected function getUser(): string;

    protected function log(string $msg)
    {
        $this->output->writeln(sprintf("[%s][%s]> %s", (new DateTime())->format(DateTime::ISO8601), $this->getDriver(), $msg));
    }
}

class MySQLGenerator extends AbstractGenerator
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

class PostgresGenerator extends AbstractGenerator
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

class MongoGenerator extends AbstractGenerator
{
    protected function initConnection()
    {
        $dsn = sprintf("%s://%s", $this->getDriver(), $this->getHost());

        $this->log("Connecting to: $dsn");
        $this->connection = (new MongoDB\Client($dsn))->{DB_NAME}->{"benchmark"};
    }

    protected function generateRecords(int $records, Symfony\Component\Console\Helper\ProgressBar $progress)
    {
        $values = [];
        for ($i = 0, $batch = 0; $i < $records; $i++) {
            $values[] = ['data' => uniqid()];
            if ($batch % $this::BATCH === 0) {
                $this->connection->insertMany($values);
                $progress->advance($this::BATCH);

                $values = [];
                $batch = 0;
            }

            if ($i && !($i % 10000)) {
                $this->log('Generated records: ' . $i);
            }
        }

        if ($values) {
            $this->connection->insertMany($values);
            $progress->advance(count($values));
        }
    }

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
}
