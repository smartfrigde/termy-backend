<?php

namespace App;

enum DataCategories: string
{
    case team = "team";
    case keys = "gpg_keys";
    case users = "users";
    case ssh = "ssh";

    case members = "members";
}
