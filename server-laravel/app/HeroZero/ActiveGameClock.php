<?php
declare(strict_types=1);
namespace HeroZero;

/** Checkpoints de uptime; nunca calcula diferenças com a data civil do host. */
final class ActiveGameClock
{
    private int $uptimeNs;
    private ?int $previousNs = null;

    public function __construct(private string $directory)
    {
        $state = json_decode((string)@file_get_contents($directory.'/game_clock.json'), true);
        $this->uptimeNs = max(0, (int)($state['uptime_ns'] ??
            (max(0, (int)@file_get_contents($directory.'/uptime_accumulated')) * 1000000000)));
    }

    public function sample(int $monotonicNs): void
    {
        if ($this->previousNs !== null) {
            $this->uptimeNs += max(0, $monotonicNs - $this->previousNs);
        }
        $this->previousNs = $monotonicNs;
        $this->write('game_clock.json', json_encode(['version' => 1,
            'uptime_ns' => $this->uptimeNs], JSON_THROW_ON_ERROR));
        // Compatibilidade com leitores antigos; started_at deixa de ser usado.
        $this->write('uptime_accumulated', (string)intdiv($this->uptimeNs, 1000000000));
    }

    private function write(string $name, string $content): void
    {
        $path = $this->directory.'/'.$name;
        $temp = $path.'.'.getmypid().'.tmp';
        if (file_put_contents($temp, $content) === false || !rename($temp, $path)) {
            throw new \RuntimeException('Não foi possível gravar o relógio do jogo.');
        }
    }
}
