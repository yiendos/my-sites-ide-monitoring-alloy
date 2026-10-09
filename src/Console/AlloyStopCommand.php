<?php

namespace Yiendos\MySitesIde\Monitoring\Alloy\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Monitoring\Alloy\Traits\InteractsWithAlloy;

class AlloyStopCommand extends Command
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
            ->setName('monitoring:alloy-stop')
            ->setDescription('Stop the Alloy container, leaving the rest of the IDE running')
        ;
    }

    /**
     * Stopped rather than removed, so monitoring:alloy-start brings back
     * the same container. Its data is kept in storage/plugins/alloy/
     * either way.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        if (!$this->running()) {
            $io->writeln('Alloy is not running - nothing to stop.');
            return Command::SUCCESS;
        }

        if ($this->compose($output, 'stop alloy') !== 0) {
            $io->error('Alloy did not stop - see above.');
            return Command::FAILURE;
        }

        $io->success('Alloy stopped.');

        return Command::SUCCESS;
    }
}
