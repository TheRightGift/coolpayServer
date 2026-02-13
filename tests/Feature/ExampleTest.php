<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    #[Test]
    public function phpunit_boots_the_application(): void
    {
        $this->assertTrue(true);
    }
}
