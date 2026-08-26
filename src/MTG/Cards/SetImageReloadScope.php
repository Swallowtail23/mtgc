<?php

/*
Version:     1.0
Date:        26/08/26
Name:        SetImageReloadScope.php
Purpose:     Defines valid scopes and queries for set image reload jobs.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use InvalidArgumentException;

final class SetImageReloadScope
{
    public const PRIMARY = 'primary';
    public const ALL = 'all';

    public static function isValid(string $scope): bool
    {
        return in_array($scope, [self::PRIMARY, self::ALL], true);
    }

    public static function label(string $scope): string
    {
        return match ($scope) {
            self::PRIMARY => 'primary-language',
            self::ALL => 'all-language',
            default => throw new InvalidArgumentException("Invalid set image reload scope '$scope'"),
        };
    }

    public static function cardIdQuery(string $scope): string
    {
        return match ($scope) {
            self::PRIMARY => "SELECT id
                              FROM cards_scry
                              WHERE setcode = ? AND primary_card = 1",
            self::ALL => "SELECT id
                          FROM cards_scry
                          WHERE setcode = ?",
            default => throw new InvalidArgumentException("Invalid set image reload scope '$scope'"),
        };
    }
}
