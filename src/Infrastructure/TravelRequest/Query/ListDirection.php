<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Query;

enum ListDirection: string
{
    case Older = 'older';
    case Newer = 'newer';
}
