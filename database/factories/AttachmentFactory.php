<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'png', 'pdf']);

        return [
            'transaction_id' => Transaction::factory(),
            'filename' => fake()->word().'.'.$extension,
            'path' => 'attachments/'.fake()->uuid().'.'.$extension,
            'mime_type' => $this->getMimeType($extension),
            'size' => fake()->numberBetween(10000, 2000000),
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => fake()->word().'.jpg',
            'path' => 'attachments/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => fake()->word().'.pdf',
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    private function getMimeType(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
