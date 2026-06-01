<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;

/**
 * Tell whether a resource has at least one IIIF-displayable media, that is a
 * media producing a canvas in the manifest (an image, audio or video with a
 * file), as opposed to external embeds (oembed such as YouTube, Vimeo or
 * SoundCloud) that have no IIIF content. Themes use it to hide the IIIF viewer
 * button and the manifest link when there is nothing to display.
 *
 * When the module DigitalObject is present, its inline digital objects are
 * checked too, since they are the canvases of the manifest.
 */
class IiifHasDisplayableMedia extends AbstractHelper
{
    public function __invoke(AbstractResourceEntityRepresentation $resource): bool
    {
        if ($resource instanceof MediaRepresentation) {
            return $this->isDisplayable($resource);
        }

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        if ($plugins->has('digitalObjectInline')) {
            foreach (($plugins->get('digitalObjectInline')($resource) ?: []) as $digitalObject) {
                if ($this->isDisplayable($digitalObject)) {
                    return true;
                }
            }
        }

        if (!method_exists($resource, 'media')) {
            return false;
        }
        foreach ($resource->media() as $media) {
            if ($this->isDisplayable($media)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A media is displayable in IIIF when it is not an external embed and has a
     * file or an image, audio or video media type.
     *
     * @param MediaRepresentation|object $media A media or a digital object.
     */
    protected function isDisplayable($media): bool
    {
        if (method_exists($media, 'renderer') && $media->renderer() === 'oembed') {
            return false;
        }
        if (method_exists($media, 'hasOriginal') && $media->hasOriginal()) {
            return true;
        }
        $mediaType = method_exists($media, 'mediaType') ? (string) $media->mediaType() : '';
        return $mediaType !== ''
            && (strpos($mediaType, 'image/') === 0
                || strpos($mediaType, 'audio/') === 0
                || strpos($mediaType, 'video/') === 0);
    }
}
