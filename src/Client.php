<?php

declare(strict_types=1);

namespace orange\observer;

use SplSubject;
use SplObserver;

abstract class Client implements SplObserver
{
    public function __construct(private readonly SplSubject $server)
    {
        $this->server->attach($this);
    }

    // must implement
    abstract public function update(SplSubject $caller): void;
}
