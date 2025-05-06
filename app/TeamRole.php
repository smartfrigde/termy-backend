<?php

namespace App;

enum TeamRole: int
{
    case MEMBER = 1;
    case ADMINISTRATOR = 2;
    case OWNER = 3;

    public static function getAllRoles()
    {
        return [
            self::OWNER,
            self::ADMINISTRATOR,
            self::MEMBER,
        ];
    }
}
