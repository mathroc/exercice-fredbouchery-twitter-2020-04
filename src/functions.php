<?php declare(strict_types=1);

function process(string $filePath, ?string $fields, ?string $aggregate): Iterator
{
    [
        "extractor" => $extractor,
        "transformer" => $transformer,
        "aggregator" => $aggregator
    ] = init($filePath, $fields, $aggregate);

    $lines = map($extractor, $transformer);

    return $aggregator($lines);
}

function init(string $filePath, ?string $fields, ?string $aggregate): array {
    $file = new SplFileObject($filePath);

    $file->setCsvControl(guessDelimiter($file->current()));
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
    $file->rewind();

    $fields = fields($fields);

    $header = $file->current();

    ["aggregator" => $aggregator, "fields" => $fields] = aggregator($aggregate, $header, $fields);

    return [
        "extractor" => iterator($file),
        "transformer" => transformer($fields, $header),
        "aggregator" => $aggregator
    ];
}

function aggregator(?string $aggregate, array $header, ?array &$fields): array {
    if ($aggregate === null) {
        return ["fields" => $fields, "aggregator" => fn ($data) => $data];
    }

    $keepAggregateField = false;

    if ($aggregate !== null) {
        if (!in_array($aggregate, $header)) {
            echo "Invalid aggregate '$aggregate' not in (", implode(", ", $header), ")", PHP_EOL;
            exit(1);
        }

        if (is_array($fields)) {
            if (in_array($aggregate, $fields)) {
                $keepAggregateField = true;
            } else {
                $fields[] = $aggregate;
            }
        }
    }

    $aggregator = function (Iterator $lines) use ($aggregate, $keepAggregateField): Iterator {
        $data = [];

        foreach ($lines as $line) {
            $key = $line[$aggregate];

            if (!array_key_exists($key, $data)) {
                $data[$key] = [];
            }

            if (!$keepAggregateField) {
                unset($line[$aggregate]);
            }

            $data[$key][] = $line;
        }

        return new ArrayIterator($data);
    };

    return ["fields" => $fields, "aggregator" => $aggregator];
}

function transformer(?array $fields, array $header): callable {
    $diff = $fields === null ? [] : array_diff($fields, $header);

    if (!empty($diff)) {
        echo "Invalid fields (", implode(", ", $diff), ") not in (", implode(", ", $header), ")", PHP_EOL;
        exit(1);
    }

    $mapping = [];
    $headerKeys = array_flip($header);

    foreach ($fields === null ? $header : array_intersect($fields, $header) as $field) {
        $mapping[$field] = $headerKeys[$field];
    }

    return fn (array $values): array => array_map(fn ($key) => $values[$key], $mapping);
}

function map(iterable $iterable, callable $mapper): iterable
{
    foreach ($iterable as $line) {
        yield $mapper($line);
    }
}

function iterator(Iterator $iterator): iterable
{
    for($iterator->next(); !$iterator->eof(); $iterator->next()) {
        yield $iterator->current();
    }
}

function toJson(Iterator $lines, bool $pretty, bool $object): void {
    $flags = $pretty ? JSON_PRETTY_PRINT : 0;

    if ($object) {
        echo json_encode(iterator_to_array($lines), $flags);
        return;
    }

    $comma = "," . ($pretty ? " " : "");

    $line = $lines->current();
    echo "[", json_encode($line, $flags);

    for ($lines->next(); $lines->valid(); $lines->next()) {
        echo $comma, json_encode($lines->current(), $flags);
    }

    echo "]";
}

function fields(?string $fields): ?array {
    return $fields === null ? null : array_map("trim", explode(guessDelimiter($fields), $fields));
}

function guessDelimiter(string $header): string {
    return ";";
}
