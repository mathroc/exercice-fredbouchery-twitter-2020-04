<?php declare(strict_types=1);

final class Csv2JsonArgvOptions implements Csv2JsonOptions {
    private string $filePath;

    private ?string $fields = null;

    private ?string $aggregate = null;

    private ?string $descFilePath = null;

    private bool $pretty = false;

    private function __construct() {}

    public static function fromArray(array $argv): self
    {
        $options = new self();

        array_shift($argv);

        if (empty($argv)) {
            throw new RuntimeException("file path missing", 1);
        }

        $options->filePath = array_shift($argv);

        while (($arg = array_shift($argv)) !== null) {
            if ($arg === "--pretty") {
                $options->pretty = true;
            } else if (in_array($arg, ["--fields", "--aggregate", "--desc"])) {
                $value = array_shift($argv);

                if ($value === null) {
                    throw new RuntimeException("Missing value for $arg parameter", 1);
                }

                $options->{substr($arg, 2)} = $value;
            } else {
                throw new RuntimeException("Unknown $arg parameter", 1);
            }
        }

        return $options;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function fields(): ?array
    {
        return $this->fields !== null ? explode((string) Delimiter::guessFromString($this->fields), $this->fields) : null;
    }

    public function aggregate(): ?string
    {
        return $this->aggregate;
    }

    public function descFilePath(): ?string
    {
        return $this->descFilePath;
    }

    public function pretty(): bool
    {
        return $this->pretty;
    }
}

final class Delimiter
{
    private string $delimiter;

    private function __construct(string $delimiter)
    {
        $this->delimiter = $delimiter;
    }

    public static function guessFromString(string $text): self
    {
        return new self(";");
    }

    public function __toString(): string
    {
        return $this->delimiter;
    }
}

final class CSVFile implements CSV, IteratorAggregate {
    private SplFileObject $file;

    private function __construct(SplFileObject $file)
    {
        $this->file = $file;
    }

    public static function fromPath(string $path): self
    {
        if (!file_exists($path)) {
            echo "$path does not exists", PHP_EOL;
            exit(1);
        }

        if (!is_file($path)) {
            echo "$path is not a file", PHP_EOL;
            exit(1);
        }

        if (!is_readable($path)) {
            echo "$path is not a readable", PHP_EOL;
            exit(1);
        }

        return new self(new SplFileObject($path));
    }

    public function header(): array
    {
        $this->file->setFlags(SplFileObject::DROP_NEW_LINE);
        $this->file->setCsvControl((string) Delimiter::guessFromString($this->file->current()));
        $this->file->setFlags(
            SplFileObject::READ_CSV |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::READ_AHEAD |
            SplFileObject::DROP_NEW_LINE
        );

        $this->file->rewind();

        return $this->file->current();
    }

    public function getIterator(): Traversable
    {
        $header = $this->header();

        for($this->file->next(); !$this->file->eof(); $this->file->next()) {
            yield array_combine($header, $this->file->current());
        }
    }

    public function jsonSerialize()
    {
        return iterator_to_array($this);
    }
}

final class MaskedCSV implements CSV, IteratorAggregate {
    private CSV $csv;

    private array $fields;

    private function __construct(CSV $csv, array $fields)
    {
        $this->csv = $csv;
        $this->fields = $fields;
    }

    public function fromFields(CSV $csv, array $fields): self
    {
        return new self($csv, $fields);
    }

    public function getIterator(): Traversable
    {
        $diff = array_diff($this->fields, $header = $this->csv->header());

        if (!empty($diff)) {
            throw new RuntimeException(
                "Invalid fields (" . implode(", ", $diff) . ") " .
                "not in (" . implode(", ", $header) . ")"
            );
        }

        $mapping = array_combine($this->fields, $this->fields);

        $mask = fn (array $values): array => array_map(fn ($key) => $values[$key], $mapping);

        foreach ($this->csv as $line) {
            yield $mask($line);
        }
    }

    public function header(): array
    {
        return $this->fields;
    }

    public function jsonSerialize()
    {
        return iterator_to_array($this);
    }

