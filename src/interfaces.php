<?php declare(strict_types=1);

interface Csv2JsonOptions {
	public function filePath(): string;

	public function fields(): ?array;

	public function aggregate(): ?string;

	public function descFilePath(): ?string;

	public function pretty(): bool;
}

interface CSV extends Traversable, JsonSerializable {
	public function header(): array;
}

interface Output {
	public function write(string $text): void;
}

interface OutputWithNewLine extends Output {
	public function writeln(string $text): void;
}
