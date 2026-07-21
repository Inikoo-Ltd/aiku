<?php

use App\Actions\HumanResources\ClockingMachine\StoreClockingMachineQRCode;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class CollidingStoreClockingMachineQRCode extends StoreClockingMachineQRCode
{
    protected static array $hashes = [];

    public static function useHashes(string ...$hashes): void
    {
        self::$hashes = $hashes;
    }

    protected static function generateHash(): string
    {
        return array_shift(self::$hashes);
    }
}

beforeEach(function () {
    Schema::dropIfExists('clocking_machine_qr_codes');

    Schema::create('clocking_machine_qr_codes', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedSmallInteger('clocking_machine_id');
        $table->string('label')->nullable();
        $table->string('hash')->unique();
        $table->dateTimeTz('expires_at');
        $table->timestampsTz();
    });
});

afterEach(function () {
    Schema::dropIfExists('clocking_machine_qr_codes');
});

test('it stores an expiring QR code for a clocking machine', function () {
    $clockingMachine = new ClockingMachine();
    $clockingMachine->id = 1;
    $clockingMachine->exists = true;

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label'      => 'Main entrance',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect($qrCode)
        ->clocking_machine_id->toBe($clockingMachine->id)
        ->label->toBe('Main entrance')
        ->expires_at->equalTo(now()->addMinutes(15));

    expect($qrCode->hash)
        ->toMatch('/^[a-f0-9]{8}$/')
        ->and($qrCode->exists)->toBeTrue();
});

test('it generates a readable label when one is not provided', function () {
    $clockingMachine = new ClockingMachine();
    $clockingMachine->id = 1;
    $clockingMachine->exists = true;

    $qrCode = StoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'expires_at' => now()->addMinutes(15),
    ]);

    expect($qrCode->label)->toMatch('/^[a-z]+-[a-z]+$/');
});

test('it requires a valid expiry timestamp', function () {
    $errors = Validator::make(
        ['expires_at' => 'invalid'],
        StoreClockingMachineQRCode::make()->rules()
    )->errors();

    expect($errors)
        ->has('expires_at')->toBeTrue()
        ->has('label')->toBeFalse();
});

test('it regenerates the hash when it collides', function () {
    $clockingMachine = new ClockingMachine();
    $clockingMachine->id = 1;
    $clockingMachine->exists = true;

    $clockingMachine->clockingMachineQrCodes()->create([
        'label'      => 'Existing QR code',
        'hash'       => 'aaaaaaaa',
        'expires_at' => now()->addMinutes(15),
    ]);

    CollidingStoreClockingMachineQRCode::useHashes('aaaaaaaa', 'bbbbbbbb');

    $qrCode = CollidingStoreClockingMachineQRCode::make()->handle($clockingMachine, [
        'label'      => 'New QR code',
        'expires_at' => now()->addMinutes(15),
    ]);

    expect($qrCode->hash)->toBe('bbbbbbbb');
});
