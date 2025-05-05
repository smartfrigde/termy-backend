<?php

namespace App;

enum TeamRole: int
{
    case MEMBER = 1;
    case ADMINISTRATOR = 2;
    case OWNER = 3;
}
