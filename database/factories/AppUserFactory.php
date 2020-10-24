<?php

namespace Database\Factories;

use App\Models\AppUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AppUser::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        
        $defaultAvatar = 'users/default_avatar.png';

        $a_z = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        return [
            'cid' => $a_z[rand(0,51)].($this->faker->unique()->numberBetween(3,9999999999999)),
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'nickname' => $this->faker->unique()->safeEmail,
            'avatar' => $defaultAvatar,
            'continent_code' => "SA",
            'country_code' => "CO",
            'city_gplace_id' => "ChIJKcumLf2bP44RFDmjIFVjnSM",
            'password' => bcrypt('molecule'),
            'email_verified_at' => strNowTime(),
            'status' => AppUser::STATUS_ACTIVE,
        ];
    }
}
