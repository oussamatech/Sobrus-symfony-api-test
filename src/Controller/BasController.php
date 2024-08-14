<?php

namespace App\Controller;

trait BasController
{
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
        return $d && $d->format('Y-m-d H:i:s') === $date;
    }

}