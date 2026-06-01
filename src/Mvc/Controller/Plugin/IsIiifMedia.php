<?php declare(strict_types=1);

namespace IiifServer\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class IsIiifMedia extends AbstractPlugin
{
    /**
     * @var array<string, string[]>
     */
    protected array $mediaIngesters;

    public function __construct(array $mediaIngesters)
    {
        $this->mediaIngesters = $mediaIngesters;
    }

    /**
     * Check if a media uses an ingester registered as IIIF.
     *
     * The list of IIIF ingesters is declared by modules via the merged config
     * key `iiifserver.media_ingesters`, grouped by type (image, audio, video,
     * presentation). A null $type matches any registered IIIF ingester,
     * whatever its type.
     *
     * Example config contributed by module IiifRemoteImage:
     *   'iiifserver' => ['media_ingesters' => ['image' => ['iiif-remote-image']]]
     */
    public function __invoke(AbstractResourceEntityRepresentation $media, ?string $type = null): bool
    {
        // Match by ingester (configured names, e.g. contributed by other
        // modules) or by renderer, so digital objects, whose ingester is always
        // "digital_object" but whose renderer is "iiif"/"iiif_presentation",
        // are recognized too.
        $ingester = $media->ingester();
        $renderer = method_exists($media, 'renderer') ? $media->renderer() : '';
        $lists = $type === null
            ? $this->mediaIngesters
            : [$this->mediaIngesters[$type] ?? []];
        foreach ($lists as $list) {
            if (in_array($ingester, $list, true) || in_array($renderer, $list, true)) {
                return true;
            }
        }
        return false;
    }
}
