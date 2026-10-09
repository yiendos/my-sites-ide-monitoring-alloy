<?php

namespace Yiendos\MySitesIde\Monitoring\Alloy\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drives the alloy compose service from the host. Every `docker compose`
 * call runs from the IDE root, as the my-sites-ide CLI is run there.
 */
trait InteractsWithAlloy
{
    /**
     * Whether the alloy container is up
     *
     * @return bool
     */
    protected function running(): bool
    {
        return trim((string) shell_exec('docker compose ps -q --status running alloy 2>/dev/null')) !== '';
    }

    /**
     * Runs `docker compose <arguments>`, echoing it first like the core commands do
     *
     * @param OutputInterface $output
     * @param string $arguments
     * @return int the exit code
     */
    protected function compose(OutputInterface $output, string $arguments): int
    {
        $output->writeLn("docker compose {$arguments}");
        passthru("docker compose {$arguments}", $code);

        return $code;
    }

    /**
     * The group that owns the Docker socket inside the container, which the
     * non-root alloy user joins (group_add in docker-compose.yml) to read
     * it: root (0) under Docker Desktop, the host's docker group on Linux.
     * DOCKER_SOCKET_GID in the root .env wins.
     *
     * @return void
     */
    protected function socketGroup(): void
    {
        if (getenv('DOCKER_SOCKET_GID') !== false) {
            return;
        }

        $gid = PHP_OS_FAMILY === 'Linux' ? @filegroup('/var/run/docker.sock') : false;

        putenv('DOCKER_SOCKET_GID=' . ($gid === false ? 0 : $gid));
    }
}
