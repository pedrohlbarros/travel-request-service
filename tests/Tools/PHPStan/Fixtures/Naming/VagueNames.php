<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\Naming;

use Throwable;

final class VagueNames
{
    private int $tmp = 0;

    public function run(int $x): void
    {
        $ab = $x + $this->tmp;

        foreach ([$ab] as $k => $v) {
            unset($k, $v);
        }

        try {
        } catch (Throwable $e) {
        }

        [$a, $data] = [1, 2];

        $res = $a + $data;
        unset($res);
    }
}
