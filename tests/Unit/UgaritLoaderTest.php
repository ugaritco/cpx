<?php

use Cpx\Runtime\Context;
use Cpx\Runtime\UgaritLoader;

test('it does not support projects missing the bootstrap file', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');
    file_put_contents("{$root}/scribe", '<?php');

    expect((new UgaritLoader)->supports(new Context($root, $root)))->toBeFalse();
});

test('it boots the console kernel and exposes the application', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');
    mkdir("{$root}/bootstrap", 0755, true);
    file_put_contents("{$root}/bootstrap/app.php", <<<'PHP'
    <?php

    return new class
    {
        public ?string $made = null;

        public object $kernel;

        public function __construct()
        {
            $this->kernel = new class
            {
                public bool $bootstrapped = false;

                public function bootstrap(): void
                {
                    $this->bootstrapped = true;
                }
            };
        }

        public function make(string $abstract): object
        {
            $this->made = $abstract;

            return $this->kernel;
        }
    };
    PHP);

    $variables = (new UgaritLoader)->boot(new Context($root, $root));

    expect($variables)->toHaveKey('app')
        ->and($variables['app']->made)->toBe('Heritage\Contracts\Console\Kernel')
        ->and($variables['app']->kernel->bootstrapped)->toBeTrue()
        ->and(defined('UGARIT_START'))->toBeTrue();
});

test('it exposes no variables when the bootstrap file is missing', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');

    expect((new UgaritLoader)->boot(new Context($root, $root)))->toBe([]);
});

test('it provides the scribe tinker command when ugarit/tinker is installed', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');
    mkdir("{$root}/vendor/ugarit/tinker", 0755, true);
    file_put_contents("{$root}/scribe", '<?php');

    expect((new UgaritLoader)->replCommand(new Context($root, $root)))
        ->toBe([PHP_BINARY, "{$root}/scribe", 'tinker']);
});

test('it provides no repl command when ugarit/tinker is missing', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');
    file_put_contents("{$root}/scribe", '<?php');

    expect((new UgaritLoader)->replCommand(new Context($root, $root)))->toBeNull();
});

test('it provides no repl command when scribe is missing', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');
    mkdir("{$root}/vendor/ugarit/tinker", 0755, true);

    expect((new UgaritLoader)->replCommand(new Context($root, $root)))->toBeNull();
});

test('it exposes no variables when the bootstrap file does not return an application', function () {
    $root = $this->temporaryDirectory('cpx-ugarit');
    mkdir("{$root}/bootstrap", 0755, true);
    file_put_contents("{$root}/bootstrap/app.php", '<?php');

    expect((new UgaritLoader)->boot(new Context($root, $root)))->toBe([]);
});
