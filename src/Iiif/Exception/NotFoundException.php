<?php declare(strict_types=1);

namespace IiifServer\Iiif\Exception;

/**
 * Raised by the IIIF info helpers when the underlying file cannot be served
 * (typically a media row that still references an original file no longer
 * present on disk). The controller maps it to HTTP 404 — the IIIF Image API
 * spec recommends 404/410 over a degraded info.json.
 */
class NotFoundException extends \RuntimeException implements ExceptionInterface
{
}
