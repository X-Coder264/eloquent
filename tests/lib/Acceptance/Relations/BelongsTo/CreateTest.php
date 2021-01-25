<?php
/*
 * Copyright 2021 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Tests\Acceptance\Relations\BelongsTo;

use App\Models\User;

class CreateTest extends TestCase
{

    public function test(): void
    {
        $user = User::factory()->create();

        $phone = $this->repository->create()->store([
            'number' => '07777123456',
            'user' => [
                'type' => 'users',
                'id' => (string) $user->getRouteKey(),
            ],
        ]);

        $this->assertTrue($phone->relationLoaded('user'));
        $this->assertTrue($user->is($phone->getRelation('user')));

        $this->assertDatabaseHas('phones', [
            'id' => $phone->getKey(),
            'number' => '07777123456',
            'user_id' => $user->getKey(),
        ]);
    }

    public function testNull(): void
    {
        $phone = $this->repository->create()->store([
            'number' => '07777123456',
            'user' => null,
        ]);

        $this->assertTrue($phone->relationLoaded('user'));
        $this->assertNull($phone->getRelation('user'));

        $this->assertDatabaseHas('phones', [
            'id' => $phone->getKey(),
            'number' => '07777123456',
            'user_id' => null,
        ]);
    }
}
