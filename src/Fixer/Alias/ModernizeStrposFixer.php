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

namespace PhpCsFixer\Fixer\Alias;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Analyzer\ArgumentsAnalyzer;
use PhpCsFixer\Tokenizer\Analyzer\FunctionsAnalyzer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @implements ConfigurableFixerInterface<_AutogeneratedInputConfiguration, _AutogeneratedComputedConfiguration>
 *
 * @phpstan-type _AutogeneratedInputConfiguration array{
 *  modernize_stripos?: bool
 * }
 * @phpstan-type _AutogeneratedComputedConfiguration array{
 *  modernize_stripos: bool
 * }
 */
final class ModernizeStrposFixer extends AbstractFixer implements ConfigurableFixerInterface
{
    /** @use ConfigurableFixerTrait<_AutogeneratedInputConfiguration, _AutogeneratedComputedConfiguration> */
    use ConfigurableFixerTrait;

    private const REPLACEMENTS = [
        [
            'operator' => [T_IS_IDENTICAL, '==='],
            'operand' => [T_LNUMBER, '0'],
            'replacement' => [T_STRING, 'str_starts_with'],
            'negate' => false,
        ],
        [
            'operator' => [T_IS_NOT_IDENTICAL, '!=='],
            'operand' => [T_LNUMBER, '0'],
            'replacement' => [T_STRING, 'str_starts_with'],
            'negate' => true,
        ],
        [
            'operator' => [T_IS_NOT_IDENTICAL, '!=='],
            'operand' => [T_STRING, 'false'],
            'replacement' => [T_STRING, 'str_contains'],
            'negate' => false,
        ],
        [
            'operator' => [T_IS_IDENTICAL, '==='],
            'operand' => [T_STRING, 'false'],
            'replacement' => [T_STRING, 'str_contains'],
            'negate' => true,
        ],
    ];

