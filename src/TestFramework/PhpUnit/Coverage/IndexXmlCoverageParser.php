<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Infection\TestFramework\PhpUnit\Coverage;

use function array_filter;
use ArrayAccess;
use ArrayIterator;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use function implode;
use Infection\AbstractTestFramework\Coverage\CoverageLineData;
use Infection\TestFramework\Coverage\ConcreteCoverageFileData;
use Infection\TestFramework\Coverage\CoverageDoesNotExistException;
use Infection\TestFramework\Coverage\LazyCoverageFileData;
use Infection\TestFramework\Coverage\MethodLocationData;
use Infection\TestFramework\SafeQuery;
use IteratorAggregate;
use function realpath as native_realpath;
use RuntimeException;
use function Safe\file_get_contents;
use function Safe\preg_replace;
use function Safe\realpath;
use function Safe\sprintf;
use function Safe\substr;
use function str_replace;
use function trim;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
class IndexXmlCoverageParser
{
    use SafeQuery;

    private $coverageDir;

    public function __construct(string $coverageDir)
    {
        $this->coverageDir = $coverageDir;
    }

    /**
     * Parses the given PHPUnit XML coverage index report (index.xml) to collect the general
     * coverage data. Note that this data is likely incomplete an will need to be enriched to
     * contain all the desired data.
     *
     * @throws NoLineExecuted
     * @throws CoverageDoesNotExistException
     *
     * @return ConcreteCoverageFileData[]
     */
    public function parse(string $coverageXmlContent): ArrayAccess
    {
        $xPath = self::createXPath($coverageXmlContent);

        self::assertHasCoverage($xPath);

        $nodes = self::safeQuery($xPath, '//file');

        $projectSource = self::getProjectSource($xPath);

        $data = [];

        foreach ($nodes as $node) {
            $relativeFilePath = $node->getAttribute('href');
            $this->processCoverageFile($relativeFilePath, $projectSource, $data);
        }

        return new class($data) implements ArrayAccess, IteratorAggregate {
            /** @var LazyCoverageFileData[] */
            private $container = [];

            private $removed = [];

            private $lastSeen;

            public function __construct(array $data)
            {
                $this->container = $data;
            }

            public function offsetSet($offset, $value): void
            {
                throw new RuntimeException('denied');
            }

            public function offsetExists($offset)
            {
                return isset($this->container[$offset]);
            }

            public function offsetUnset($offset): void
            {
                throw new RuntimeException('denied');
            }

            public function offsetGet($offset)
            {
                if (!array_key_exists($offset, $this->container)) {
                    if (array_key_exists($offset, $this->removed)) {
                        throw new RuntimeException("Trying to get coverage for $offset");
                    }

                    return null;
                }

                $item = $this->container[$offset];
                /** @var $item LazyCoverageFileData */
                if ($item->lazyStill()) {
                    return $item;
                }

                if ($this->lastSeen === null || $offset === $this->lastSeen) {
                    $this->lastSeen = $offset;

                    return $item;
                }

                $this->removed[$this->lastSeen] = true;
                unset($this->container[$this->lastSeen]);
                $this->lastSeen = $offset;

                return $this->container[$offset];
            }

            public function getIterator()
            {
                return new ArrayIterator($this->container);
            }
        };
    }

    private static function createXPath(string $coverageContent): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML(self::removeNamespace($coverageContent));

