<?php

namespace App;

enum TeamRole: int
{
    case MEMBER = 1;
    case ADMINISTRATOR = 2;
    case OWNER = 3;

    public static function getHierarchy(): array
    {
        return [
            2 => self::MEMBER->value,
            1 => self::ADMINISTRATOR->value,
            0 => self::OWNER->value,
        ];
    }

    public static function getAllRolesAsStrings(): array
    {
        return [
            (string) self::OWNER->value,
            (string) self::ADMINISTRATOR->value,
            (string) self::MEMBER->value,
        ];
    }

    public static function getAllRolesAsIntegers(): array
    {
        return [
            self::OWNER->value,
            self::ADMINISTRATOR->value,
            self::MEMBER->value,
        ];
    }

    public static function hasHighestRole(int $grandestRole, int $role): bool
    {
        $hierarchy = self::getHierarchy();
        $hierarchySize = count($hierarchy) - 1;

        if ($grandestRole < 0 || $grandestRole > $hierarchySize) {
            return false;
        }

        if ($role < 0 || $role > $hierarchySize) {
            return false;
        }

        return $hierarchy[$grandestRole] <= $hierarchy[$role];
    }
}
