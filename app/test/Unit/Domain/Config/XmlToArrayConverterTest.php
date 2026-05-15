<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Config;

use DOMDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Config\XmlToArrayConverter;

final class XmlToArrayConverterTest extends TestCase
{
    private XmlToArrayConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new XmlToArrayConverter();
    }

    /** @return array<string, mixed> */
    private function convert(string $xml): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        return $this->converter->convert($dom);
    }

    #[Test]
    public function pluralWrapperBecomesFlattened(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="a" type="php:8.5" root="/"/>
                    <application name="b" type="php:8.5" root="/"/>
                </applications>
                <routes/>
            </project>
        XML);

        self::assertIsArray($result['applications']);
        self::assertIsList($result['applications']);
        self::assertCount(2, $result['applications']);
        self::assertSame('a', $result['applications'][0]['name']);
        self::assertSame('b', $result['applications'][1]['name']);
    }

    #[Test]
    public function singleItemInPluralWrapperRemainsAList(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="only" type="php:8.5" root="/"/>
                </applications>
                <routes/>
            </project>
        XML);

        self::assertIsList($result['applications']);
        self::assertCount(1, $result['applications']);
    }

    #[Test]
    public function kebabCaseAttributeIsCamelCased(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <mounts>
                            <mount name="s" source="local" path="s" clone-from-parent="true"/>
                        </mounts>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        $mount = $result['applications'][0]['mounts'][0];
        self::assertIsArray($mount);
        self::assertArrayHasKey('cloneFromParent', $mount);
    }

    #[Test]
    public function booleanStringTrueIsCastToBool(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <mounts>
                            <mount name="s" source="local" path="s" clone-from-parent="true"/>
                        </mounts>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        self::assertTrue($result['applications'][0]['mounts'][0]['cloneFromParent']);
    }

    #[Test]
    public function booleanStringFalseIsCastToBool(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <mounts>
                            <mount name="s" source="local" path="s" clone-from-parent="false"/>
                        </mounts>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        self::assertFalse($result['applications'][0]['mounts'][0]['cloneFromParent']);
    }

    #[Test]
    public function cdataTextBecomesValueKey(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <hooks>
                            <hook type="build"><![CDATA[composer install]]></hook>
                        </hooks>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        self::assertSame('composer install', $result['applications'][0]['hooks'][0]['value']);
    }

    #[Test]
    public function listContainerChildrenGetPluralKey(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <web>
                            <location path="/" root="public"/>
                        </web>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        $web = $result['applications'][0]['web'];
        self::assertIsArray($web);
        self::assertArrayHasKey('locations', $web);
        self::assertArrayNotHasKey('location', $web);
        self::assertIsList($web['locations']);
    }

    #[Test]
    public function multipleLocationsAreMappedToList(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <web>
                            <location path="/" root="public"/>
                            <location path="/api" root="api"/>
                        </web>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        $web = $result['applications'][0]['web'];
        self::assertIsArray($web);
        $locations = $web['locations'];
        self::assertIsArray($locations);
        self::assertCount(2, $locations);
    }

    #[Test]
    public function singleNonListChildIsUnpacked(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <web>
                            <location path="/" root="public"/>
                        </web>
                    </application>
                </applications>
                <routes/>
            </project>
        XML);

        // <web> is a single non-list child → unpacked to array (not wrapped in outer array)
        $app = $result['applications'][0];
        self::assertIsArray($app);
        self::assertArrayHasKey('web', $app);
        $web = $app['web'];
        self::assertIsArray($web);
        self::assertIsList($web['locations']);
    }

    #[Test]
    public function attributesAreMappedAsScalars(): void
    {
        $result = $this->convert(<<<'XML'
            <project>
                <applications>
                    <application name="My App" type="php:8.5" root="/app"/>
                </applications>
                <routes/>
            </project>
        XML);

        $app = $result['applications'][0];
        self::assertSame('My App', $app['name']);
        self::assertSame('php:8.5', $app['type']);
        self::assertSame('/app', $app['root']);
    }
}
