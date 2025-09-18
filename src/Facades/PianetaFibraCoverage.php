<?php

namespace DvSoft\PianetaFibraCoverage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DvSoft\PianetaFibraCoverage\PianetaFibraCoverage
 */
class PianetaFibraCoverage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DvSoft\PianetaFibraCoverage\PianetaFibraCoverage::class;
    }
}
