# Observer

Minimal Observer pattern built directly on PHP's built-in `SplSubject`/`SplObserver` interfaces. `Server` is a subject you extend to broadcast state changes; `Client` is an observer you extend to react to them.

## Example

```php
use SplSubject;
use peels\observer\Server;
use peels\observer\Client;

class JobQueue extends Server
{
    private string $status = 'idle';

    public function setStatus(string $status): void
    {
        $this->status = $status;

        $this->notify(); // calls update() on every attached Client
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}

class Logger extends Client
{
    public function update(SplSubject $caller): void
    {
        /** @var JobQueue $caller */
        echo 'Job queue status: ' . $caller->getStatus() . PHP_EOL;
    }
}

$queue = new JobQueue();
$logger = new Logger($queue); // attaches itself to $queue automatically

$queue->setStatus('processing'); // prints "Job queue status: processing"
```

`Client`'s constructor calls `attach()` on the subject for you; call `$queue->detach($logger)` to stop receiving updates.
