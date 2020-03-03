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

namespace Infection\TestFramework\Coverage\XmlReport;

use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\TestFramework\Coverage\CoverageFileData;
use Infection\TestFramework\Coverage\CoveredFileData;
use Infection\TestFramework\PhpUnit\Coverage\IndexXmlCoverageParser;
use Infection\TestFramework\TestFrameworkTypes;
use Symfony\Component\Finder\SplFileInfo;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class XMLLineCodeCoverageFactory
{
    private $coverageDir;
    private $coverageXmlParser;
    private $testFileDataProvider;
    /** @var SplFileInfo[] */
    private $sourceFiles;

    /**
     * @param iterable<SplFileInfo> $sourceFiles
     */
    public function __construct(
        string $coverageDir,
        IndexXmlCoverageParser $coverageXmlParser,
        TestFileDataProvider $testFileDataProvider,
        iterable $sourceFiles
    ) {
        $this->coverageDir = $coverageDir;
        $this->coverageXmlParser = $coverageXmlParser;
        $this->testFileDataProvider = $testFileDataProvider;
        $this->sourceFiles = $sourceFiles;
    }

    /**
     * @return iterable<CoveredFileData>
     */
    public function create(
        string $testFrameworkKey,
        TestFrameworkAdapter $adapter
    ): iterable {
        Assert::oneOf($testFrameworkKey, TestFrameworkTypes::TYPES);

        $testFileDataProviderService = $adapter->hasJUnitReport()
            ? $this->testFileDataProvider
            : null
        ;

        $factory = new PhpUnitXmlCoverageFactory(
            $this->coverageDir,
            $this->coverageXmlParser,
            $testFrameworkKey,
            $testFileDataProviderService
        );

        $seenFiles = [];

        // Shall something else be responsible for this?
        foreach ($factory->createCoverage() as $data) {
            // FIXME realpath() should not be required here
            $seenFiles[realpath($data->sourceFilePath)] = true;

            yield $data;
        }

        foreach ($this->sourceFiles as $splFileInfo) {
            $sourceFilePath = $splFileInfo->getRealPath();

            if (array_key_exists($sourceFilePath, $seenFiles)) {
                continue;
            }

            yield new CoveredFileData($sourceFilePath, new CoverageFileData());
        }
    }
}
