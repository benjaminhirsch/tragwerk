<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Config\ConfigValidator;

final class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ConfigValidator(
            __DIR__ . '/../../../../src/Tragwerk/Domain/Config/schema.xsd',
        );
    }

    #[Test]
    public function validXmlReturnsEmptyErrorList(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '</application>'
            . '</applications>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        self::assertSame([], $this->validator->validate($xml));
    }

    #[Test]
    public function unparsableXmlReturnsErrors(): void
    {
        $errors = $this->validator->validate('<unclosed');

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function emptyStringReturnsErrors(): void
    {
        $errors = $this->validator->validate('');

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function xmlWithUnknownRootElementReturnsErrors(): void
    {
        $errors = $this->validator->validate('<?xml version="1.0"?><unknown/>');

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function xmlMissingRequiredApplicationNameReturnsErrors(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php"/></web>'
            . '</application>'
            . '</applications>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        $errors = $this->validator->validate($xml);

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function xmlMissingRoutesReturnsErrors(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php"/></web>'
            . '</application>'
            . '</applications>'
            . '</project>';

        $errors = $this->validator->validate($xml);

        self::assertNotEmpty($errors);
    }

    #[Test]
    public function semanticallyBrokenCronScheduleReturnsError(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '<crons><cron name="cleanup" command="bin/cli x" schedule="61 * * * *"/></crons>'
            . '</application>'
            . '</applications>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        $errors = $this->validator->validate($xml);

        self::assertContains('Invalid cron schedule "61 * * * *" for cron "cleanup"', $errors);
    }

    #[Test]
    public function validCronScheduleReturnsNoError(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '<crons><cron name="cleanup" command="bin/cli x" schedule="0 2 * * *"/></crons>'
            . '</application>'
            . '</applications>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        self::assertSame([], $this->validator->validate($xml));
    }

    #[Test]
    public function duplicateLocalPortReturnsError(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '</application>'
            . '</applications>'
            . '<services>'
            . '<service name="db" type="postgresql:18" local-port="55432"/>'
            . '<service name="db2" type="postgresql:18" local-port="55432"/>'
            . '</services>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        $errors = $this->validator->validate($xml);

        self::assertContains('Duplicate local-port "55432" on services "db" and "db2"', $errors);
    }

    #[Test]
    public function distinctLocalPortsReturnNoError(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '</application>'
            . '</applications>'
            . '<services>'
            . '<service name="db" type="postgresql:18" local-port="55432"/>'
            . '<service name="cache" type="redis:8" local-port="56379"/>'
            . '</services>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        self::assertSame([], $this->validator->validate($xml));
    }

    #[Test]
    public function localPortBelowRangeReturnsSchemaError(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '</application>'
            . '</applications>'
            . '<services><service name="db" type="postgresql:18" local-port="80"/></services>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';

        self::assertNotEmpty($this->validator->validate($xml));
    }

    #[Test]
    public function workerWithCommandAttributeIsValid(): void
    {
        $xml = self::projectWithWorker('<worker name="queue" command="bin/cli worker:start default"/>');

        self::assertSame([], $this->validator->validate($xml));
    }

    #[Test]
    public function workerWithScriptBodyIsValid(): void
    {
        $xml = self::projectWithWorker(
            '<worker name="mailer"><![CDATA['
            . "\n export APP_ROLE=mailer\n exec bin/cli worker:start mail\n"
            . ']]></worker>',
        );

        self::assertSame([], $this->validator->validate($xml));
    }

    #[Test]
    public function workerWithoutAnyCommandReturnsError(): void
    {
        $errors = $this->validator->validate(self::projectWithWorker('<worker name="queue"/>'));

        self::assertContains(
            'Worker "queue" has no command: set the command attribute'
            . ' or put the script in the element body',
            $errors,
        );
    }

    #[Test]
    public function workerWithBothAttributeAndBodyReturnsError(): void
    {
        $errors = $this->validator->validate(
            self::projectWithWorker('<worker name="queue" command="a"><![CDATA[b]]></worker>'),
        );

        self::assertContains(
            'Worker "queue" defines both a command attribute and a script body — use one',
            $errors,
        );
    }

    private static function projectWithWorker(string $worker): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<project>'
            . '<applications>'
            . '<application name="app" type="php:8.5" root="/">'
            . '<web><location path="/" root="public" index="index.php" passthru="/index.php"/></web>'
            . '<workers>' . $worker . '</workers>'
            . '</application>'
            . '</applications>'
            . '<routes><route pattern="{default}" upstream="app:http"/></routes>'
            . '</project>';
    }

    #[Test]
    public function errorsContainHumanReadableMessages(): void
    {
        $errors = $this->validator->validate('<?xml version="1.0"?><unknown/>');

        foreach ($errors as $error) {
            self::assertStringContainsString('(line', $error);
        }
    }
}
