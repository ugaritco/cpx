<?php

use Cpx\Cache\ExecSandboxMetadata;
use Cpx\Cache\Metadata;
use Cpx\Packages\Package;

test('it opens an empty metadata state when no metadata file exists', function () {
    $this->useIsolatedComposerHome();

    $metadata = Metadata::open();

    expect($metadata->packages)->toBe([])
        ->and($metadata->execCache)->toBe([]);
});

test('it saves readable metadata json and round trips package timestamps', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata
        ->recordUpdate(Package::parse('ugarit/pint'))
        ->recordRun(Package::parse('ugarit/pint')));

    $metadataFile = cpx_path('.cpx_metadata.json');
    $contents = file_get_contents($metadataFile);
    $metadata = Metadata::open();

    expect($contents)->not->toBeFalse()
        ->and(json_decode((string) $contents, true))->toHaveKey('packages')
        ->and($metadata->hasPackage('ugarit/pint'))->toBeTrue()
        ->and($metadata->packages['ugarit/pint']->lastUpdatedAt)->not->toBeNull()
        ->and($metadata->packages['ugarit/pint']->lastRunAt)->not->toBeNull();
});

test('it handles invalid metadata json as an empty state', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), '{invalid');

    $metadata = Metadata::open();

    expect($metadata->packages)->toBe([])
        ->and($metadata->execCache)->toBe([]);
});

test('a transaction persists and round-trips package timestamps', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun(Package::parse('ugarit/pint')));

    $metadata = Metadata::open();

    expect($metadata->hasPackage('ugarit/pint'))->toBeTrue()
        ->and($metadata->packages['ugarit/pint']->lastRunAt)->not->toBeNull();
});

test('it writes the schema version and exposes the install path', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate(Package::parse('ugarit/pint')));

    $decoded = json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true);
    $metadata = Metadata::open();

    expect($decoded['version'])->toBe(2)
        ->and($metadata->packages['ugarit/pint']->installPath())->toBe(cpx_path('ugarit/pint/latest'));
});

test('it loads a legacy metadata file without a version section', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'ugarit/pint' => ['last_updated' => '2024-01-01 00:00:00', 'last_run' => '2024-01-01 00:00:00'],
        ],
        'execCache' => [
            'sandbox' => ['packages' => ['ugarit/pint'], 'last_updated' => 1, 'last_run' => 1],
        ],
    ], JSON_THROW_ON_ERROR));

    $metadata = Metadata::open();

    expect($metadata->packages['ugarit/pint']->lastUpdatedAt)->toBe(strtotime('2024-01-01 00:00:00'))
        ->and($metadata->execCache['sandbox'])->toBeInstanceOf(ExecSandboxMetadata::class)
        ->and($metadata->execCache['sandbox']->lastRunAt)->toBe(1);
});

test('it drops packages with unparseable keys and keeps valid entries', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'ugarit/pint' => ['last_updated' => 1, 'last_run' => 1],
            'Not A Package!!' => ['last_updated' => 1, 'last_run' => 1],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    $metadata = Metadata::open();

    expect($metadata->hasPackage('ugarit/pint'))->toBeTrue()
        ->and($metadata->hasPackage('Not A Package!!'))->toBeFalse();
});

test('a write after opening persists the healed package state', function () {
    $this->useIsolatedComposerHome();

    mkdir(dirname(cpx_path('.cpx_metadata.json')), 0755, true);
    file_put_contents(cpx_path('.cpx_metadata.json'), json_encode([
        'packages' => [
            'ugarit/pint' => ['last_updated' => 1, 'last_run' => 1],
            'Not A Package!!' => ['last_updated' => 1, 'last_run' => 1],
        ],
        'execCache' => [],
    ], JSON_THROW_ON_ERROR));

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun(Package::parse('ugarit/pint')));

    $decoded = json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true);

    expect($decoded['packages'])->toHaveKey('ugarit/pint')
        ->and($decoded['packages'])->not->toHaveKey('Not A Package!!');
});

test('interleaved transactions keep every update and leave valid json', function () {
    $this->useIsolatedComposerHome();

    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordRun(Package::parse('ugarit/pint')));
    Metadata::transaction(fn (Metadata $metadata) => $metadata->recordUpdate(Package::parse('phpstan/phpstan')));

    $decoded = json_decode((string) file_get_contents(cpx_path('.cpx_metadata.json')), true);
    $metadata = Metadata::open();

    expect($decoded)->toBeArray()
        ->and($metadata->hasPackage('ugarit/pint'))->toBeTrue()
        ->and($metadata->hasPackage('phpstan/phpstan'))->toBeTrue()
        ->and($metadata->packages['ugarit/pint']->lastRunAt)->not->toBeNull()
        ->and($metadata->packages['phpstan/phpstan']->lastUpdatedAt)->not->toBeNull();
});
