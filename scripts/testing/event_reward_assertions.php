<?php
declare(strict_types=1);
/** Verifica o contrato comum dos equipamentos novos, sem alterar dados. */
function assertEventRewardEquipment(array $item, int $cid): void
{
    $type = (int)$item['type'];
    if ($type < 1 || $type > 8) return;
    $level = (int)$item['required_level'];
    $stats = array_map(static fn($key) => (int)$item[$key],
        ['stat_stamina', 'stat_strength', 'stat_critical_rating', 'stat_dodge_rating']);
    $budget = (int)ceil(($level * 1.6 + 2 + $level ** 2 * .004) * 2.7)
        + HeroZero\Hideout::kyberneticBonuses($cid)['item_flat'];
    if ((int)$item['quality'] !== 3 || (int)$item['ts_availability_start'] !== 15
        || (int)$item['ts_availability_end'] !== ($type <= 7 ? 1 : 0)
        || ($type === 8 ? array_sum($stats) !== 0 : (array_sum($stats) !== $budget || count(array_filter($stats)) < 3))
        || ($type === 8 && ((int)$item['item_level'] !== (int)HeroZero\Db::value('SELECT level FROM `character` WHERE id=?',[$cid])
            || (int)$item['charges'] !== HeroZero\Missiles::PACKAGE_CHARGES
            || HeroZero\Missiles::damage((int)$item['item_level']) <= 0))
        || (int)$item['stat_weapon_damage'] !== ($type === 6 ? (int)ceil($level * 1.035) + 2 : 0)) {
        throw new RuntimeException('Prémio inválido: fórmula épica da loja, borda dourada ou espaço livre.');
    }
}
