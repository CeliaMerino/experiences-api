<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$environment = $_SERVER['APP_ENV'] ?? 'test';
if (!\is_string($environment)) {
    throw new RuntimeException('APP_ENV must be a string.');
}

$kernel = new Kernel($environment, (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$application = new Application($kernel);
$application->setAutoExit(false);
$application->run(
    new ArrayInput([
        'command' => 'doctrine:migrations:migrate',
        '--no-interaction' => true,
    ]),
    new NullOutput(),
);

$kernel->shutdown();
