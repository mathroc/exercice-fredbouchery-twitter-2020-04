<?php declare(strict_types=1);

require_once __DIR__ . "/autoload.php";

$command = new Csv2JsonCommand();

try {
    $command(Csv2JsonArgvOptions::fromArray($argv), new StdOut());
} catch (Error|LogicException $error) {
    throw $error;
} catch (Throwable $th) {
    fwrite(STDERR, $th->getMessage() . PHP_EOL);
    exit($th->getCode() ? $th->getCode() : 254);
}
