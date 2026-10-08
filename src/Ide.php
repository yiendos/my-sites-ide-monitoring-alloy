<?php

namespace Yiendos\MySitesIde\Monitoring\Alloy;

use RuntimeException;

/**
 * What this plugin needs to know about the IDE around it: where its files
 * live, which other monitoring plugins are installed, and the IDE's Docker
 * network. Everything Alloy keeps between containers goes in the IDE's
 * storage/plugins/alloy/, mounted at /storage ("storage": true in
 * composer.json).
 *
 * The IDE root comes from IDE_ROOT, which the my-sites-ide bootstrap sets
 * before any plugin command runs (and which docker-compose.yml interpolates).
 */
final class Ide
{
    public const STORAGE = 'storage/plugins/alloy';

    /**
     * Plugins\Discover's record of the installed plugins
     */
    private const PLUGINS = '_dev/cache/plugins.php';

    /**
     * The my-sites-ide project root
     *
     * @return string
     */
    public static function root(): string
    {
        $root = getenv('IDE_ROOT');

        if ($root === false || $root === '') {
            throw new RuntimeException('IDE_ROOT is not set - run this command through the my-sites-ide CLI.');
        }

        return rtrim($root, '/');
    }

    /**
     * A path in the plugin's storage on the host, e.g. conf, created on first
     * use - Docker would otherwise create the bind mount itself, owned by root
     * on Linux hosts
     *
     * @param string $path
     * @return string
     */
    public static function storage(string $path = ''): string
    {
        $storage = self::root() . '/' . self::STORAGE;

        if (!is_dir($storage)) {
            mkdir($storage, 0755, true);
        }

        return $path === '' ? $storage : "{$storage}/{$path}";
    }

    /**
     * A file shipped with this package, e.g. stubs/otlp.alloy
     *
     * @param string $file
     * @return string
     */
    public static function package(string $file = ''): string
    {
        return dirname(__DIR__) . ($file === '' ? '' : "/{$file}");
    }

    /**
     * Whether an installed plugin provides the compose service, e.g. loki -
     * read from Plugins\Discover's cache rather than a composer dependency, so
     * the monitoring plugins work in any combination
     *
     * @param string $service
     * @return bool
     */
    public static function installed(string $service): bool
    {
        $cache = self::root() . '/' . self::PLUGINS;

        if (!is_file($cache)) {
            return false;
        }

        foreach ((array) require $cache as $plugin) {
            if (in_array($service, $plugin['services'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes a generated file into storage, reporting whether it changed - a
     * running container only reads its config when it starts
     *
     * @param string $path
     * @param string $contents
     * @return bool
     */
    public static function write(string $path, string $contents): bool
    {
        $file = self::storage($path);

        if (is_file($file) && file_get_contents($file) === $contents) {
            return false;
        }

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, $contents);

        return true;
    }

    /**
     * The Docker name of the IDE's my-sites-ide network, e.g.
     * my-sites-ide_my-sites-ide - compose prefixes it with the project name
     * (the IDE folder), so it's asked for rather than guessed. Containers are
     * filtered on it, so a second IDE checkout's containers are never picked up.
     *
     * @return string
     */
    public static function network(): string
    {
        $config = json_decode((string) shell_exec('docker compose config --format json 2>/dev/null'), true);

        $name = $config['networks']['my-sites-ide']['name'] ?? null;

        if (!is_string($name) || $name === '') {
            throw new RuntimeException('Could not read the my-sites-ide network from `docker compose config` - run this from the IDE root.');
        }

        return $name;
    }
}
