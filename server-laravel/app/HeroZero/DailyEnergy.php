<?php
declare(strict_types=1);
namespace HeroZero;

/** API dos patamares de energia, mantida para missões e scripts existentes. */
final class DailyEnergy
{
    public static function catalog(int $threshold): array { return DailyMilestones::catalog(DailyMilestones::ENERGY,$threshold); }
    public static function xpReward(int $level, int $percent): int { return DailyMilestones::xpReward($level,$percent); }
    public static function record(int $cid, int $amount): void { DailyMilestones::record($cid,$amount,DailyMilestones::ENERGY); }
    public static function attach(array $data, int $cid): array { return DailyMilestones::attach($data,$cid,DailyMilestones::ENERGY); }
    public static function claim(int $cid, int $id, bool $discard): void { DailyMilestones::claim($cid,$id,$discard,DailyMilestones::ENERGY); }
}