        return new DOMXPath($dom);
    }

    /**
     * Remove namespace to work with xPath without a headache
     */
    private static function removeNamespace(string $xml): string
    {
        /** @var string $cleanedXml */
        $cleanedXml = preg_replace('/xmlns=\".*?\"/', '', $xml);

        Assert::string($cleanedXml);

        return $cleanedXml;
    }

    /**
     * @throws NoLineExecuted
     */
    private static function assertHasCoverage(DOMXPath $xPath): void
    {
        $lineCoverage = self::safeQuery($xPath, '/phpunit/project/directory[1]/totals/lines')->item(0);

        if (
            !($lineCoverage instanceof DOMElement)
            || ($coverageCount = $lineCoverage->getAttribute('executed')) === '0'
            || $coverageCount === ''
        ) {
            throw NoLineExecuted::create();
        }
    }

    /**
     * @param array<string, ConcreteCoverageFileData> $data
     *
     * @throws CoverageDoesNotExistException
     */
    private function processCoverageFile(
        string $relativeCoverageFilePath,
        string $projectSource,
        array &$data
    ): void {
        //$time = microtime(true);
        // <file name="BaseCommand.php" path="/Command">
        preg_match('/<file.*?name="([^"]+)".*?path="([^"]+)"/', file_get_contents($this->coverageDir . '/' . $relativeCoverageFilePath), $match);
        $sourceFilePath = self::retrieveSourceFilePathForFilename($match[1], $match[2], $relativeCoverageFilePath, $projectSource);
        //var_dump(microtime(true) - $time);

        /*
        $time = microtime(true);
        $xPath = self::createXPath(file_get_contents(
            realpath($this->coverageDir . '/' . $relativeCoverageFilePath)
        ));
        $sourceFilePath = self::retrieveSourceFilePath($xPath, $relativeCoverageFilePath, $projectSource);
        var_dump(microtime(true) - $time);
        */
        /*
        float(0.0011301040649414)
        float(0.031888961791992)
        */

        //var_dump($sourceFilePath2, $sourceFilePath);  die();

        $data[$sourceFilePath] = new LazyCoverageFileData(function () use ($relativeCoverageFilePath): ConcreteCoverageFileData {
            // echo $this->coverageDir . '/' . $relativeCoverageFilePath. "\n";

            $xPath = self::createXPath(file_get_contents(
                realpath($this->coverageDir . '/' . $relativeCoverageFilePath)
            ));

            $linesNode = self::safeQuery($xPath, '/phpunit/file/totals/lines')[0];

            $percentage = $linesNode->getAttribute('percent');

            if (substr($percentage, -1) === '%') {
                // In PHPUnit <6 the percentage value would take the form "0.00%" in _some_ cases.
                // For example could find both with percentage and without in
                // https://github.com/maks-rafalko/tactician-domain-events/tree/1eb23434d3a833dedb6180ead75ff983ef09a2e9
                $percentage = substr($percentage, 0, -1);
            }

            if ($percentage === '') {
                $percentage = .0;
            } else {
                Assert::numeric($percentage);

                $percentage = (float) $percentage;
            }

            if ($percentage === .0) {
                return new ConcreteCoverageFileData();
            }

            $coveredLineNodes = self::safeQuery($xPath, '/phpunit/file/coverage/line');

            if ($coveredLineNodes->length === 0) {
                return new ConcreteCoverageFileData();
            }

            $coveredMethodNodes = self::safeQuery($xPath, '/phpunit/file/class/method');

            if ($coveredMethodNodes->length === 0) {
                $coveredMethodNodes = self::safeQuery($xPath, '/phpunit/file/trait/method');
            }

            return new ConcreteCoverageFileData(
                self::collectCoveredLinesData($coveredLineNodes),
                self::collectMethodsCoverageData($coveredMethodNodes)
            );
        });
    }

    /**
     * @throws CoverageDoesNotExistException
     */
    private static function retrieveSourceFilePath(
        DOMXPath $xPath,
        string $relativeCoverageFilePath,
        string $projectSource
    ): string {
        $fileNode = self::safeQuery($xPath, '/phpunit/file')[0];

        $fileName = $fileNode->getAttribute('name');
        $relativeFilePath = $fileNode->getAttribute('path');

        if ($relativeFilePath === '') {
            // The relative path is not present for old versions of PHPUnit. As a result we parse
            // the relative path from the source file path and the XML coverage file
            $relativeFilePath = str_replace(
                sprintf('%s.xml', $fileName),
                '',
                $relativeCoverageFilePath
            );
        }

        $path = implode(
            '/',
            array_filter([$projectSource, trim($relativeFilePath, '/'), $fileName])
        );

        $realPath = native_realpath($path);

        if ($realPath === false) {
            throw CoverageDoesNotExistException::forFileAtPath($fileName, $path);
        }

        return $realPath;
    }

    /**
     * @throws CoverageDoesNotExistException
     */
    private static function retrieveSourceFilePathForFilename(
        string $fileName,
        string $relativeFilePath,
        string $relativeCoverageFilePath,
        string $projectSource
        ): string {
        if ($relativeFilePath === '') {
            // The relative path is not present for old versions of PHPUnit. As a result we parse
            // the relative path from the source file path and the XML coverage file
            $relativeFilePath = str_replace(
                    sprintf('%s.xml', $fileName),
                    '',
                    $relativeCoverageFilePath
                    );
        }

        $path = implode(
                '/',
                array_filter([$projectSource, trim($relativeFilePath, '/'), $fileName])
                );

        $realPath = native_realpath($path);

        if ($realPath === false) {
            throw CoverageDoesNotExistException::forFileAtPath($fileName, $path);
        }

        return $realPath;
    }

    /**
     * @param DOMNodeList|DOMElement[] $coveredLineNodes
     * @phpstan-param DOMNodeList<DOMElement> $coveredLineNodes
     *
     * @return array<int, array<int, CoverageLineData>>
     */
    private static function collectCoveredLinesData(DOMNodeList $coveredLineNodes): array
    {
        $data = [];

        foreach ($coveredLineNodes as $lineNode) {
            $lineNumber = $lineNode->getAttribute('nr');

            Assert::integerish($lineNumber);

            $lineNumber = (int) $lineNumber;

            /** @phpstan-var DOMNodeList<DOMElement> $coveredNodes */
            $coveredNodes = $lineNode->childNodes;

            foreach ($coveredNodes as $coveredNode) {
                if ($coveredNode->nodeName !== 'covered') {
                    continue;
                }

                $data[$lineNumber][] = CoverageLineData::withTestMethod(
                    $coveredNode->getAttribute('by')
                );
            }
        }

        return $data;
    }

    /**
     * @param DOMNodeList|DOMElement[] $methodsCoverageNodes
     * @phpstan-param DOMNodeList<DOMElement> $methodsCoverageNodes
     *
     * @return MethodLocationData[]
     */
    private static function collectMethodsCoverageData(DOMNodeList $methodsCoverageNodes): array
    {
        $methodsCoverage = [];

        foreach ($methodsCoverageNodes as $methodsCoverageNode) {
            $methodName = $methodsCoverageNode->getAttribute('name');

            $start = $methodsCoverageNode->getAttribute('start');
            $end = $methodsCoverageNode->getAttribute('end');

            Assert::integerish($start);
            Assert::integerish($end);

            $methodsCoverage[$methodName] = new MethodLocationData(
                (int) $start,
                (int) $end
            );
        }

        return $methodsCoverage;
    }

    private static function getProjectSource(DOMXPath $xPath): string
    {
        // PHPUnit >= 6
        $sourceNodes = self::safeQuery($xPath, '//project/@source');

        if ($sourceNodes->length > 0) {
            return $sourceNodes[0]->nodeValue;
        }

        // PHPUnit < 6
        return self::safeQuery($xPath, '//project/@name')[0]->nodeValue;
    }
}
