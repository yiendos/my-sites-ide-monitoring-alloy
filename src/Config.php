<?php

namespace Yiendos\MySitesIde\Monitoring\Alloy;

/**
 * Alloy's config, put together from the plugin's stubs/*.alloy for whichever
 * of the other monitoring plugins are installed. The OTLP receiver is always
 * there, so apps can point at alloy:4318 whatever's installed; each signal is
 * only passed on when its destination exists:
 *
 *   tempo       traces
 *   loki        OTLP logs, and the IDE's container logs (from the Docker socket)
 *   prometheus  OTLP metrics
 */
final class Config
{
    /**
     * The compose services Alloy can send to
     */
    public const DESTINATIONS = ['tempo', 'loki', 'prometheus'];

    /**
     * @param array<string, bool> $installed compose service => whether a plugin provides it
     * @param string $network the IDE's Docker network, whose containers' logs are collected
     */
    public function __construct(private array $installed, private string $network)
    {
    }

    /**
     * Reads which monitoring plugins are installed, and the network, from the IDE
     *
     * @return self
     */
    public static function fromIde(): self
    {
        return new self(
            array_map(Ide::installed(...), array_combine(self::DESTINATIONS, self::DESTINATIONS)),
            Ide::network(),
        );
    }

    /**
     * Where Alloy is sending, e.g. ['traces -> tempo', 'container logs -> loki']
     *
     * @return string[]
     */
    public function pipelines(): array
    {
        return array_values(array_filter([
            $this->has('tempo') ? 'traces -> tempo' : null,
            $this->has('loki') ? 'OTLP logs and container logs -> loki' : null,
            $this->has('prometheus') ? 'metrics -> prometheus' : null,
        ]));
    }

    /**
     * The config, conf/config.alloy
     *
     * @return string
     */
    public function alloy(): string
    {
        $batch = 'otelcol.processor.batch.default.input';

        $config = $this->stub('header') . strtr($this->stub('otlp'), [
            '__TRACES__' => $this->has('tempo') ? $batch : '',
            '__LOGS__' => $this->has('loki') ? $batch : '',
            '__METRICS__' => $this->has('prometheus') ? $batch : '',
            '__TRACES_OUT__' => $this->has('tempo') ? 'otelcol.exporter.otlp.tempo.input' : '',
            '__LOGS_OUT__' => $this->has('loki') ? 'otelcol.exporter.otlphttp.loki.input' : '',
            '__METRICS_OUT__' => $this->has('prometheus') ? 'otelcol.exporter.prometheus.default.input' : '',
        ]);

        foreach (self::DESTINATIONS as $service) {
            if ($this->has($service)) {
                $config .= str_replace('__NETWORK__', $this->network, $this->stub($service));
            }
        }

        return $config;
    }

    /**
     * @param string $service
     * @return bool
     */
    private function has(string $service): bool
    {
        return $this->installed[$service] ?? false;
    }

    /**
     * @param string $name
     * @return string
     */
    private function stub(string $name): string
    {
        return (string) file_get_contents(Ide::package("stubs/{$name}.alloy"));
    }
}
