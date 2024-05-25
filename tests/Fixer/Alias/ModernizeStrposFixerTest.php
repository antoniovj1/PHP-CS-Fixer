<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests\Fixer\Alias;

use PhpCsFixer\ConfigurationException\InvalidFixerConfigurationException;
use PhpCsFixer\Tests\Test\AbstractFixerTestCase;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @internal
 *
 * @covers \PhpCsFixer\Fixer\Alias\ModernizeStrposFixer
 */
final class ModernizeStrposFixerTest extends AbstractFixerTestCase
{
    public function testConfigure(): void
    {
        $this->fixer->configure(['modernize_stripos' => true]);

        $reflectionProperty = new \ReflectionProperty($this->fixer, 'configuration');
        $reflectionProperty->setAccessible(true);

        self::assertSame(
            ['modernize_stripos' => true],
            $reflectionProperty->getValue($this->fixer)
        );
    }

    public function testInvalidConfiguration(): void
    {
        $this->expectException(InvalidFixerConfigurationException::class);
        $this->expectExceptionMessage('[modernize_strpos] Invalid configuration: The option "invalid" does not exist. Defined options are: "modernize_stripos".');

        $this->fixer->configure(['invalid' => true]);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @dataProvider provideFixCases
     */
    public function testFix(string $expected, ?string $input = null, array $configuration = []): void
    {
        $this->fixer->configure($configuration);
        $this->doTest($expected, $input);
    }

    public static function provideFixCases(): iterable
    {
        yield 'yoda ===' => [
            '<?php if (  str_starts_with($haystack1, $needle)) {}',
            '<?php if (0 === strpos($haystack1, $needle)) {}',
        ];

        yield 'case insensitive yoda ===' => [
            '<?php if (  str_starts_with(strtolower($haystack1), strtolower($needle))) {}',
            '<?php if (0 === stripos($haystack1, $needle)) {}',
            ['modernize_stripos' => true],
        ];

        yield 'not zero yoda !==' => [
            '<?php if (  !str_starts_with($haystack2, $needle)) {}',
            '<?php if (0 !== strpos($haystack2, $needle)) {}',
        ];

        yield 'case insensitive not zero yoda !==' => [
            '<?php if (  !str_starts_with(strtolower($haystack2), strtolower($needle))) {}',
            '<?php if (0 !== stripos($haystack2, $needle)) {}',
            ['modernize_stripos' => true],
        ];

        yield 'false yoda ===' => [
            '<?php if (  !str_contains($haystack, $needle)) {}',
            '<?php if (false === strpos($haystack, $needle)) {}',
        ];

        yield 'case insensitive false yoda ===' => [
            '<?php if (  !str_contains(strtolower($haystack), strtolower($needle))) {}',
            '<?php if (false === stripos($haystack, $needle)) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php if (str_starts_with($haystack3, $needle)  ) {}',
            '<?php if (strpos($haystack3, $needle) === 0) {}',
        ];

        yield [
            '<?php if (str_starts_with(strtolower($haystack3), strtolower($needle))  ) {}',
            '<?php if (stripos($haystack3, $needle) === 0) {}',
            ['modernize_stripos' => true],
        ];

        yield 'casing call' => [
            '<?php if (str_starts_with($haystack4, $needle)  ) {}',
            '<?php if (STRPOS($haystack4, $needle) === 0) {}',
        ];

        yield 'case insensitive casing call' => [
            '<?php if (str_starts_with(strtolower($haystack4), strtolower($needle))  ) {}',
            '<?php if (STRIPOS($haystack4, $needle) === 0) {}',
            ['modernize_stripos' => true],
        ];

        yield 'leading namespace' => [
            '<?php if (\str_starts_with($haystack5, $needle)  ) {}',
            '<?php if (\strpos($haystack5, $needle) === 0) {}',
        ];

        yield 'case insensitive leading namespace' => [
            '<?php if (\str_starts_with(\strtolower($haystack5), \strtolower($needle))  ) {}',
            '<?php if (\stripos($haystack5, $needle) === 0) {}',
            ['modernize_stripos' => true],
        ];

        yield 'leading namespace with yoda' => [
            '<?php if (  \str_starts_with($haystack5, $needle)) {}',
            '<?php if (0 === \strpos($haystack5, $needle)) {}',
        ];

        yield 'case insensitive leading namespace with yoda' => [
            '<?php if (  \str_starts_with(\strtolower($haystack5), \strtolower($needle))) {}',
            '<?php if (0 === \stripos($haystack5, $needle)) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php if (!str_starts_with($haystack6, $needle)  ) {}',
            '<?php if (strpos($haystack6, $needle) !== 0) {}',
        ];

        yield [
            '<?php if (!str_starts_with(strtolower($haystack6), strtolower($needle))  ) {}',
            '<?php if (stripos($haystack6, $needle) !== 0) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php if (!\str_starts_with($haystack6, $needle)  ) {}',
            '<?php if (\strpos($haystack6, $needle) !== 0) {}',
        ];

        yield [
            '<?php if (!str_starts_with(strtolower($haystack6), strtolower($needle))  ) {}',
            '<?php if (stripos($haystack6, $needle) !== 0) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php if (  !\str_starts_with($haystack6, $needle)) {}',
            '<?php if (0 !== \strpos($haystack6, $needle)) {}',
        ];

        yield [
            '<?php if (  !\str_starts_with(\strtolower($haystack6), \strtolower($needle))) {}',
            '<?php if (0 !== \stripos($haystack6, $needle)) {}',
            ['modernize_stripos' => true],
        ];

        yield 'casing operand' => [
            '<?php if (str_contains($haystack7, $needle)  ) {}',
            '<?php if (strpos($haystack7, $needle) !== FALSE) {}',
        ];

        yield 'case insensitive casing operand' => [
            '<?php if (str_contains(strtolower($haystack7), strtolower($needle))  ) {}',
            '<?php if (stripos($haystack7, $needle) !== FALSE) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php if (!str_contains($haystack8, $needle)  ) {}',
            '<?php if (strpos($haystack8, $needle) === false) {}',
        ];

        yield [
            '<?php if (!str_contains(strtolower($haystack8), strtolower($needle))  ) {}',
            '<?php if (stripos($haystack8, $needle) === false) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php if (  !str_starts_with($haystack9, $needle)) {}',
            '<?php if (0 !== strpos($haystack9, $needle)) {}',
        ];

        yield [
            '<?php if (  !str_starts_with(strtolower($haystack9), strtolower($needle))) {}',
            '<?php if (0 !== stripos($haystack9, $needle)) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php $a = !str_starts_with($haystack9a, $needle)  ;',
            '<?php $a = strpos($haystack9a, $needle) !== 0;',
        ];

        yield [
            '<?php $a = !str_starts_with(strtolower($haystack9a), strtolower($needle))  ;',
            '<?php $a = stripos($haystack9a, $needle) !== 0;',
            ['modernize_stripos' => true],
        ];

        yield 'comments inside, no spacing' => [
            '<?php if (/* foo *//* bar */str_contains($haystack10,$a)) {}',
            '<?php if (/* foo */false/* bar */!==strpos($haystack10,$a)) {}',
        ];

        yield 'case insensitive comments inside, no spacing' => [
            '<?php if (/* foo *//* bar */str_contains(strtolower($haystack10),strtolower($a))) {}',
            '<?php if (/* foo */false/* bar */!==stripos($haystack10,$a)) {}',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php $a =   !str_contains($haystack11, $needle)?>',
            '<?php $a = false === strpos($haystack11, $needle)?>',
        ];

        yield [
            '<?php $a =   !str_contains(strtolower($haystack11), strtolower($needle))?>',
            '<?php $a = false === stripos($haystack11, $needle)?>',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php $a = $input &&   str_contains($input, $method)   ? $input : null;',
            '<?php $a = $input &&   strpos($input, $method) !== FALSE ? $input : null;',
        ];

        yield [
            '<?php $a = $input &&   str_contains(strtolower($input), strtolower($method))   ? $input : null;',
            '<?php $a = $input &&   stripos($input, $method) !== FALSE ? $input : null;',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php   !str_starts_with(strtolower($file), strtolower($needle.\DIRECTORY_SEPARATOR));',
            '<?php 0 !== stripos($file, $needle.\DIRECTORY_SEPARATOR);',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php   !str_starts_with(strtolower($file.\DIRECTORY_SEPARATOR), strtolower($needle.\DIRECTORY_SEPARATOR));',
            '<?php 0 !== stripos($file.\DIRECTORY_SEPARATOR, $needle.\DIRECTORY_SEPARATOR);',
            ['modernize_stripos' => true],
        ];

        yield [
            '<?php   !str_starts_with(strtolower($file.\DIRECTORY_SEPARATOR), strtolower($needle));',
            '<?php 0 !== stripos($file.\DIRECTORY_SEPARATOR, $needle);',
            ['modernize_stripos' => true],
        ];

        // do not fix

        yield [
            '<?php
                $x = 1;
                $x = "strpos";
                // if (false === strpos($haystack12, $needle)) {}
                /** if (false === strpos($haystack13, $needle)) {} */
            ',
        ];

        yield [
            '<?php
                $x = 1;
                $x = "stripos";
                // if (false === strpos($haystack12, $needle)) {}
                /** if (false === strpos($haystack13, $needle)) {} */
            ',
        ];

        yield 'disabled stripos (default)' => [
            '<?php if (stripos($haystack3, $needle) === 0) {}',
        ];

        yield 'disabled stripos' => [
            '<?php if (stripos($haystack3, $needle) === 0) {}',
            null,
            ['modernize_stripos' => false],
        ];

        yield 'different namespace' => [
            '<?php if (a\strpos($haystack14, $needle) === 0) {}',
        ];

        yield 'case insensitive different namespace' => [
            '<?php if (a\stripos($haystack14, $needle) === 0) {}',
        ];

        yield 'different namespace with yoda' => [
            '<?php if (0 === a\strpos($haystack14, $needle)) {}',
        ];

        yield 'case insensitive different namespace with yoda' => [
            '<?php if (0 === a\stripos($haystack14, $needle)) {}',
        ];

        yield 'non condition (hardcoded)' => [
            '<?php $x = strpos(\'foo\', \'f\');',
        ];

        yield 'case insensitive non condition (hardcoded)' => [
            '<?php $x = stripos(\'foo\', \'f\');',
        ];

        yield 'non condition' => [
            '<?php $x = strpos($haystack15, $needle) ?>',
        ];

        yield 'case insensitive non condition' => [
            '<?php $x = stripos($haystack15, $needle) ?>',
        ];

        yield 'none zero int' => [
            '<?php if (1 !== strpos($haystack16, $needle)) {}',
        ];

        yield 'case insensitive none zero int' => [
            '<?php if (1 !== stripos($haystack16, $needle)) {}',
        ];

        yield 'greater condition' => [
            '<?php if (strpos($haystack17, $needle) > 0) {}',
        ];

        yield 'case insensitive greater condition' => [
            '<?php if (stripos($haystack17, $needle) > 0) {}',
        ];

        yield 'lesser condition' => [
            '<?php if (0 < strpos($haystack18, $needle)) {}',
        ];

        yield 'case insensitive lesser condition' => [
            '<?php if (0 < stripos($haystack18, $needle)) {}',
        ];

        yield 'no argument' => [
            '<?php $z = strpos();',
        ];

        yield 'case insensitive no argument' => [
            '<?php $z = stripos();',
        ];

        yield 'one argument' => [
            '<?php if (0 === strpos($haystack1)) {}',
        ];

        yield 'case insensitive one argument' => [
            '<?php if (0 === stripos($haystack1)) {}',
        ];

        yield '3 arguments' => [
            '<?php if (0 === strpos($haystack1, $a, $b)) {}',
        ];

        yield 'case insensitive 3 arguments' => [
            '<?php if (0 === stripos($haystack1, $a, $b)) {}',
        ];

        yield 'higher precedence 1' => [
            '<?php if (4 + 0 !== strpos($haystack9, $needle)) {}',
        ];

        yield 'case insensitive higher precedence 1' => [
            '<?php if (4 + 0 !== stripos($haystack9, $needle)) {}',
        ];

        yield 'higher precedence 2' => [
            '<?php if (!false === strpos($haystack, $needle)) {}',
        ];

        yield 'case insensitive higher precedence 2' => [
            '<?php if (!false === stripos($haystack, $needle)) {}',
        ];

        yield 'higher precedence 3' => [
            '<?php $a = strpos($haystack, $needle) === 0 + 1;',
        ];

        yield 'case insensitive higher precedence 3' => [
            '<?php $a = stripos($haystack, $needle) === 0 + 1;',
        ];

        yield 'higher precedence 4' => [
            '<?php $a = strpos($haystack, $needle) === 0 > $b;',
        ];

        yield 'case insensitive higher precedence 4' => [
            '<?php $a = stripos($haystack, $needle) === 0 > $b;',
        ];
    }
}
