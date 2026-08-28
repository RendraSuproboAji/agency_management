<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'uploaded_by' => null,
            'title' => 'Kontrak kerja sama',
            'category' => 'contract',
            'file_path' => 'attachments/demo/'.fake()->unique()->slug(2).'.pdf',
            'mime' => 'application/pdf',
            'size' => 204_800,
        ];
    }
}
