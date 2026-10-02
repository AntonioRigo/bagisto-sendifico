<?php

use Tapstar\Sendifico\Rules\CedulaORuc;

/**
 * Run the rule and report whether it rejected the value. The rule translates
 * its message, so the failure closure answers with a stub that accepts that.
 */
function rejects(string $value): bool
{
    $rejected = false;

    (new CedulaORuc)->validate('vat_id', $value, function () use (&$rejected) {
        $rejected = true;

        return new class
        {
            public function translate(): void {}
        };
    });

    return $rejected;
}

it('accepts cédulas, including the newer series whose third digit is 6', function () {
    // Act & Assert
    expect(rejects('0926687856'))->toBeFalse();
    expect(rejects('1760034007'))->toBeFalse();
});

it('accepts a natural person RUC whose cédula starts with 6', function () {
    // Act & Assert
    expect(rejects('1760034007001'))->toBeFalse();
});

it('accepts public entity and private company RUCs', function () {
    // Act & Assert
    expect(rejects('1768152560001'))->toBeFalse();
    expect(rejects('1790016919001'))->toBeFalse();
    expect(rejects('0992339411001'))->toBeFalse();
});

it('rejects numbers that fail the check digit, province or length', function () {
    // Act & Assert
    expect(rejects('1760034008'))->toBeTrue();
    expect(rejects('1760034007000'))->toBeTrue();
    expect(rejects('2560034007001'))->toBeTrue();
    expect(rejects('1760034007002001'))->toBeTrue();
    expect(rejects('17600340'))->toBeTrue();
});
