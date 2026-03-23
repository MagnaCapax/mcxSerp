<?php

declare(strict_types=1);

namespace MagnaCapax\Serp\Http;

use Psr\Http\Message\StreamInterface;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $stream;

    public function __construct($stream = null)
    {
        $this->stream = $stream ?? fopen('php://temp', 'r+');
    }

    public static function fromString(string $content): self
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return new self($stream);
    }

    public function __toString(): string
    {
        if (!is_resource($this->stream)) {
            return '';
        }

        $this->rewind();

        return stream_get_contents($this->stream) ?: '';
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    public function detach()
    {
        $stream = $this->stream;
        $this->stream = null;

        return $stream;
    }

    public function getSize(): ?int
    {
        if (!is_resource($this->stream)) {
            return null;
        }

        $stats = fstat($this->stream);

        return $stats['size'] ?? null;
    }

    public function tell(): int
    {
        $position = ftell($this->stream);
        if ($position === false) {
            throw new \RuntimeException('Unable to determine stream position.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return !is_resource($this->stream) || feof($this->stream);
    }

    public function isSeekable(): bool
    {
        if (!is_resource($this->stream)) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);

        return (bool) ($meta['seekable'] ?? false);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable() || fseek($this->stream, $offset, $whence) !== 0) {
            throw new \RuntimeException('Unable to seek stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if (!is_resource($this->stream)) {
            return false;
        }

        $mode = stream_get_meta_data($this->stream)['mode'] ?? '';

        return strpbrk($mode, 'waxc+') !== false;
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new \RuntimeException('Stream is not writable.');
        }

        $written = fwrite($this->stream, $string);
        if ($written === false) {
            throw new \RuntimeException('Unable to write to stream.');
        }

        return $written;
    }

    public function isReadable(): bool
    {
        if (!is_resource($this->stream)) {
            return false;
        }

        $mode = stream_get_meta_data($this->stream)['mode'] ?? '';

        return strpbrk($mode, 'r+') !== false;
    }

    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream is not readable.');
        }

        $content = fread($this->stream, $length);
        if ($content === false) {
            throw new \RuntimeException('Unable to read from stream.');
        }

        return $content;
    }

    public function getContents(): string
    {
        if (!is_resource($this->stream)) {
            return '';
        }

        $content = stream_get_contents($this->stream);
        if ($content === false) {
            throw new \RuntimeException('Unable to read stream contents.');
        }

        return $content;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if (!is_resource($this->stream)) {
            return $key === null ? [] : null;
        }

        $metadata = stream_get_meta_data($this->stream);

        return $key === null ? $metadata : ($metadata[$key] ?? null);
    }
}
