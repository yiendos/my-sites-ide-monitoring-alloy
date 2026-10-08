<?php

namespace Yiendos\MySitesIde\Monitoring\Alloy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Alloy\Config;
use Yiendos\MySitesIde\Monitoring\Alloy\Ide;
use Yiendos\MySitesIde\Monitoring\Alloy\Traits\InteractsWithAlloy;

class AlloyStartCommand extends Command
{
    use InteractsWithAlloy;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('monitoring:alloy-start')
            ->setDescription('Write Alloy\'s pipelines for the monitoring plugins installed, then start Alloy')
        ;
    }

    /**
     * Writes storage/plugins/alloy/conf/config.alloy for the monitoring
     * plugins installed right now - so run it again after adding or removing
     * one. Alloy only reads its config when it starts, so a running Alloy is
     * restarted when it's changed.
     *
     * `up -d --build` is a no-op for an up-to-date container, recreates one
     * whose compose config changed (e.g. a new ALLOY_PORT), and builds the
     * image after an update to the Dockerfile.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $config = Config::fromIde();

        if (!is_dir(Ide::storage('data'))) {
            mkdir(Ide::storage('data'), 0755, true);
        }

        $changed = Ide::write('conf/config.alloy', $config->alloy());
        $wasRunning = $this->running();

        $io->writeln($config->pipelines() === []
            ? 'Nowhere to send anything yet - none of the tempo, loki or prometheus plugins are installed'
            : 'Pipelines: ' . implode(', ', $config->pipelines()));

        $this->socketGroup();

        if ($this->compose($output, 'up -d --build alloy') !== 0) {
            $io->error('Alloy did not start - see above.');
            return Command::FAILURE;
        }

        if ($changed && $wasRunning && $this->compose($output, 'restart alloy') !== 0) {
            $io->error('Alloy did not restart with the new config - see above.');
            return Command::FAILURE;
        }

        $io->success('Alloy started - send OpenTelemetry to http://alloy:4318 inside the IDE; UI at http://localhost:' . (getenv('ALLOY_PORT') ?: '12345'));

        return Command::SUCCESS;
    }
}
