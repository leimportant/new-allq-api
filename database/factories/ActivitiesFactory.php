<?php

namespace Database\Factories;
use App\Models\Activities;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivitiesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */

    protected $model = Activities::class;

    public function definition()
    {
        return [
            'transaction_id' => $this->faker->name(),
            'user_id' => $this->faker->user_id(),
            'created_at' => now(),
            'descriptions' => $this->faker->descriptions(),
        ];
    }
}
