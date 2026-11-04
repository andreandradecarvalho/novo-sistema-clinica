<?php

use App\Support\SyntheticCpf;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

function syntheticCpf(): string
{
    return SyntheticCpf::generate();
}
