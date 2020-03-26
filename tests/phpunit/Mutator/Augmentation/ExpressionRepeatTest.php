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

namespace Infection\Tests\Mutator\Augmentation;

use Infection\Tests\Mutator\BaseMutatorTestCase;

final class ExpressionRepeatTest extends BaseMutatorTestCase
{
    /**
     * @dataProvider mutationsProvider
     *
     * @param string|string[] $expected
     */
    public function test_it_can_mutate(string $input, $expected = []): void
    {
        $this->doTest($input, $expected);
    }

    public function mutationsProvider(): iterable
    {
        yield 'It duplicates simple statement' => [
            <<<'PHP'
<?php

$a->foo();
PHP
            ,
            <<<'PHP'
<?php

$a->foo();
$a->foo();
PHP
            ,
        ];

        yield 'It duplicates complex statement' => [
            <<<'PHP'
<?php

$z = $b->bar() + [1 => $c->foo()];
PHP
            ,
            <<<'PHP'
<?php

$z = $b->bar() + [1 => $c->foo()];
$z = $b->bar() + [1 => $c->foo()];
PHP
            ,
        ];

        yield 'It duplicates closure calls' => [
            <<<'PHP'
<?php

$a = ($this->closure)();
PHP
            ,
            <<<'PHP'
<?php

$a = ($this->closure)();
$a = ($this->closure)();
PHP
            ,
        ];

        yield 'It does not mutate complex statement without method calls' => [
            <<<'PHP'
<?php

$z = $b::bar() + [1 => $c::foo()] && strpos('a', 'b');
PHP
        ];

        yield 'It does not mutate assignments with a function call right on the right' => [
            <<<'PHP'
<?php

$this->z = bar($a->bar())->z()->q();
PHP
        ];

        yield 'It does not mutate statements with new instance created' => [
            <<<'PHP'
<?php
$this->z = $a ?? new A($this->b());
PHP
        ];

        yield 'It does not mutate assignments with a static call right on the right' => [
            <<<'PHP'
<?php
$this->z = self::foo($a->bar());
PHP
        ];

        yield 'It does not mutate scalar assignments' => [
            <<<'PHP'
<?php

$a = 1;
PHP
        ];

        yield 'It does not mutate constant assignments' => [
            <<<'PHP'
<?php

$a = null;
PHP
        ];

        yield 'It does not mutate array assignments' => [
            <<<'PHP'
<?php

$a = [];
PHP
        ];

        yield 'It does not mutate variable assignments' => [
            <<<'PHP'
<?php

$a = $b;
PHP
        ];

        yield 'It does not mutate array value assignments' => [
            <<<'PHP'
<?php

$a = $b['foo']['bar'];
PHP
        ];

        yield 'It does not mutate static calls' => [
            <<<'PHP'
<?php

Any::foo();
PHP
        ];

        yield 'It does not mutate non-statements' => [
            <<<'PHP'
<?php

return 1;
PHP
        ];
    }
}
