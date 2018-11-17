<?php
/*******************************************************************
 *
 * @author Oliver Schieche <schiecheo@cpan.org>
 *
 *    This file was generated using Parse::Yapphp version <<$version>>.
 *
 *        Don't edit this file, use source file instead.
 *
 *             ANY CHANGES MADE HERE WILL BE LOST !
 *
 *******************************************************************/
//------------------------------------------------------------------------------
/*<<$namespace>>*//**
 * Interface LexerInterface
 *
 * A parser requires a lexical scanner (lexer). In order to use the generated
 * parser, you'll have to pass an object implementing this interface to its
 * constructor.
 *
 * Lexers must keep track of their input and analyze it. Their `lex()` method
 * is called every time the parser requires a new token.
 */
interface LexerInterface
{
    /**
     * The lexical scanner's `lex` method is invoked any time the parser requires
     * a new token. Implementations must return an array containing the token's name
     * and its value. End of input must be signaled by returning an array with an empty
     * token name and undefined (`null`) token value.
     *
     * Examples:
     *
     * ```php
     * // Return token "T_IDENTIFIER" with semantic value "variable"
     * return ['T_IDENTIFIER', 'variable'];
     *
     * // Return a semicolon token
     * return [';', ';'];
     *
     * // Indicate end of input
     * return ['', null];
     * ```
     *
     * @param <<$driverclass>> $parser
     * @return array Token/value
     */
    public function lex(/*<<$driverclass>>*/ $parser): array;
}
