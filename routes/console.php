<?php

use Illuminate\Support\Facades\Schedule;

// Backup harian; retensi diurus oleh perintahnya sendiri.
Schedule::command('backup:run')->dailyAt('02:00');

// Pengingat harian pagi hari, sebelum jam kerja dimulai.
Schedule::command('reminders:send')->dailyAt('07:00');
