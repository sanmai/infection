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

use function array_key_exists;
use function count;
use Generator;
use Infection\AbstractTestFramework\Coverage\CoverageLineData;
use Infection\TestFramework\Coverage\CoverageDoesNotExistException;
use Infection\TestFramework\Coverage\CoverageFileData;
use Infection\TestFramework\Coverage\LineCodeCoverage;
use Infection\TestFramework\Coverage\NodeLineRangeData;
use function range;

/**
 * @internal
 */
final class XMLLineCodeCoverage implements LineCodeCoverage
{
    /**
     * @var CoverageFileData
     */
    private $fileCoverage;

    public function __construct(CoverageFileData $fileCoverage)
    {
        $this->fileCoverage = $fileCoverage;
    }

    public function hasTests(): bool
    {
        $coveredLineTestMethods = array_filter(
            $this->fileCoverage->byLine,
            static function (array $testMethods) {
                return count($testMethods) > 0;
            }
        );

        return count($coveredLineTestMethods) > 0;
    }

    public function getAllTestsForMutation(
        NodeLineRangeData $lineRange,
        bool $isOnFunctionSignature
    ): iterable {
        if ($isOnFunctionSignature) {
            return $this->getTestsForFunctionSignature($lineRange);
        }

        return $this->getTestsForLineRange($lineRange);
    }

    /**
     * @throws CoverageDoesNotExistException
     *
     * @return Generator<CoverageLineData>
     */
    private function getTestsForFunctionSignature(NodeLineRangeData $lineRange): iterable
    {
        foreach ($lineRange->range as $line) {
            yield from $this->getTestsForExecutedMethodOnLine($line);
        }
    }

    /**
     * @throws CoverageDoesNotExistException
     *
     * @return Generator<CoverageLineData>
     */
    private function getTestsForLineRange(NodeLineRangeData $lineRange): iterable
    {
        foreach ($lineRange->range as $line) {
            yield from $this->fileCoverage->byLine[$line] ?? [];
        }
    }

    /**
     * @throws CoverageDoesNotExistException
     *
     * @return CoverageLineData[]
     */
    private function getTestsForExecutedMethodOnLine(int $line): iterable
    {
        foreach ($this->fileCoverage->byMethod as $method => $coverageMethodData) {
            if ($line >= $coverageMethodData->startLine && $line <= $coverageMethodData->endLine) {
                /** @var int[] $allLines */
                $allLines = range($coverageMethodData->startLine, $coverageMethodData->endLine);

                foreach ($allLines as $lineInExecutedMethod) {
                    if (array_key_exists($lineInExecutedMethod, $this->fileCoverage->byLine)) {
                        foreach ($this->fileCoverage->byLine[$lineInExecutedMethod] as $coverageLineData) {
                            yield $coverageLineData;
                        }
                    }
                }

                break;
            }
        }
    }
}
