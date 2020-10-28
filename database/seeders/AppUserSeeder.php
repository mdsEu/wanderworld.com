<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\AppUser;
use App\Models\AppUserMeta;

class AppUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $defaultAvatar = 'users/default_avatar.png';

        $nFriends = $this->command->ask('How many friends do you want for Desarrollo user?');
        if(!is_numeric($nFriends)) {
            $this->command->error('Invalid value for amount of friends');
            return;    
        }
        $nFriends = intval($nFriends);
        $this->command->info('Friends for Desarrollo user: '.$nFriends);

        DB::delete('delete from app_users where 1=1;');

        $desarrollo = AppUser::create([
            'cid' => AppUser::getChatId(),
            'name' => "Desarrollo",
            'email' => 'desarrollo@mdsdigital.com',
            'nickname' => "desarrollo",
            'avatar' => $defaultAvatar,
            'continent_code' => "SA",
            'country_code' => "CO",
            'city_gplace_id' => "ChIJKcumLf2bP44RFDmjIFVjnSM",
            'password' => bcrypt('atOmicSa*12356'),
            'email_verified_at' => strNowTime(),
            'status' => AppUser::STATUS_ACTIVE,
        ]);
        $friends = AppUser::factory()
                    ->count($nFriends)
                    ->create()
                    ->map(function($friend){
                        return $friend->id;
                    });
        $desarrollo->friends()->attach($friends, ['status' => AppUser::FRIEND_STATUS_ACTIVE]);

        AppUserMeta::create([
            'user_id' => $desarrollo->id,
            'meta_key' => 'chat_key',
            'meta_value' => Str::random(50),
        ]);

        $users = AppUser::factory()
            ->times(5)
            ->hasAttached(
                AppUser::factory()->count(2),
                ['status' => AppUser::FRIEND_STATUS_ACTIVE]
            )
            ->create();

        foreach ($users as $user) {
            AppUserMeta::create([
                'user_id' => $user->id,
                'meta_key' => 'chat_key',
                'meta_value' => Str::random(50),
            ]);
        }

    }
}
