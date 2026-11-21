<?php

use App\Models\AiRequest;
use Illuminate\Support\Facades\Schedule;

Schedule::command('pkm:digest')->weeklyOn(1, '08:00')->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [AiRequest::class]])->daily();
