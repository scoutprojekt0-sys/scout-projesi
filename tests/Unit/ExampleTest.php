<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
    /**
     * User model için örnek test.
     */
    public function test_user_model_can_be_instantiated(): void
    {
        $user = new \App\Models\User();
        $this->assertInstanceOf(\App\Models\User::class, $user);
    }
}
