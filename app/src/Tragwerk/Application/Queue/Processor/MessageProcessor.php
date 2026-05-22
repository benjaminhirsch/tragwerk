<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Processor;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Exception\InvalidSource;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\Mapper\TreeMapper;
use Enqueue\Consumption\Result;
use Interop\Queue\Context;
use Interop\Queue\Processor;
use InvalidArgumentException;
use Tragwerk\Application\Queue\Handler;
use Tragwerk\Application\Queue\Message;

use function assert;
use function class_exists;
use function is_string;
use function is_subclass_of;

// phpcs:disable Generic.Files.LineLength.TooLong

final readonly class MessageProcessor implements Processor
{
    public function __construct(
        private TreeMapper $mapper,
        // Handlers
        private Handler\RunSetupJob $runSetupJob,
        private Handler\SendMail $sendMail,
    ) {
    }

    public function process(\Interop\Queue\Message $message, Context $context): Result
    {
        $type = $message->getProperty('type');
        assert(is_string($type));

        if (! class_exists($type) || ! is_subclass_of($type, Message::class)) {
            throw new InvalidArgumentException('Unexpected message type: ' . $type);
        }

        try {
            $parsedMessage = $this->mapper->map($type, Source::json($message->getBody()));
        } catch (MappingError | InvalidSource $e) {
            throw new InvalidArgumentException('Message mapping error for type: ' . $type, previous: $e);
        }

        match ($parsedMessage::class) {
            Message\RunSetupJob::class => $this->runSetupJob->handle($parsedMessage),
            Message\SendMail::class    => $this->sendMail->handle($parsedMessage),
            default => throw new InvalidArgumentException('Unknown message class: ' . $parsedMessage::class),
        };

        return Result::ack();
    }
}
