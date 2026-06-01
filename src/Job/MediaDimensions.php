<?php declare(strict_types=1);

namespace IiifServer\Job;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Job\AbstractJob;

class MediaDimensions extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 25;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Logger
     */
    protected $logger;

    /**
     * @var \IiifServer\Mvc\Controller\Plugin\MediaDimension
     */
    protected $mediaDimension;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Doctrine\ORM\EntityRepository
     */
    protected $mediaRepository;

    /**
     * @var string
     */
    protected $filter;

    /**
     * @var array
     */
    protected $imageTypes;

    /**
     * @var int
     */
    protected $totalSucceed;

    /**
     * @var int
     */
    protected $totalFailed;

    /**
     * @var int
     */
    protected $totalSkipped;

    /**
     * @var int
     */
    protected $totalMedias;

    /**
     * @var int
     */
    protected $totalProcessed;

    /**
     * @var int
     */
    protected $totalToProcess;

    public function perform(): void
    {
        /** @var \Omeka\Api\Manager $api */
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('iiifserver/media-dimensions/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);
        $api = $services->get('Omeka\ApiManager');

        $query = $this->getArg('query') ?: [];
        if (is_string($query)) {
            $sQuery = [];
            parse_str($query, $sQuery);
            $query = $sQuery ?: [];
        }

        $scope = (array) $this->getArg('scope', ['items', 'digital_objects']);
        $scope = array_values(array_intersect($scope, ['items', 'digital_objects']));
        if (!$scope) {
            $this->logger->warn(
                'No scope selected (items / digital objects). Nothing to do.' // @translate
            );
            return;
        }
        $doItems = in_array('items', $scope, true);
        $doDigitalObjects = in_array('digital_objects', $scope, true);

        $this->prepareSizer();

        $this->totalToProcess = 0;
        $this->totalMedias = 0;
        $this->totalProcessed = 0;
        $this->totalSucceed = 0;
        $this->totalFailed = 0;
        $this->totalSkipped = 0;

        $response = $api->search('items', $query);
        $this->totalToProcess = $response->getTotalResults();
        if (empty($this->totalToProcess)) {
            $this->logger->warn(
                'No item selected. You may check your query.' // @translate
            );
            return;
        }

        $this->logger->info(
            'Starting bulk sizing for {total} items ({mode} media, scope: {scope}).', // @translate
            ['total' => $this->totalToProcess, 'mode' => $this->filter, 'scope' => implode(', ', $scope)]
        );

        // Collect ids of digital objects referenced by items, to deduplicate
        // across items (a DO can be shared by many items).
        $doIds = [];

        $offset = 0;
        while (true) {
            /** @var \Omeka\Api\Representation\ItemRepresentation[] $items */
            $items = $api
                ->search('items', ['limit' => self::SQL_LIMIT, 'offset' => $offset] + $query)
                ->getContent();
            if (empty($items)) {
                break;
            }

            foreach ($items as $key => $item) {
                if ($this->shouldStop()) {
                    $this->logger->warn(
                        'The job "Media Dimensions" was stopped: {count}/{total} resources processed.', // @translate
                        ['count' => $offset + $key, 'total' => $this->totalToProcess]
                    );
                    break 2;
                }

                if ($doItems) {
                    /** @var \Omeka\Api\Representation\MediaRepresentation $media */
                    foreach ($item->media() as $media) {
                        $mainMediaType = strtok((string) $media->mediaType(), '/');
                        if (in_array($mainMediaType, ['image', 'audio', 'video'])
                            // For ingester bulk_upload, wait that the process
                            // is finished, else the thumbnails won't be
                            // available and the size of derivative will be the
                            // fallback.
                            && $media->ingester() !== 'bulk_upload'
                        ) {
                            ++$this->totalMedias;
                            $this->prepareSize($media);
                        }
                        unset($media);
                    }
                }

                if ($doDigitalObjects) {
                    foreach ($item->values() as $property) {
                        foreach ($property['values'] as $value) {
                            $vr = $value->valueResource();
                            if ($vr && $vr->resourceName() === 'digital_objects') {
                                $doIds[$vr->id()] = true;
                            }
                        }
                    }
                }

                unset($item);

                ++$this->totalProcessed;
            }

            $this->entityManager->clear();
            $offset += self::SQL_LIMIT;
        }

        // Second pass: digital objects referenced by the matched items.
        if ($doDigitalObjects && $doIds) {
            $doIds = array_keys($doIds);
            foreach (array_chunk($doIds, self::SQL_LIMIT) as $chunk) {
                if ($this->shouldStop()) {
                    break;
                }
                try {
                    $dos = $api->search('digital_objects', ['id' => $chunk])->getContent();
                } catch (\Omeka\Api\Exception\BadRequestException $e) {
                    break;
                }
                foreach ($dos as $do) {
                    if ($this->shouldStop()) {
                        break 2;
                    }
                    $mainMediaType = strtok((string) $do->mediaType(), '/');
                    if (in_array($mainMediaType, ['image', 'audio', 'video'])) {
                        ++$this->totalMedias;
                        $this->prepareSize($do);
                    }
                    unset($do);
                }
                $this->entityManager->clear();
            }
        }

        $this->logger->notice(
            'End of bulk sizing: {count}/{total} items processed, {count_medias} media checked (images, audio, video), {count_succeed} sized, {count_skipped} already sized (skipped), {count_failed} errors.', // @translate
            [
                'count' => $this->totalProcessed,
                'total' => $this->totalToProcess,
                'count_medias' => $this->totalMedias,
                'count_succeed' => $this->totalSucceed,
                'count_skipped' => $this->totalSkipped,
                'count_failed' => $this->totalFailed,
            ]
        );
    }

    protected function prepareSizer(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $this->mediaDimension = $services->get('ControllerPluginManager')->get('mediaDimension');
        // The api cannot update value "data", so use entity manager.
        $this->entityManager = $services->get('Omeka\EntityManager');
        // Use Resource repository to support both Media and DigitalObject.
        $this->mediaRepository = $this->entityManager->getRepository(\Omeka\Entity\Resource::class);

        $this->filter = $this->getArg('filter', 'all');
        if (!in_array($this->filter, ['all', 'sized', 'unsized'])) {
            $this->filter = 'all';
        }
        $this->imageTypes = array_keys($services->get('Config')['thumbnails']['types']);
        // Keep original first.
        array_unshift($this->imageTypes, 'original');
    }

    /**
     * @see \IiifServer\Module::prepareSizeItem()
     * @see \IiifServer\Job\MediaDimensions::prepareSize()
     */
    protected function prepareSize(AbstractResourceEntityRepresentation $media): void
    {
        $mainMediaType = strtok((string) $media->mediaType(), '/');
        if (!in_array($mainMediaType, ['image', 'audio', 'video'])) {
            return;
        }

        // Keep possible data added by another module.
        $mediaData = $media->mediaData() ?: [];

        switch ($mainMediaType) {
            case 'audio':
            case 'video':
                if ($this->filter === 'sized') {
                    if (empty($mediaData['dimensions']['original']['duration'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                } elseif ($this->filter === 'unsized') {
                    if (!empty($mediaData['dimensions']['original']['duration'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                }
                break;
            case 'image':
            default:
                // Some images have no original.
                if ($this->filter === 'sized') {
                    if (empty($mediaData['dimensions']['large']['width'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                } elseif ($this->filter === 'unsized') {
                    if (!empty($mediaData['dimensions']['large']['width'])) {
                        ++$this->totalSkipped;
                        return;
                    }
                }
                break;
        }

        /** @var \Omeka\Entity\Media $mediaEntity */
        $mediaEntity = $this->mediaRepository->find($media->id());

        // Reset dimensions to make the sizer working.
        // TODO In rare cases, the original file is removed once the thumbnails are built.
        $mediaData['dimensions'] = [];
        $mediaEntity->setData($mediaData);

        $failedTypes = [];
        foreach ($mainMediaType === 'image' ? $this->imageTypes : ['original'] as $imageType) {
            // Force recalculation from file: the representation
            // still holds stale data after entity reset above.
            $result = $this->mediaDimension->__invoke($media, $imageType, true);
            if (!array_filter($result)) {
                $failedTypes[] = $imageType;
            }
            $mediaData['dimensions'][$imageType] = $result;
        }
        if (count($failedTypes)) {
            $this->logger->err(
                'Media #{media_id}: Error getting dimensions for types "{types}".', // @translate
                [
                    'media_id' => $mediaEntity->getId(),
                    'types' => implode('", "', $failedTypes),
                ]
            );
            ++$this->totalFailed;
        }

        $mediaEntity->setData($mediaData);
        $this->entityManager->persist($mediaEntity);
        $this->entityManager->flush();
        unset($mediaEntity);

        ++$this->totalSucceed;
    }
}
