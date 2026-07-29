<?php

declare(strict_types=1);

use orange\observer\Client;
use orange\observer\Server;
use PHPUnit\Framework\TestCase;

/**
 * A concrete subject. The package ships only abstracts, so the tests supply the
 * two halves a real application would.
 */
final class TestServer extends Server
{
    public string $state = '';
}

/**
 * A concrete observer that records what it was handed, so a test can prove
 * notify() passed the subject itself rather than merely that it fired.
 */
final class RecordingClient extends Client
{
    public int $updates = 0;
    public ?SplSubject $lastCaller = null;

    public function update(SplSubject $caller): void
    {
        $this->updates++;
        $this->lastCaller = $caller;
    }
}

/**
 * An observer that detaches itself the moment it is notified - the case that
 * decides whether notify() iterates a snapshot or the live collection.
 */
final class SelfDetachingClient extends Client
{
    public int $updates = 0;

    public function __construct(private readonly Server $subject)
    {
        parent::__construct($subject);
    }

    public function update(SplSubject $caller): void
    {
        $this->updates++;
        $this->subject->detach($this);
    }
}

final class ObserverTest extends TestCase
{
    private TestServer $server;

    protected function setUp(): void
    {
        $this->server = new TestServer();
    }

    /**
     * Read the private collection - the whole surface is attach/detach/notify,
     * so nothing else can report how many observers are actually held.
     */
    private function observerCount(Server $server): int
    {
        $reader = fn() => $this->observers;

        return count(Closure::bind($reader, $server, Server::class)());
    }

    public function testConstructingAClientAttachesItToItsServer(): void
    {
        $this->assertSame(0, $this->observerCount($this->server));

        new RecordingClient($this->server);

        // Client's constructor attaches - a caller never has to remember to
        $this->assertSame(1, $this->observerCount($this->server));
    }

    public function testNotifyCallsUpdateOnEveryAttachedObserver(): void
    {
        $first = new RecordingClient($this->server);
        $second = new RecordingClient($this->server);

        $this->server->notify();

        $this->assertSame(1, $first->updates);
        $this->assertSame(1, $second->updates);
    }

    /**
     * update() is handed the subject, which is the only way an observer can read
     * the state it is being told changed.
     */
    public function testNotifyPassesTheSubjectItself(): void
    {
        $client = new RecordingClient($this->server);

        $this->server->state = 'changed';
        $this->server->notify();

        $this->assertSame($this->server, $client->lastCaller);
        $this->assertSame('changed', $client->lastCaller->state);
    }

    public function testNotifyingWithNoObserversIsNotAnError(): void
    {
        $this->server->notify();

        $this->assertSame(0, $this->observerCount($this->server));
    }

    /**
     * Observers are keyed by object hash, so attaching the same one twice holds
     * it once - and it is notified once, not twice.
     */
    public function testAttachingTheSameObserverTwiceHoldsItOnce(): void
    {
        $client = new RecordingClient($this->server);

        // the constructor already attached it; do it again explicitly
        $this->server->attach($client);

        $this->assertSame(1, $this->observerCount($this->server));

        $this->server->notify();

        $this->assertSame(1, $client->updates);
    }

    public function testDetachStopsAnObserverBeingNotified(): void
    {
        $kept = new RecordingClient($this->server);
        $dropped = new RecordingClient($this->server);

        $this->server->detach($dropped);

        $this->assertSame(1, $this->observerCount($this->server));

        $this->server->notify();

        $this->assertSame(1, $kept->updates);
        $this->assertSame(0, $dropped->updates);
    }

    /**
     * Detaching something that was never attached is a no-op rather than an
     * error - the caller would otherwise have to track attachment itself.
     */
    public function testDetachingAnUnattachedObserverIsHarmless(): void
    {
        $attached = new RecordingClient($this->server);

        $stranger = new RecordingClient(new TestServer());

        $this->server->detach($stranger);

        $this->assertSame(1, $this->observerCount($this->server));

        $this->server->notify();

        $this->assertSame(1, $attached->updates);
        // it belongs to the other server, so this one's notify never reached it
        $this->assertSame(0, $stranger->updates);
    }

    public function testDetachingTwiceIsHarmless(): void
    {
        $client = new RecordingClient($this->server);

        $this->server->detach($client);
        $this->server->detach($client);

        $this->assertSame(0, $this->observerCount($this->server));
    }

    /**
     * notify() iterates by foreach over the property, and PHP arrays are
     * value types - so the loop runs over a copy and an observer that detaches
     * itself mid-notify neither breaks the iteration nor skips its neighbours.
     */
    public function testAnObserverMayDetachItselfWhileBeingNotified(): void
    {
        $selfDetaching = new SelfDetachingClient($this->server);
        $other = new RecordingClient($this->server);

        $this->server->notify();

        $this->assertSame(1, $selfDetaching->updates);
        $this->assertSame(1, $other->updates, 'the remaining observer was skipped');
        $this->assertSame(1, $this->observerCount($this->server));

        // and it stays detached on the next round
        $this->server->notify();

        $this->assertSame(1, $selfDetaching->updates);
        $this->assertSame(2, $other->updates);
    }

    public function testEachServerKeepsItsOwnObservers(): void
    {
        $other = new TestServer();

        $mine = new RecordingClient($this->server);
        $theirs = new RecordingClient($other);

        $this->server->notify();

        $this->assertSame(1, $mine->updates);
        $this->assertSame(0, $theirs->updates);
    }

    public function testNotifyCanBeCalledRepeatedly(): void
    {
        $client = new RecordingClient($this->server);

        $this->server->notify();
        $this->server->notify();
        $this->server->notify();

        $this->assertSame(3, $client->updates);
    }
}