    public function withField(string $field): self
    {
        if (in_array($field, $this->header())) {
            return $this;
        }

        if (!in_array($field, $header = $this->csv->header())) {
            throw new RuntimeException("Invalid field $field not in (" . implode(", ", $header) . ")");
        }

        return new self($this->csv, [$field, ...$this->header()]);
    }
}

final class AggregatedCSV implements JsonSerializable, IteratorAggregate {
    private CSV $csv;

    private string $aggregateField;

    private bool $keepAggregateField;

    private function __construct(CSV $csv, string $aggregateField, bool $keepAggregateField)
    {
        $this->csv = $csv;
        $this->aggregateField = $aggregateField;
        $this->keepAggregateField = $keepAggregateField;
    }

    public static function fromField(CSV $csv, string $aggregateField): self
    {
        $keepAggregateField = false;

        if ($csv instanceof MaskedCSV) {
            if (in_array($aggregateField, $csv->header())) {
                $keepAggregateField = true;
            } else {
                try {
                    $csv = $csv->withField($aggregateField);
                } catch (RuntimeException $ex) {
                    throw new RuntimeException(
                        "Invalid aggregate '$aggregateField' not in (" . implode(", ", $header) . ")",
                        1,
                        $ex,
                    );
                }
            }
        } else if (!in_array($aggregateField, $header = $csv->header())) {
            throw new RuntimeException(
                "Invalid aggregate '$aggregateField' not in (" . implode(", ", $header) . ")",
                1,
            );
        }

        return new self($csv, $aggregateField, $keepAggregateField);
    }

    public function getIterator(): Traversable
    {
        $data = [];

        foreach ($this->csv as $line) {
            $key = $line[$this->aggregateField];

            if (!array_key_exists($key, $data)) {
                $data[$key] = [];
            }

            if (!$this->keepAggregateField) {
                unset($line[$this->aggregateField]);
            }

            $data[$key][] = $line;
        }

        return new ArrayIterator($data);
    }

    public function jsonSerialize()
    {
        return iterator_to_array($this);
    }
}

final class JSON {
    private JsonSerializable $data;

    private function __construct(JsonSerializable $data)
    {
        $this->data = $data;
    }

    public static function fromJsonSerializable(JsonSerializable $data): self
    {
        return new self($data);
    }

    public function print(Output $output, bool $pretty): void
    {
        $flags = $pretty ? JSON_PRETTY_PRINT : 0;

        if ($this->data instanceof AggregatedCSV) {
            echo json_encode($this->data, $flags);
            return;
        }

        $comma = "," . ($pretty ? " " : "");

        $lines = $this->data->getIterator();

        $line = $lines->current();
        echo "[", json_encode($line, $flags);

        for ($lines->next(); $lines->valid(); $lines->next()) {
            echo $comma, json_encode($lines->current(), $flags);
        }

        echo "]";
    }
}

final class ResourceOutput implements Output {
    private $resource;

    public function __construct($resource)
    {
        assert(is_ressource($resource));

        $this->resource = $resource;
    }

    public function write(string $text): void
    {
        fwrite($this->resource, $text);
    }
}

abstract class NewLineOutput implements OutputWithNewLine {
    private Output $output;

    protected function __construct(Output $output)
    {
        $this->output = $output;
    }

    public function write(string $text): void
    {
        $this->output->write($text);
    }

    public function writeln(string $text): void
    {
        $this->output->write($text);
        $this->output->write(PHP_EOL);
    }
}

final class StdOut extends NewLineOutput {
    public function __construct()
    {
        parent::__construct(new ResourceOutput(STDOUT));
    }
}

final class StdErr extends NewLineOutput {
    public function __construct()
    {
        parent::__construct(new ResourceOutput(STDERR));
    }
}

final class Csv2JsonCommand {
    public function __invoke(Csv2JsonOptions $options, Output $output): void
    {
        $data = CSVFile::fromPath($options->filePath());

        if (($fields = $options->fields()) !== null) {
            $data = MaskedCSV::fromFields($data, $fields);
        }

        if (($descFilePath = $options->descFilePath()) !== null) {
            $data = CoercedCSV::fromDesc($data, Desc::fromFilePath($descFilePath));
        }

        if (($aggregate = $options->aggregate()) !== null) {
            $data = AggregatedCSV::fromField($data, $aggregate);
        }

        JSON::fromJsonSerializable($data)->print($output, $options->pretty());
    }
}

