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

namespace Infection\TestFramework\Coverage;

use Infection\Mutation\MutationGenerator;
use Infection\TestFramework\Coverage\XmlReport\JUnit\TestFileDataAdder;
use Infection\TestFramework\Coverage\XmlReport\PhpUnitXmlCoverageFactory;
use Symfony\Component\Finder\SplFileInfo;
use Webmozart\Assert\Assert;

/**
 * Assembles a ready feed of CoveredFileData from different sources. Feeds data into MutationGenerator. Does not known about differences between adapters and what not.
 *
 * @see MutationGenerator
 * @see PhpUnitXmlCoverageFactory
 *
 * @internal
 */
final class CoveredFileDataFactory implements CoveredFileDataProvider
{
    /** @var CoveredFileDataProvider|PhpUnitXmlCoverageFactory */
    private $primaryCoverageProvider;

    /** @var iterable<SplFileInfo> */
    private $sourceFiles;

    private $filter;

    private $testFileDataAdder;

    /**
     * @param iterable<SplFileInfo> $sourceFiles
     */
    public function __construct(
        CoveredFileDataProvider $primaryCoverageProvider,
        TestFileDataAdder $testFileDataAdder,
        CoveredFileNameFilter $filter,
        iterable $sourceFiles
    ) {
        $this->primaryCoverageProvider = $primaryCoverageProvider;
        $this->testFileDataAdder = $testFileDataAdder;
        $this->filter = $filter;
        $this->sourceFiles = $sourceFiles;
    }

    /**
     * @return iterable<CoveredFileData>
     */
    public function createCoverage(): iterable
    {
        $coverageFeed = $this->primaryCoverageProvider->createCoverage();

        $filteredCoverageFeed = $this->filter->filter($coverageFeed);

        $readyCoverageFeed = $this->testFileDataAdder->addTestExecutionInfo($filteredCoverageFeed);

        if (is_array($this->sourceFiles) && $this->sourceFiles === []) {
            // The case where only covered files are considered
            return $readyCoverageFeed;
        }

        return $this->appendUncoveredFiles($readyCoverageFeed);
    }

    /**
     * @param iterable<CoveredFileData> $coverage
     *
     * @return iterable<CoveredFileData>
     */
    private function appendUncoveredFiles(iterable $coverage): iterable
    {
        $seenFiles = [];

        /** @var CoveredFileData $data */
        foreach ($coverage as $data) {
            $seenFiles[$data->getSplFileInfo()->getRealPath()] = true;

            yield $data;
        }

        // Since these are sorted sets, there should be a way to optimize.

        foreach ($this->sourceFiles as $splFileInfo) {
            $sourceFilePath = $splFileInfo->getRealPath();

            Assert::string($sourceFilePath);

            if (array_key_exists($sourceFilePath, $seenFiles)) {
                continue;
            }

            yield new CoveredFileData($splFileInfo, [new CoverageFileData()]);
        }
    }
}
