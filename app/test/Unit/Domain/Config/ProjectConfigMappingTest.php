<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Config;

use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use DOMDocument;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Enum\ApplicationRuntime;
use Tragwerk\Domain\Enum\HookType;
use Tragwerk\Domain\Enum\MountSource;
use Tragwerk\Domain\Enum\ServiceRuntime;
use Tragwerk\Domain\Model\ProjectConfig;

final class ProjectConfigMappingTest extends TestCase
{
    private XmlToArrayConverter $converter;
    private TreeMapper $mapper;

    protected function setUp(): void
    {
        $this->converter = new XmlToArrayConverter();
        $this->mapper    = new MapperBuilder()
            ->allowSuperfluousKeys()
            ->allowScalarValueCasting()
            ->mapper();
    }

    private function map(string $xml): ProjectConfig
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $source = $this->converter->convert($dom);
        unset($source['xsi:noNamespaceSchemaLocation']);

        return $this->mapper->map(ProjectConfig::class, $source);
    }

    private static function projectXml(
        string $appType = 'php:8.5',
        string $appExtras = '',
        string $services = '',
        string $routeXml = '<route pattern="https://{default}" upstream="app:http"/>',
    ): string {
        return <<<XML
            <project>
                <applications>
                    <application name="app" type="{$appType}" root="/">
                        <web><location path="/" root="public"/></web>
                        {$appExtras}
                    </application>
                </applications>
                {$services}
                <routes>{$routeXml}</routes>
            </project>
        XML;
    }

    #[Test]
    public function mapsConfigReferenceXmlFile(): void
    {
        $path = __DIR__ . '/../../../../src/Tragwerk/Domain/Config/config-reference.xml';
        $dom  = new DOMDocument();
        $dom->load($path);
        $source = $this->converter->convert($dom);
        unset($source['xsi:noNamespaceSchemaLocation']);

        $config = $this->mapper->map(ProjectConfig::class, $source);

        self::assertCount(2, $config->applications);

        self::assertSame('My Test Project', $config->applications[0]->name);
        self::assertSame(ApplicationRuntime::PHP85, $config->applications[0]->type);
        self::assertCount(1, $config->applications[0]->web->locations);
        self::assertCount(2, $config->applications[0]->hooks);
        self::assertCount(1, $config->applications[0]->mounts);
        self::assertCount(2, $config->applications[0]->relationships);

        self::assertSame('Foobar', $config->applications[1]->name);
        self::assertSame(ApplicationRuntime::PHP82, $config->applications[1]->type);
        self::assertCount(1, $config->applications[1]->web->locations);
        self::assertCount(1, $config->applications[1]->hooks);
        self::assertCount(1, $config->applications[1]->mounts);
        self::assertCount(1, $config->applications[1]->relationships);

        self::assertCount(2, $config->services);
        self::assertCount(4, $config->routes);
    }

    public static function applicationRuntimeProvider(): Generator
    {
        foreach (ApplicationRuntime::cases() as $runtime) {
            yield $runtime->value => [$runtime];
        }
    }

    #[Test]
    #[DataProvider('applicationRuntimeProvider')]
    public function mapsApplicationRuntime(ApplicationRuntime $expected): void
    {
        $config = $this->map(self::projectXml(appType: $expected->value));

        self::assertSame($expected, $config->applications[0]->type);
    }

    public static function serviceRuntimeProvider(): Generator
    {
        foreach (ServiceRuntime::cases() as $runtime) {
            yield $runtime->value => [$runtime];
        }
    }

    #[Test]
    #[DataProvider('serviceRuntimeProvider')]
    public function mapsServiceRuntime(ServiceRuntime $expected): void
    {
        $services = <<<XML
            <services>
                <service name="svc" type="{$expected->value}"/>
            </services>
        XML;

        $config = $this->map(self::projectXml(services: $services));

        self::assertCount(1, $config->services);
        self::assertSame($expected, $config->services[0]->type);
    }

    public static function hookTypeProvider(): Generator
    {
        foreach (HookType::cases() as $type) {
            yield $type->value => [$type];
        }
    }

    #[Test]
    #[DataProvider('hookTypeProvider')]
    public function mapsHookType(HookType $expected): void
    {
        $hooks = <<<XML
            <hooks>
                <hook type="{$expected->value}">script</hook>
            </hooks>
        XML;

        $config = $this->map(self::projectXml(appExtras: $hooks));

        self::assertSame($expected, $config->applications[0]->hooks[0]->type);
    }

    #[Test]
    #[DataProvider('hookTypeProvider')]
    public function hookValueIsMapped(HookType $type): void
    {
        $script = 'bin/cli ' . $type->value . ':run';
        $hooks  = <<<XML
            <hooks>
                <hook type="{$type->value}"><![CDATA[{$script}]]></hook>
            </hooks>
        XML;

        $config = $this->map(self::projectXml(appExtras: $hooks));

        self::assertSame($script, $config->applications[0]->hooks[0]->value);
    }

    public static function mountSourceProvider(): Generator
    {
        foreach (MountSource::cases() as $source) {
            yield $source->value => [$source];
        }
    }

    #[Test]
    #[DataProvider('mountSourceProvider')]
    public function mapsMountSource(MountSource $expected): void
    {
        $mounts = <<<XML
            <mounts>
                <mount name="data" source="{$expected->value}" path="data"/>
            </mounts>
        XML;

        $config = $this->map(self::projectXml(appExtras: $mounts));

        self::assertSame($expected, $config->applications[0]->mounts[0]->source);
    }

    #[Test]
    public function mapsRoute(): void
    {
        $config = $this->map(self::projectXml(
            routeXml: '<route pattern="https://{default}" upstream="app:http"/>',
        ));

        $route = $config->routes[0];
        self::assertSame('https://{default}', $route->pattern);
        self::assertSame('app:http', $route->upstream);
    }

    #[Test]
    public function locationPassthruIsNullWhenAbsent(): void
    {
        $config = $this->map(self::projectXml());

        self::assertNull($config->applications[0]->web->locations[0]->passthru);
    }

    #[Test]
    public function locationPassthruIsSetWhenPresent(): void
    {
        $xml = <<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <web><location path="/" root="public" passthru="/index.php"/></web>
                    </application>
                </applications>
                <routes><route pattern="https://{default}" upstream="app:http"/></routes>
            </project>
        XML;

        $config = $this->map($xml);

        self::assertSame('/index.php', $config->applications[0]->web->locations[0]->passthru);
    }

    #[Test]
    public function locationIndexDefaultsToIndexPhpWhenAbsent(): void
    {
        $config = $this->map(self::projectXml());

        self::assertSame('index.php', $config->applications[0]->web->locations[0]->index);
    }

    #[Test]
    public function locationIndexIsSetWhenExplicit(): void
    {
        $xml = <<<'XML'
            <project>
                <applications>
                    <application name="app" type="php:8.5" root="/">
                        <web><location path="/" root="public" index="app.php"/></web>
                    </application>
                </applications>
                <routes><route pattern="https://{default}" upstream="app:http"/></routes>
            </project>
        XML;

        $config = $this->map($xml);

        self::assertSame('app.php', $config->applications[0]->web->locations[0]->index);
    }

    #[Test]
    public function serviceDiskIsNullWhenAbsent(): void
    {
        $services = '<services><service name="cache" type="redis:8"/></services>';

        $config = $this->map(self::projectXml(services: $services));

        self::assertNull($config->services[0]->disk);
    }

    #[Test]
    public function serviceDiskIsIntWhenPresent(): void
    {
        $services = '<services><service name="db" type="postgresql:18" disk="2048"/></services>';

        $config = $this->map(self::projectXml(services: $services));

        self::assertSame(2048, $config->services[0]->disk);
    }

    #[Test]
    public function mountCloneFromParentIsTrueWhenSet(): void
    {
        $mounts = '<mounts><mount name="data" source="local" path="data" clone-from-parent="true"/></mounts>';

        $config = $this->map(self::projectXml(appExtras: $mounts));

        self::assertTrue($config->applications[0]->mounts[0]->cloneFromParent);
    }

    #[Test]
    public function mountCloneFromParentIsFalseWhenSet(): void
    {
        $mounts = '<mounts><mount name="data" source="local" path="data" clone-from-parent="false"/></mounts>';

        $config = $this->map(self::projectXml(appExtras: $mounts));

        self::assertFalse($config->applications[0]->mounts[0]->cloneFromParent);
    }

    #[Test]
    public function applicationWithoutOptionalSectionsHasEmptyArrays(): void
    {
        $config = $this->map(self::projectXml());

        self::assertSame([], $config->applications[0]->hooks);
        self::assertSame([], $config->applications[0]->mounts);
        self::assertSame([], $config->applications[0]->relationships);
    }

    #[Test]
    public function multipleHooksAreMapped(): void
    {
        $hooks = <<<'XML'
            <hooks>
                <hook type="build">build script</hook>
                <hook type="deploy">deploy script</hook>
                <hook type="post_deploy">post deploy script</hook>
            </hooks>
        XML;

        $config = $this->map(self::projectXml(appExtras: $hooks));

        self::assertCount(3, $config->applications[0]->hooks);
        self::assertSame(HookType::BUILD, $config->applications[0]->hooks[0]->type);
        self::assertSame(HookType::DEPLOY, $config->applications[0]->hooks[1]->type);
        self::assertSame(HookType::POST_DEPLOY, $config->applications[0]->hooks[2]->type);
    }

    #[Test]
    public function multipleServicesAreMapped(): void
    {
        $services = <<<'XML'
            <services>
                <service name="db" type="postgresql:18" disk="1024"/>
                <service name="cache" type="redis:8"/>
            </services>
        XML;

        $config = $this->map(self::projectXml(services: $services));

        self::assertCount(2, $config->services);
        self::assertSame('db', $config->services[0]->name);
        self::assertSame('cache', $config->services[1]->name);
    }

    #[Test]
    public function multipleRoutesAreMapped(): void
    {
        $routes = <<<'XML'
            <route pattern="https://{default}" upstream="app:http"/>
            <route pattern="https://www.{default}" upstream="app:http"/>
        XML;

        $config = $this->map(self::projectXml(routeXml: $routes));

        self::assertCount(2, $config->routes);
        self::assertSame('https://{default}', $config->routes[0]->pattern);
        self::assertSame('https://www.{default}', $config->routes[1]->pattern);
    }

    #[Test]
    public function relationshipNameAndTargetAreMapped(): void
    {
        $relationships = <<<'XML'
            <relationships>
                <relationship name="database" target="db" endpoint="postgresql"/>
                <relationship name="cache" target="redis-svc" endpoint="redis"/>
            </relationships>
        XML;

        $config = $this->map(self::projectXml(appExtras: $relationships));

        self::assertCount(2, $config->applications[0]->relationships);
        self::assertSame('database', $config->applications[0]->relationships[0]->name);
        self::assertSame('db', $config->applications[0]->relationships[0]->target);
        self::assertSame('cache', $config->applications[0]->relationships[1]->name);
    }
}
