<?php

namespace Tests\Unit;

use App\Rules\ValidPhone;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Teléfonos completos y con código de país real: lo que GHL y WhatsApp aceptan. */
class PhoneValidationTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_it_accepts_complete_numbers(string $raw): void
    {
        $this->assertNull(Phone::problem($raw), $raw);
        $this->assertTrue(Phone::isValid($raw));
    }

    public static function validNumbers(): array
    {
        return [
            'celular con +56' => ['+56912345678'],
            'celular con espacios' => ['+56 9 1234 5678'],
            'celular con guiones' => ['+56-9-1234-5678'],
            'celular sin prefijo' => ['912345678'],
            'celular con 9 y espacios' => ['9 1234 5678'],
            'fijo de Santiago' => ['+56222345678'],
            'fijo sin prefijo' => ['222345678'],
            'argentino' => ['+54 9 11 1234 5678'],
            'estadounidense' => ['+1 305 555 0123'],
            'brasileño con 00' => ['00 55 11 91234 5678'],
            'peruano' => ['+51 912 345 678'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_incomplete_or_impossible_numbers(string $raw): void
    {
        $this->assertNotNull(Phone::problem($raw), $raw);
        $this->assertFalse(Phone::isValid($raw));
    }

    public static function invalidNumbers(): array
    {
        return [
            'el de GHL rechazado: 10 dígitos con 56' => ['+5691917003'],
            'el de la reserva trabada' => ['+87121124'],
            'sin el 56 y corto' => ['87121124'],
            'celular con un dígito de más' => ['+569123456789'],
            'muy corto' => ['12345'],
            'vacío' => [''],
            'solo letras' => ['abc'],
            'extranjero sin +' => ['5491112345678'],
            'código de país inexistente' => ['+871234567890'],
            'chileno que parte con 1' => ['+56112345678'],
            '10 dígitos sin prefijo' => ['9123456789'],
        ];
    }

    public function test_the_messages_tell_reception_what_is_wrong(): void
    {
        $this->assertStringContainsString('falta', mb_strtolower(Phone::problem('+5691917003')));
        $this->assertStringContainsString('Le faltan dígitos', Phone::problem('87121124'));
        $this->assertStringContainsString('de más', Phone::problem('+569123456789'));
        $this->assertStringContainsString('extranjero', Phone::problem('5491112345678'));
    }

    public function test_the_validation_rule_ignores_blank_and_reports_with_its_prefix(): void
    {
        $rule = fn (?string $v) => Validator::make(['phone' => $v], ['phone' => [new ValidPhone('Revisa tu número: ')]]);

        $this->assertTrue($rule(null)->passes());
        $this->assertTrue($rule('+56912345678')->passes());

        $bad = $rule('+5691917003');
        $this->assertTrue($bad->fails());
        $this->assertStringStartsWith('Revisa tu número: ', $bad->errors()->first('phone'));
    }

    public function test_normalization_is_unchanged(): void
    {
        $this->assertSame('+56912345678', Phone::toE164('9 1234 5678'));
        $this->assertSame('+56912345678', Phone::toE164('+56 9 1234 5678'));
    }
}
