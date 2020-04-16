<?php declare(strict_types=1);

require_once __DIR__ . "/../src/functions.php";

array_shift($argv);

$filePath = array_shift($argv);

if (!file_exists($filePath)) {
    echo "$filePath does not exists", PHP_EOL;
    exit(1);
}

if (!is_file($filePath)) {
    echo "$filePath is not a file", PHP_EOL;
    exit(1);
}

if (!is_readable($filePath)) {
    echo "$filePath is not a readable", PHP_EOL;
    exit(1);
}

$fields = null;
$aggregate = null;
$desc = null;
$pretty = false;

while (($arg = array_shift($argv)) !== null) {
    if ($arg === "--pretty") {
        $pretty = true;
    } else if (in_array($arg, ["--fields", "--aggregate", "--desc"])) {
        $value = array_shift($argv);

        if ($value === null) {
            echo "Missing value for $arg parameter", PHP_EOL;
            exit(1);
        }

        ${substr($arg, 2)} = $value;
    } else {
        echo "Unknown $arg parameter", PHP_EOL;
        exit(1);
    }
}

toJson(process($filePath, $fields, $aggregate), $pretty, $aggregate !== null);
