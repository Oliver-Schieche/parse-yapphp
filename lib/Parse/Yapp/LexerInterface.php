<?php
/**
 * @author    Oliver Schieche <oliver.schieche@check24.de>
 * @copyright 2018 CHECK24 Vergleichsportal Flüge GmbH
 */
//------------------------------------------------------------------------------
/*<<$namespace>>*//**
 * Interface LexerInterface
 */
interface LexerInterface
{
    /**
     * @param <<$package>> $parser
     * @return array
     */
    public function lex(/*<<$package>>*/ $parser): array;
}