    private bool $modernizeStripos = false;

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Replace `strpos()` and `stripos` calls with `str_starts_with()` or `str_contains()` if possible.',
            [
                new CodeSample(
                    '<?php
if (strpos($haystack, $needle) === 0) {}
if (strpos($haystack, $needle) !== 0) {}
if (strpos($haystack, $needle) !== false) {}
if (strpos($haystack, $needle) === false) {}
',
                ),
                new CodeSample(
                    '<?php
if (strpos($haystack, $needle) === 0) {}
if (strpos($haystack, $needle) !== 0) {}
if (strpos($haystack, $needle) !== false) {}
if (strpos($haystack, $needle) === false) {}
if (stripos($haystack, $needle) === 0) {}
if (stripos($haystack, $needle) !== 0) {}
if (stripos($haystack, $needle) !== false) {}
if (stripos($haystack, $needle) === false) {}
',
                    ['modernize_stripos' => true]
                ),
            ],
            null,
            'Risky if `strpos`, `stripos`, `str_starts_with`, `str_contains` or `strtolower` functions are overridden.'
        );
    }

    /**
     * {@inheritdoc}
     *
     * Must run before BinaryOperatorSpacesFixer, NoExtraBlankLinesFixer, NoSpacesInsideParenthesisFixer, NoTrailingWhitespaceFixer, NotOperatorWithSpaceFixer, NotOperatorWithSuccessorSpaceFixer, PhpUnitDedicateAssertFixer, SingleSpaceAfterConstructFixer, SingleSpaceAroundConstructFixer, SpacesInsideParenthesesFixer.
     * Must run after StrictComparisonFixer.
     */
    public function getPriority(): int
    {
        return 37;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_STRING) && $tokens->isAnyTokenKindsFound([T_IS_IDENTICAL, T_IS_NOT_IDENTICAL]);
    }

    public function isRisky(): bool
    {
        return true;
    }

    protected function configurePostNormalisation(): void
    {
        if (isset($this->configuration['modernize_stripos']) && true === $this->configuration['modernize_stripos']) {
            $this->modernizeStripos = true;
        }
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('modernize_stripos', 'Whether to modernize `stripos` calls as well.'))
                ->setAllowedTypes(['bool'])
                ->setDefault(false) // @TODO change to "true" on next major 4.0
                ->getOption(),
        ]);
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        $functionsAnalyzer = new FunctionsAnalyzer();
        $argumentsAnalyzer = new ArgumentsAnalyzer();

        $modernizeCandidates = [[T_STRING, 'strpos']];
        if ($this->modernizeStripos) {
            $modernizeCandidates[] = [T_STRING, 'stripos'];
        }

        for ($index = \count($tokens) - 1; $index > 0; --$index) {
            // find candidate function call
            if (!$tokens[$index]->equalsAny($modernizeCandidates, false) || !$functionsAnalyzer->isGlobalFunctionCall($tokens, $index)) {
                continue;
            }

            // assert called with 2 arguments
            $openIndex = $tokens->getNextMeaningfulToken($index);
            $closeIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openIndex);
            $arguments = $argumentsAnalyzer->getArguments($tokens, $openIndex, $closeIndex);

            if (2 !== \count($arguments)) {
                continue;
            }

            // check if part condition and fix if needed
            $compareTokens = $this->getCompareTokens($tokens, $index, -1); // look behind

            if (null === $compareTokens) {
                $compareTokens = $this->getCompareTokens($tokens, $closeIndex, 1); // look ahead
            }

            if (null !== $compareTokens) {
                $isCaseInsensitive = $tokens[$index]->equals([T_STRING, 'stripos'], false);
                $this->fixCall($tokens, $index, $compareTokens, $isCaseInsensitive);
            }
        }
    }

    /**
     * @param array{operator_index: int, operand_index: int} $operatorIndices
     */
    private function fixCall(Tokens $tokens, int $functionIndex, array $operatorIndices, bool $isCaseInsensitive): void
    {
        foreach (self::REPLACEMENTS as $replacement) {
            if (!$tokens[$operatorIndices['operator_index']]->equals($replacement['operator'])) {
                continue;
            }

            if (!$tokens[$operatorIndices['operand_index']]->equals($replacement['operand'], false)) {
                continue;
            }

            $tokens->clearTokenAndMergeSurroundingWhitespace($operatorIndices['operator_index']);
            $tokens->clearTokenAndMergeSurroundingWhitespace($operatorIndices['operand_index']);
            $tokens->clearTokenAndMergeSurroundingWhitespace($functionIndex);

            if ($replacement['negate']) {
                $negateInsertIndex = $functionIndex;

                $prevFunctionIndex = $tokens->getPrevMeaningfulToken($functionIndex);
                if ($tokens[$prevFunctionIndex]->isGivenKind(T_NS_SEPARATOR)) {
                    $negateInsertIndex = $prevFunctionIndex;
                }

                $tokens->insertAt($negateInsertIndex, new Token('!'));
                ++$functionIndex;
            }

            $tokens->insertAt($functionIndex, new Token($replacement['replacement']));

            if ($isCaseInsensitive) {
                $this->wrapArgumentsWithStrToLower($tokens, $functionIndex);
            }

            break;
        }
    }

    private function wrapArgumentsWithStrToLower(Tokens $tokens, int $functionIndex): void
    {
        $argumentsAnalyzer = new ArgumentsAnalyzer();
        $shouldAddNamespace = $tokens[$functionIndex - 1]->isGivenKind(T_NS_SEPARATOR);

        $openIndex = $tokens->getNextMeaningfulToken($functionIndex);
        $closeIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openIndex);
        $arguments = $argumentsAnalyzer->getArguments($tokens, $openIndex, $closeIndex);

        $firstArgumentIndexStart = array_key_first($arguments);
        if (!isset($arguments[$firstArgumentIndexStart])) {
            return;
        }
        $firstArgumentIndexEnd = $arguments[$firstArgumentIndexStart] + 3 + ($shouldAddNamespace ? 1 : 0);

        $isSecondArgumentTokenWhiteSpace = $tokens[array_key_last($arguments)]->isGivenKind(T_WHITESPACE);

        if ($isSecondArgumentTokenWhiteSpace) {
            $secondArgumentIndexStart = $tokens->getNextMeaningfulToken(array_key_last($arguments));
        } else {
            $secondArgumentIndexStart = array_key_last($arguments);
        }

        $secondArgumentIndexStart += 3 + ($shouldAddNamespace ? 1 : 0);
        if (!isset($arguments[array_key_last($arguments)])) {
            return;
        }
        $secondArgumentIndexEnd = $arguments[array_key_last($arguments)] + 6 + ($shouldAddNamespace ? 1 : 0) + ($isSecondArgumentTokenWhiteSpace ? 1 : 0);

        if ($shouldAddNamespace) {
            $tokens->insertAt($firstArgumentIndexStart, new Token([T_NS_SEPARATOR, '\\']));
            ++$firstArgumentIndexStart;
        }

        $tokens->insertAt($firstArgumentIndexStart, [new Token([T_STRING, 'strtolower']), new Token('(')]);
        $tokens->insertAt($firstArgumentIndexEnd, new Token(')'));

        if ($shouldAddNamespace) {
            $tokens->insertAt($secondArgumentIndexStart, new Token([T_NS_SEPARATOR, '\\']));
            ++$secondArgumentIndexStart;
        }

        $tokens->insertAt($secondArgumentIndexStart, [new Token([T_STRING, 'strtolower']), new Token('(')]);
        $tokens->insertAt($secondArgumentIndexEnd, new Token(')'));
    }

    /**
     * @param -1|1 $direction
     *
     * @return null|array{operator_index: int, operand_index: int}
     */
    private function getCompareTokens(Tokens $tokens, int $offsetIndex, int $direction): ?array
    {
        $operatorIndex = $tokens->getMeaningfulTokenSibling($offsetIndex, $direction);

        if (null !== $operatorIndex && $tokens[$operatorIndex]->isGivenKind(T_NS_SEPARATOR)) {
            $operatorIndex = $tokens->getMeaningfulTokenSibling($operatorIndex, $direction);
        }

        if (null === $operatorIndex || !$tokens[$operatorIndex]->isGivenKind([T_IS_IDENTICAL, T_IS_NOT_IDENTICAL])) {
            return null;
        }

        $operandIndex = $tokens->getMeaningfulTokenSibling($operatorIndex, $direction);

        if (null === $operandIndex) {
            return null;
        }

        $operand = $tokens[$operandIndex];

        if (!$operand->equals([T_LNUMBER, '0']) && !$operand->equals([T_STRING, 'false'], false)) {
            return null;
        }

        $precedenceTokenIndex = $tokens->getMeaningfulTokenSibling($operandIndex, $direction);

        if (null !== $precedenceTokenIndex && $this->isOfHigherPrecedence($tokens[$precedenceTokenIndex])) {
            return null;
        }

        return ['operator_index' => $operatorIndex, 'operand_index' => $operandIndex];
    }

    private function isOfHigherPrecedence(Token $token): bool
    {
        static $operatorsKinds = [
            T_DEC,                 // --
            T_INC,                 // ++
            T_INSTANCEOF,          // instanceof
            T_IS_GREATER_OR_EQUAL, // >=
            T_IS_SMALLER_OR_EQUAL, // <=
            T_POW,                 // **
            T_SL,                  // <<
            T_SR,                  // >>
        ];

        static $operatorsPerContent = [
            '!',
            '%',
            '*',
            '+',
            '-',
            '.',
            '/',
            '<',
            '>',
            '~',
        ];

        return $token->isGivenKind($operatorsKinds) || $token->equalsAny($operatorsPerContent);
    }
}
