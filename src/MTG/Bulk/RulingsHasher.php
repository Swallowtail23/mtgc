<?php

/*
Version:     1.0
Date:        22/12/25
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
    public function buildContentHash($oracleId, $source, $publishedAt, $comment)
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
