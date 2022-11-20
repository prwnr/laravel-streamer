<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Str;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\Specifications\IdentifierSpecification;
use Prwnr\Streamer\Errors\Specifications\MatchAllSpecification;
use Prwnr\Streamer\Errors\Specifications\ReceiverSpecification;
use Prwnr\Streamer\Errors\Specifications\StreamSpecification;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\LocalListener;

class FailedMessageSpecificationsTest extends \PHPUnit\Framework\TestCase
{
    public function test_identifier_specification(): void
    {
        $specification = new IdentifierSpecification('123');

        $this->assertTrue($specification->isSatisfiedBy($this->makeMessage(['id' => '123'])));
        $this->assertFalse($specification->isSatisfiedBy($this->makeMessage(['id' => '321'])));
    }

    public function test_stream_specification(): void
    {
        $specification = new StreamSpecification('foo.bar');

        $this->assertTrue($specification->isSatisfiedBy($this->makeMessage(['stream' => 'foo.bar'])));
        $this->assertFalse($specification->isSatisfiedBy($this->makeMessage(['stream' => 'bar.foo'])));
    }

    public function test_receiver_specification(): void
    {
        $specification = new ReceiverSpecification(LocalListener::class);

        $this->assertTrue($specification->isSatisfiedBy($this->makeMessage(['receiver' => LocalListener::class])));
        $this->assertFalse(
            $specification->isSatisfiedBy($this->makeMessage(['receiver' => AnotherLocalListener::class]))
        );
    }

    public function test_match_all_specification_combined(): void
    {
        $id = new IdentifierSpecification('123');
        $stream = new StreamSpecification('foo.bar');
        $receiver = new ReceiverSpecification(LocalListener::class);

        $correctMessage = $this->makeMessage([
            'id' => '123',
            'stream' => 'foo.bar',
            'receiver' => LocalListener::class,
        ]);
        $badMessage = $this->makeMessage([
            'id' => '321',
            'stream' => 'foo.other',
            'receiver' => AnotherLocalListener::class,
        ]);

        $specification = new MatchAllSpecification($id);

        $this->assertTrue($specification->isSatisfiedBy($correctMessage));
        $this->assertFalse($specification->isSatisfiedBy($badMessage));

        $specification = new MatchAllSpecification($id, $stream);

        $this->assertTrue($specification->isSatisfiedBy($correctMessage));
        $this->assertFalse($specification->isSatisfiedBy($badMessage));

        $specification = new MatchAllSpecification($id, $receiver);

        $this->assertTrue($specification->isSatisfiedBy($correctMessage));
        $this->assertFalse($specification->isSatisfiedBy($badMessage));

        $specification = new MatchAllSpecification($stream, $receiver);

        $this->assertTrue($specification->isSatisfiedBy($correctMessage));
        $this->assertFalse($specification->isSatisfiedBy($badMessage));

        $specification = new MatchAllSpecification($id, $stream, $receiver);

        $this->assertTrue($specification->isSatisfiedBy($correctMessage));
        $this->assertFalse($specification->isSatisfiedBy($badMessage));
    }

    protected function makeMessage(array $data): FailedMessage
    {
        return new FailedMessage(
            $data['id'] ?? Str::uuid()->toString(),
            $data['stream'] ?? Str::random(),
            $data['receiver'] ?? Str::random(),
            $data['error'] ?? Str::random()
        );
    }
}
