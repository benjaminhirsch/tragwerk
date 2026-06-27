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
    public function errorsContainHumanReadableMessages(): void
    {
        $errors = $this->validator->validate('<?xml version="1.0"?><unknown/>');

        foreach ($errors as $error) {
            self::assertStringContainsString('(line', $error);
        }
    }
}
