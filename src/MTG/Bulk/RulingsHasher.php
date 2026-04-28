<?php

/*
Version:     1.1
Date:        28/04/26
Name:        RulingsHasher.php
Purpose:     Provide stable content hashing for Scryfall rulings imports.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Bulk;

class RulingsHasher
{
    public function buildContentHash(
        ?string $oracleId,
        ?string $source,
        ?string $publishedAt,
        ?string $comment
    ): string
    {
        $payload = json_encode(
            [
                (string) $oracleId,
                (string) $source,
                (string) $publishedAt,
                (string) $comment
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($payload === false) :
            $payload = '';
        endif;

        return sha1($payload);
    }
}
