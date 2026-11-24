<?php
declare(strict_types=1);
namespace HeroZero;

/** Calendário de bónus. Tipos e multiplicadores correspondem a BonusInfo do cliente. */
final class BonusEvents
{
    public const TYPES = [1 => 'Energia de missões', 5 => 'Unidades de treino',
        7 => 'XP de missões', 8 => 'Moedas de missões', 10 => 'Energia de treino'];

    public static function today(): string { return Live::gameNow()->format('Y-m-d'); }
    public static function midnight(): int { return Live::gameNow()->setTime(0, 0)->getTimestamp(); }
    public static function dateOf(int $timestamp): string
    {
        return (new \DateTimeImmutable('@'.$timestamp))->setTimezone(Live::gameTimezone())->format('Y-m-d');
    }

    public static function factor(int $type): int
    {
        return (int)(Db::value('SELECT multiplier FROM bonus_event_days WHERE bonus_date = ? AND bonus_type = ?',
            [self::today(), $type]) ?? 1);
    }

    public static function calendar(string $start, int $days): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $start, Live::gameTimezone());
        if (!$date || $date->format('Y-m-d') !== $start || $days < 1 || $days > 365) {
            throw new \InvalidArgumentException('Data inválida ou duração fora de 1–365 dias.');
        }
        $out = [];
        for ($i = 0; $i < $days; $i++) $out[] = $date->modify('+'.$i.' days')->format('Y-m-d');
        return $out;
    }

    public static function create(string $name, string $start, int $days, array $types, int $multiplier = 2): int
    {
        $dates = self::calendar($start, $days);
        $types = array_unique(array_map('intval', $types));
        if (trim($name) === '' || mb_strlen($name) > 100 || !$types || $multiplier < 2 || $multiplier > 5
            || array_diff($types, array_keys(self::TYPES)) || $start < self::today()) {
            throw new \InvalidArgumentException('Nome, início, tipos ou multiplicador inválidos.');
        }
        return Missiles::transaction([], function () use ($name, $start, $days, $types, $multiplier, $dates): int {
            Db::exec('INSERT INTO bonus_events (name, start_date, end_date, enabled) VALUES (?, ?, ?, 1)',
                [trim($name), $start, end($dates)]);
            $id = (int)Db::pdo()->lastInsertId();
            foreach ($dates as $date) foreach ($types as $type) {
                // UNIQUE(data,tipo) impede empilhar acidentalmente o mesmo bónus.
                Db::exec('INSERT INTO bonus_event_days (event_id, bonus_date, bonus_type, multiplier) VALUES (?, ?, ?, ?)',
                    [$id, $date, $type, $multiplier]);
            }
            return $id;
        });
    }

    public static function cancel(int $id): void
    {
        Missiles::transaction([], function () use ($id): void {
            if (!Db::row('SELECT id FROM bonus_events WHERE id = ? FOR UPDATE', [$id])) {
                throw new \InvalidArgumentException('Evento inexistente.');
            }
            Db::exec('DELETE FROM bonus_event_days WHERE event_id = ?', [$id]);
            Db::exec('UPDATE bonus_events SET enabled = 0 WHERE id = ?', [$id]);
        });
    }

    public static function payload(): array
    {
        // Só anunciar eventos activos. Incluir o calendário inteiro permite mostrar Dia 1..X.
        $rows = Db::rows('SELECT d.* FROM bonus_event_days d JOIN bonus_events e ON e.id = d.event_id
            WHERE e.enabled = 1 AND e.start_date <= ? AND e.end_date >= ? ORDER BY d.bonus_date, d.bonus_type',
            [self::today(), self::today()]);
        $data = [];
        foreach ($rows as $row) {
            $date = new \DateTimeImmutable($row['bonus_date'].' 12:00:00', Live::gameTimezone());
            $data[] = ['event_id' => null, 'date' => $date->getTimestamp(),
                'dateFormatted' => $row['bonus_date'], 'type' => (int)$row['bonus_type'], 'value' => (int)$row['multiplier']];
        }
        return ['bonus_datas' => $data];
    }
}
