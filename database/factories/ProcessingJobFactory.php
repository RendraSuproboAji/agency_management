<?php

namespace Database\Factories;

use App\Models\ProcessingJob;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessingJob>
 */
class ProcessingJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'capture_session_id' => null,
            'kind' => 'splat_training',
            'status' => 'queued',
            'machine' => 'workstation-01 (RTX 4090)',
            'started_at' => null,
            'finished_at' => null,
            'output_size_gb' => null,
            'notes' => null,
        ];
    }
}
