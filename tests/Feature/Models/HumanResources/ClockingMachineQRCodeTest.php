<?php

use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

test('it is configured as an expiring clocking machine QR code', function () {
    $qrCode = new ClockingMachineQRCode();

    expect($qrCode->getTable())->toBe('clocking_machine_qr_codes')
        ->and($qrCode->getCasts())->toHaveKey('expires_at', 'datetime')
        ->and($qrCode->clockingMachine())->toBeInstanceOf(BelongsTo::class)
        ->and($qrCode->clockingMachine()->getRelated())->toBeInstanceOf(ClockingMachine::class)
        ->and((new ClockingMachine())->clockingMachineQrCodes())->toBeInstanceOf(HasMany::class)
        ->and((new ClockingMachine())->clockingMachineQrCodes()->getRelated())->toBeInstanceOf(ClockingMachineQRCode::class);
});
