<?php
declare(strict_types=1);

require 'bootstrap.php';

abstract class AbstractGenerator extends DBAccessor
{
    const RECORDS = 5000000;
    const BATCH = 500;

    public function generate(int $records)
    {
        $this->log('Generating records: ' . $records);

        $progress = new Symfony\Component\Console\Helper\ProgressBar($this->output, $records);
        $progress->start();

        $this->generateRecords($records, $progress);

        $progress->finish();
        $this->output->writeln("");

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
            $progress->advance(1);
        }
    }
}

class MySQLGenerator extends AbstractGenerator
{
    use MySQLDBAccessor;
}

class PgSQLGenerator extends AbstractGenerator
{
    use PgSQLDBAccessor;
}

class MongoGenerator extends AbstractGenerator
{
    use MongoDBAccessor;

    protected function generateRecords(int $records, Symfony\Component\Console\Helper\ProgressBar $progress)
    {
        $values = [];
        for ($i = 0, $batch = 0; $i < $records; $i++) {
            $values[$batch++] = ['data' => uniqid(), 'created_at' => new MongoDB\BSON\UTCDateTime()];
            if ($batch % $this::BATCH === 0) {
                $this->connection->insertMany($values);
                $progress->advance($this::BATCH);

                $values = [];
                $batch = 0;
            }
        }

        if ($values) {
            $this->connection->insertMany($values);
            $progress->advance(count($values));
        }
    }
}

// generate slightly more records than required
$records = AbstractGenerator::RECORDS + rand(1, 999);

$mysql = new MySQLGenerator($output);
$mysql->generate($records);

$postgres = new PgSQLGenerator($output);
$postgres->generate($records);

$mongo = new MongoGenerator($output);
$mongo->generate($records);
