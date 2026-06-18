<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use CuyZ\Valinor\Mapper\TreeMapper;
use DOMDocument;
use Throwable;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

final readonly class ProjectConfigLoader
{
    private const string CONFIG_PATH = '.tragwerk/config.xml';

    public function __construct(
        private BareRepository $bareRepository,
        private XmlToArrayConverter $xmlConverter,
        private TreeMapper $treeMapper,
    ) {
    }

    /**
     * Loads and parses the .tragwerk/config.xml from the latest commit of the given branch.
     */
    public function load(ProjectIdentifier $projectId, string $branch): ProjectConfig|null
    {
        try {
            $commits = $this->bareRepository->getCommits($projectId->toString(), $branch);
        } catch (Throwable) {
            return null;
        }

        $latestCommit = $commits[0] ?? null;
        if ($latestCommit === null) {
            return null;
        }

        $content = $this->bareRepository->getFileContent(
            $projectId->toString(),
            $latestCommit->hash,
            self::CONFIG_PATH,
        );
        if ($content === null || $content === '') {
            return null;
        }

        return $this->fromXml($content);
    }

    public function fromXml(string $xml): ProjectConfig|null
    {
        try {
            $dom = new DOMDocument();
            if (! $dom->loadXML($xml)) {
                return null;
            }

            $source = $this->xmlConverter->convert($dom);
            unset($source['xsi:noNamespaceSchemaLocation']);

            return $this->treeMapper->map(ProjectConfig::class, $source);
        } catch (Throwable) {
            return null;
        }
    }
}
