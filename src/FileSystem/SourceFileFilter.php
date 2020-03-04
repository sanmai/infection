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

namespace Infection\FileSystem;

use function explode;
use Infection\FileSystem\Finder\Iterator\RealPathFilterIterator;
use Infection\TestFramework\Coverage\CoveredFileData;
use Iterator;
use function Pipeline\take;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
final class SourceFileFilter
{
    private $sourceDirectories;
    private $excludeDirectories;
    /** @var string[] */
    private $filters;

    /**
     * @param string[] $sourceDirectories
     * @param string[] $excludeDirectories
     *
     * @return iterable<SplFileInfo>
     */
    public function __construct(
        array $sourceDirectories,
        array $excludeDirectories,
        string $filter
    ) {
        $this->sourceDirectories = $sourceDirectories;
        $this->excludeDirectories = $excludeDirectories;
        $this->filters = take(explode(',', $filter))
            ->map('trim')
            ->filter()
            ->toArray();
    }

    /**
     * @return string[]
     *
     * @see SourceFileCollector
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @param iterable<SplFileInfo&CoveredFileData> $input
     *
     * @return iterable<SplFileInfo&CoveredFileData>
     */
    public function filter(iterable $input): iterable
    {
        if ($this->filters === []) {
            return $input;
        }

        return new RealPathFilterIterator(
            $this->iterableToIterator($input),
            $this->filters,
            []
        );
    }

    /**
     * @param iterable<SplFileInfo&CoveredFileData> $input
     *
     * @return \Iterator<SplFileInfo&CoveredFileData>
     */
    private function iterableToIterator(iterable $input)
    {
        if ($input instanceof Iterator) {
            // Generator is an iterator, means most of the time we're done right here.
            return $input;
        }

        // Clause for all other cases, e.g. when testing
        return (static function () use ($input): Iterator {
            yield from $input;
        })();
    }
}
