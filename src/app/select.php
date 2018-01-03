<?php
declare(strict_types=1);

require 'bootstrap.php';

abstract class AbstractSelector extends DBAccessor
{
    public function selectOffset(int $limit): array
    {
        $records = $this->count();
        $this->log('Selecting records using OFFSET: ' . $records);

        $progress = new Symfony\Component\Console\Helper\ProgressBar($this->output, $records);
        $progress->start();

        $result = $this->performSelectOffset($limit, $progress);

        $progress->finish();
        $this->output->writeln("");

        $this->log('Done selecting records using OFFSET: ' . $records);

        return $result;
    }

    public function selectWhere(int $limit): array
    {
        $records = $this->count();
        $this->log('Selecting records using WHERE: ' . $records);

        $progress = new Symfony\Component\Console\Helper\ProgressBar($this->output, $records);
        $progress->start();

        $result = $this->performSelectWhere($limit, $progress);

        $progress->finish();
        $this->output->writeln("");

        $this->log('Done selecting records using WHERE: ' . $records);

        return $result;
    }

    protected function performSelectOffset(int $limit, Symfony\Component\Console\Helper\ProgressBar $progress): array
    {
        $result = [];

        $stmt = $this->connection->prepare('SELECT id, data, created_at FROM benchmark ORDER BY id ASC LIMIT :limit OFFSET :offset');
        for ($offset = 0; ; $offset += $limit) {
            $start = microtime(true);

            $stmt->execute(['limit' => $limit, 'offset' => $offset]);
            $progress->advance($stmt->rowCount());

            $result[$offset] = microtime(true) - $start;

            if ($stmt->rowCount() < $limit) {
                break;
            }
        }

        return $result;
    }

    protected function performSelectWhere(int $limit, Symfony\Component\Console\Helper\ProgressBar $progress): array
    {
        $result = [];

        $stmt = $this->connection->prepare('SELECT id, data, created_at FROM benchmark WHERE id > :id ORDER BY id ASC LIMIT :limit');
        for ($offset = 0, $id = 0; ; $offset += $limit) {
            $start = microtime(true);

            $stmt->execute(['limit' => $limit, 'id' => $id]);
            $progress->advance($stmt->rowCount());

            $result[$offset] = microtime(true) - $start;

            while ($value = $stmt->fetchColumn()) {
                $id = $value;
            }

            if ($stmt->rowCount() < $limit) {
                break;
            }
        }

        return $result;
    }

    protected function count(): int
    {
        return $this->connection->query('SELECT COUNT(1) FROM benchmark')->fetchColumn();
    }
}

class MySQLSelector extends AbstractSelector
{
    use MySQLDBAccessor;
}

class PgSQLSelector extends AbstractSelector
{
    use PgSQLDBAccessor;
}

class MongoSelector extends AbstractSelector
{
    use MongoDBAccessor;

    protected function performSelectOffset(int $limit, Symfony\Component\Console\Helper\ProgressBar $progress): array
    {
        $result = [];

        for ($offset = 0; ; $offset += $limit) {
            $start = microtime(true);

            $cursor = $this->connection->find([], ['limit' => $limit, 'skip' => $offset, 'sort' => ['_id' => 1]]);
            $result[$offset] = microtime(true) - $start;

            $count = count($cursor->toArray());
            $progress->advance($count);


            if ($count < $limit) {
                break;
            }
        }

        return $result;
    }

    protected function performSelectWhere(int $limit, Symfony\Component\Console\Helper\ProgressBar $progress): array
    {
        $result = [];

        for ($offset = 0, $id = new MongoDB\BSON\ObjectId(str_repeat('0', 24)); ; $offset += $limit) {
            $start = microtime(true);

            $cursor = $this->connection->find(['_id' => ['$gt' => $id]], ['limit' => $limit, 'sort' => ['_id' => 1]]);
            $result[$offset] = microtime(true) - $start;

            $rows = $cursor->toArray();
            $count = count($rows);
            $lastRow = $rows[$count - 1];
            $id = $lastRow['_id'];

            $progress->advance($count);

            if ($count < $limit) {
                break;
            }
        }

        return $result;
    }

    protected function count(): int
    {
        return $this->connection->count();
    }
}

$limit = 10000;

$selectors = [
    'mysql' => new MySQLSelector($output),
    'pgsql' => new PgSQLSelector($output),
    'mongo' => new MongoSelector($output),
];

foreach ($selectors as $engine => $selector) {
    $timingsOffset = $timingsWhere = [];

    /** @var AbstractSelector $selector */
    for ($i = 0; $i < TRIES; $i++) {
        out($engine, 'Running try ' . ($i + 1) . ' / ' . TRIES);

        $tryFileNameOffset = sprintf('try-offset-%d.%s.txt', $i, $engine);
        $tryFileNameWhere = sprintf('try-where-%d.%s.txt', $i, $engine);

        if (file_exists($tryFileNameOffset)) {
            out($engine, 'Try OFFSET cache found, trying to load data from file: ' . $tryFileNameOffset);
            $timingsOffset[$i] = unserialize(file_get_contents($tryFileNameOffset));
        } else {
            $timingsOffset[$i] = $selector->selectOffset($limit);
            file_put_contents($tryFileNameOffset, serialize($timingsOffset[$i]));
        }

        if (file_exists($tryFileNameWhere)) {
            out($engine, 'Try WHERE cache found, trying to load data from file: ' . $tryFileNameWhere);
            $timingsWhere[$i] = unserialize(file_get_contents($tryFileNameWhere));
        } else {
            $timingsWhere[$i] = $selector->selectWhere($limit);
            file_put_contents($tryFileNameWhere, serialize($timingsWhere[$i]));
        }
    }

    $avgOffset = $avgWhere = [];
    out($engine, 'Calculating AVGs');
    foreach ($timingsOffset[0] as $offset => $time) {
        for ($i = 0; $i < TRIES; $i++) {
            $avgOffset[$offset][$i] = $timingsOffset[$i][$offset];
            $avgWhere[$offset][$i] = $timingsWhere[$i][$offset];
        }

        $avgOffset[$offset] = array_sum($avgOffset[$offset]) / (float)TRIES;
        $avgWhere[$offset] = array_sum($avgWhere[$offset]) / (float)TRIES;
    }

    $nameOffset = sprintf('avg-offset.%s.csv', $engine);
    $nameWhere = sprintf('avg-where.%s.csv', $engine);
    out($engine, 'Writing result file: ' . $nameOffset);
    out($engine, 'Writing result file: ' . $nameWhere);

    $fpOffset = fopen($nameOffset, 'w');
    $fpWhere = fopen($nameWhere, 'w');

    foreach ($avgOffset as $offset => $time) {
        fputcsv($fpOffset, [$offset, $avgOffset[$offset]]);
        fputcsv($fpWhere, [$offset, $avgWhere[$offset]]);
    }

    fclose($fpOffset);
    fclose($fpWhere);

    out($engine, 'Done');
}
