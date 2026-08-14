<?php

declare(strict_types=1);

namespace Mailkube\Model;

/**
 * A file attached to an email.
 *
 * Raw bytes are base64-encoded by the SDK on the way out; pass an already-encoded string only
 * when you encoded it yourself.
 */
final class Attachment
{
    /**
     * Describe one attachment.
     */
    public function __construct(
        public readonly string $filename,
        public readonly string $content,
        public readonly ?string $contentType = null,
        private readonly bool $preEncoded = false,
    ) {
    }

    /**
     * Attach raw file bytes; the SDK base64-encodes them for the wire.
     */
    public static function fromBytes(string $filename, string $bytes, ?string $contentType = null): self
    {
        return new self($filename, $bytes, $contentType);
    }

    /**
     * Attach content that is already base64-encoded.
     */
    public static function fromBase64(string $filename, string $encoded, ?string $contentType = null): self
    {
        return new self($filename, $encoded, $contentType, true);
    }

    /**
     * Render for the wire, base64-encoding raw bytes.
     *
     * @phpstan-return array<string, string>
     */
    public function toArray(): array
    {
        $item = [
            'filename' => $this->filename,
            'content' => $this->preEncoded ? $this->content : base64_encode($this->content),
        ];
        if ($this->contentType !== null) {
            $item['content_type'] = $this->contentType;
        }

        return $item;
    }
}
