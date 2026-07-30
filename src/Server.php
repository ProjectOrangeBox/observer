<?php

declare(strict_types=1);

namespace orange\observer;

use SplSubject;
use SplObserver;

abstract class Server implements SplSubject
{
    /** @var array<string, SplObserver> keyed by spl_object_hash() */
    private array $observers = [];

    public function attach(SplObserver $observer): void
    {
        $id = spl_object_hash($observer);

        $this->observers[$id] = $observer;
    }

    public function detach(SplObserver $observer): void
    {
        $id = spl_object_hash($observer);

        if (isset($this->observers[$id])) {
            unset($this->observers[$id]);
        }
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }
}
