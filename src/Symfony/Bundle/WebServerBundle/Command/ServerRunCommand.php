<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebServerBundle\Command;

use Symfony\Bundle\WebServerBundle\WebServer;
use Symfony\Bundle\WebServerBundle\WebServerConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Runs Symfony application using a local web server.
 *
 * @author Michał Pipa <michal.pipa.xsolve@gmail.com>
 */
class ServerRunCommand extends ServerCommand
{
    private $documentRoot;
    private $environment;

    public function __construct($documentRoot = null, $environment = null)
    {
        $this->documentRoot = $documentRoot;
        $this->environment = $environment;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
            ))
            ->setName('server:run')
            ->setDescription('Runs a local web server')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> runs a local web server:

  <info>%command.full_name%</info>

Change the default address and port by passing them as an argument:

  <info>%command.full_name% 127.0.0.1:8080</info>

Use the <info>--docroot</info> option to change the default docroot directory:

  <info>%command.full_name% --docroot=htdocs/</info>

Specify your own router script via the <info>--router</info> option:

  <info>%command.full_name% --router=app/config/router.php</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if (null === $documentRoot = $input->getOption('docroot')) {
            if (!$this->documentRoot) {
                $io->error('The document root directory must be either passed as first argument of the constructor or through the "--docroot" input option.');

                return 1;
            }
            $documentRoot = $this->documentRoot;
        }

        if (!is_dir($documentRoot)) {
            $io->error(sprintf('The document root directory "%s" does not exist.', $documentRoot));

            return 1;
        }

        if (!$env = $this->environment) {
            if ($input->hasOption('env') && !$env = $input->getOption('env')) {
                $io->error('The environment must be either passed as second argument of the constructor or through the "--env" input option.');

                return 1;
            } else {
                $io->error('The environment must be passed as second argument of the constructor.');

                return 1;
            }
        }

        if ('prod' === $env) {
            $io->error('Running this server in production environment is NOT recommended!');
        }

        $callback = null;
        $disableOutput = false;
        if ($output->isQuiet()) {
            $disableOutput = true;
        } else {
            $callback = function ($type, $buffer) use ($output) {
                if (Process::ERR === $type && $output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            };
        }

        try {
            $server = new WebServer();
            $config = new WebServerConfig($documentRoot, $env, $input->getArgument('addressport'), $input->getOption('router'));

            $io->success(sprintf('Server listening on http://%s', $config->getAddress()));
            $io->comment('Quit the server with CONTROL-C.');

            $exitCode = $server->run($config, $disableOutput, $callback);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        return $exitCode;
    }
}
