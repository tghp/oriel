<?php

namespace Oriel;

class Util
{

    public static function slugify(string $string): string
    {
        return sanitize_title($string);
    }

}
