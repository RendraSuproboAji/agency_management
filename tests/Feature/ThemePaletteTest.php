<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menambah satu warna ke palet terang tetapi lupa pasangan gelapnya adalah
 * kesalahan yang mudah terjadi dan sulit terlihat — warnanya baru salah saat
 * seseorang kebetulan memakai mode yang lain.
 */
class ThemePaletteTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    /** @return list<string> */
    private function tokensIn(string $block): array
    {
        preg_match_all('/(--color-[a-z-]+):/', $block, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function block(string $css, string $selector): string
    {
        $start = strpos($css, $selector);
        $this->assertNotFalse($start, "Blok [{$selector}] tidak ditemukan di app.css.");

        $open = strpos($css, '{', $start);
        $depth = 0;

        for ($i = $open; $i < strlen($css); $i++) {
            $depth += ($css[$i] === '{') ? 1 : (($css[$i] === '}') ? -1 : 0);

            if ($depth === 0) {
                return substr($css, $open, $i - $open);
            }
        }

        $this->fail("Blok [{$selector}] tidak tertutup.");
    }

    public function test_both_palettes_define_exactly_the_same_tokens(): void
    {
        $css = $this->css();

        $light = $this->tokensIn($this->block($css, '@theme'));
        $chosenDark = $this->tokensIn($this->block($css, 'html[data-theme="dark"]'));
        $systemDark = $this->tokensIn($this->block($css, '@media (prefers-color-scheme: dark)'));

        sort($light);
        sort($chosenDark);
        sort($systemDark);

        $this->assertNotEmpty($light);
        $this->assertSame($light, $chosenDark, 'palet gelap pilihan pengguna tidak selengkap palet terang');
        $this->assertSame($light, $systemDark, 'palet gelap dari setelan perangkat tidak selengkap palet terang');
    }

    public function test_the_dark_palettes_match_each_other_value_for_value(): void
    {
        $css = $this->css();

        $values = function (string $block): array {
            preg_match_all('/(--color-[a-z-]+):\s*([^;]+);/', $block, $matches, PREG_SET_ORDER);
            $out = [];

            foreach ($matches as [, $token, $value]) {
                $out[$token] = trim($value);
            }

            ksort($out);

            return $out;
        };

        // Dua pemicu, satu tampilan: kalau nilainya berbeda, mode gelap dari
        // setelan perangkat dan dari tombol akan terlihat tidak sama.
        $this->assertSame(
            $values($this->block($css, 'html[data-theme="dark"]')),
            $values($this->block($css, '@media (prefers-color-scheme: dark)')),
        );
    }
}
