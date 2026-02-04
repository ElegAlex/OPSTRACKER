<?php

namespace App\EventSubscriber;

use App\Entity\Campagne;
use App\Service\SegmentSyncService;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber EasyAdmin pour synchroniser les segments quand colonneSegment est modifie.
 */
class CampagneSegmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SegmentSyncService $segmentSyncService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityUpdatedEvent::class => 'onAfterEntityUpdated',
        ];
    }

    /**
     * @param AfterEntityUpdatedEvent<object> $event
     */
    public function onAfterEntityUpdated(AfterEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof Campagne) {
            return;
        }

        // Si colonneSegment est defini, synchroniser les segments
        if ($entity->getColonneSegment()) {
            $this->segmentSyncService->syncFromColonne($entity);
        }
    }
}
