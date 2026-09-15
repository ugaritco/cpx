<?php

use Cpx\Packages\Package;

test('it parses a package target without a version constraint', function () {
    $package = Package::parse('ugarit/pint');

    expect($package->vendor)->toBe('ugarit')
        ->and($package->name)->toBe('pint')
        ->and($package->version)->toBeNull()
        ->and($package->versionName())->toBe('latest')
        ->and($package->fullPackageString())->toBe('ugarit/pint');
});

test('it preserves version constraints and stability flags', function (string $target, string $version) {
    $package = Package::parse($target);

    expect($package->version)->toBe($version)
        ->and($package->fullPackageString())->toBe($target);
})->with([
    ['ugarit/pint:^1.2', '^1.2'],
    ['ugarit/pint:1.0.0', '1.0.0'],
    ['ugarit/pint:dev-main', 'dev-main'],
    ['ugarit/pint:^1@dev', '^1@dev'],
]);

test('it accepts composer package names with supported punctuation', function (string $target) {
    $package = Package::parse($target);

    expect($package->fullPackageString())->toBe($target);
})->with([
    'vendor-name/package_name',
    'vendor.name/package-name',
    'vendor123/package.456',
]);

test('binaries filters non-string manifest bin entries', function () {
    $installDir = $this->temporaryDirectory('cpx-install');
    mkdir("{$installDir}/vendor/vendor/pkg", 0755, true);
    file_put_contents("{$installDir}/vendor/vendor/pkg/composer.json", json_encode(['bin' => ['bin/tool', 123, null]], JSON_THROW_ON_ERROR));

    expect(Package::parse('vendor/pkg')->binaries($installDir))->toBe(['tool' => 'bin/tool']);
});

test('it rejects invalid package targets', function (string $target) {
    Package::parse($target);
})->with([
    '',
    'ugarit',
    '/pint',
    'ugarit/',
    'Ugarit/pint',
    'ugarit/Pint',
    'ugarit/pint extra',
    '../ugarit/pint',
    'ugarit/../pint',
    'ugarit/pint;rm -rf',
    'ugarit/pint|cat',
    'ugarit/pint/name',
    'ugarit/pint:',
    'ugarit/pint:.',
    'ugarit/pint:..',
])->throws(InvalidArgumentException::class);

test('it derives filesystem-safe version segments from hostile constraints', function (string $target) {
    $segment = Package::parse($target)->versionName();

    expect($segment)->not->toBe('')
        ->and(preg_match('#[/\\\\:*?"<>|\s]#', $segment))->toBe(0);
})->with([
    'ugarit/pint:^1@dev',
    'ugarit/pint:~1.0',
    'ugarit/pint:>=2,<3',
    'ugarit/pint:dev-main',
    'ugarit/pint:*',
    'ugarit/pint:1.0.0|2.0.0',
]);

test('it maps an unversioned package to the literal latest segment', function () {
    expect(Package::parse('ugarit/pint')->versionName())->toBe('latest');
});

test('it derives a deterministic version segment for the same constraint', function () {
    $first = Package::parse('ugarit/pint:^1@dev')->versionName();
    $second = Package::parse('ugarit/pint:^1@dev')->versionName();

    expect($first)->toBe($second);
});

test('it disambiguates constraints that slug to the same readable prefix', function () {
    $caret = Package::parse('ugarit/pint:^1')->versionName();
    $tilde = Package::parse('ugarit/pint:~1')->versionName();

    expect($caret)->not->toBe($tilde);
});

test('it keeps the true package string while using a safe directory key', function () {
    $package = Package::parse('ugarit/pint:>=2,<3');

    expect($package->fullPackageString())->toBe('ugarit/pint:>=2,<3')
        ->and(preg_match('#[/\\\\:*?"<>|,\s]#', $package->versionName()))->toBe(0);
});
