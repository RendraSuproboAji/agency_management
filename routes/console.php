<?php

use Illuminate\Support\Facades\Schedule;

// Backup harian; retensi diurus oleh perintahnya sendiri.
Schedule::command('backup:run')->dailyAt('02:00');
