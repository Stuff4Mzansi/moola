<?php

namespace Database\Factories;

use App\Models\MailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MailSetting> */
class MailSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => 1, 'enabled' => false, 'host' => 'smtp.example.com', 'port' => 587, 'security' => 'starttls', 'from_address' => 'reminders@example.com', 'from_name' => 'Moola'];
    }
}
