<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Seluruh antarmuka berbahasa Indonesia, jadi pesan kesalahannya pun harus
 * begitu. APP_LOCALE memang sudah id sejak awal, tetapi tanpa berkas lang/id
 * Laravel diam-diam jatuh ke bawaan bahasa Inggris.
 */
class ValidationLanguageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, list<string>> */
    private function errorsFor(array $data, array $rules): array
    {
        try {
            Validator::make($data, $rules)->validate();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Validasi seharusnya gagal.');
    }

    public function test_the_active_locale_is_indonesian(): void
    {
        $this->assertSame('id', app()->getLocale());
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $errors = $this->errorsFor(
            ['email' => 'bukan-email'],
            ['email' => ['required', 'email'], 'title' => ['required']],
        );

        $this->assertSame('Email harus berupa alamat email yang sah.', $errors['email'][0]);
        $this->assertSame('Judul wajib diisi.', $errors['title'][0]);
    }

    public function test_field_names_are_translated_not_raw_column_names(): void
    {
        $errors = $this->errorsFor(
            [],
            ['scheduled_at' => ['required'], 'client_id' => ['required'], 'unit_price' => ['required']],
        );

        // Bukan "scheduled_at wajib diisi".
        $this->assertSame('Jadwal wajib diisi.', $errors['scheduled_at'][0]);
        $this->assertSame('Klien wajib diisi.', $errors['client_id'][0]);
        $this->assertSame('Harga satuan wajib diisi.', $errors['unit_price'][0]);
    }

    public function test_password_rule_messages_are_in_indonesian(): void
    {
        $errors = $this->errorsFor(
            ['password' => 'abc'],
            ['password' => ['required', Password::min(10)->letters()->numbers()]],
        );

        $this->assertStringContainsString('sedikitnya 10 karakter', $errors['password'][0]);
        $this->assertStringContainsString('sedikitnya satu angka', implode(' ', $errors['password']));
    }

    public function test_a_real_form_returns_indonesian_errors(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('clients.store'), ['name' => '', 'status' => '']);

        $response->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);
    }
}
