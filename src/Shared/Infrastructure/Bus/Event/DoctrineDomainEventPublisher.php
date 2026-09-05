<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Event;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Bus\Event\DomainEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DoctrineDomainEventPublisher
{
    /** @var list<DomainEvent> */
    private array $events = [];

    public function __construct(
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([$unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates()] as $entities) {
            foreach ($entities as $entity) {
                if (!$entity instanceof AggregateRoot) {
                    continue;
                }

                foreach ($entity->pullDomainEvents() as $event) {
                    $this->events[] = $event;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $events = $this->events;
        $this->events = [];

        foreach ($events as $event) {
            $this->eventBus->dispatch(new Envelope($event, [new DispatchAfterCurrentBusStamp()]));
        }
    }
}
