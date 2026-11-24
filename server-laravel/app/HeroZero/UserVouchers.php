<?php
declare(strict_types=1);
namespace HeroZero;

/** Cupões pessoais das mensagens; não são campanhas promocionais. */
final class UserVouchers
{
    public const KEYS = ['quest_energy', 'training_sessions', 'hideout_glue', 'hideout_stone'];
    public const BOOSTERS = ['quest_booster'=>[1,'quest',86400], 'stats_booster'=>[2,'stats',86400],
        'work_booster'=>[3,'work',86400], 'sense_booster'=>[4,'sense',3600],
        'league_booster'=>[5,'league',86400], 'multitasking_booster'=>[6,'multitasking',3600],
        'hideout_booster'=>[8,'hideout',86400]];

    public static function validateReward(string $key, mixed $value): void
    {
        if (in_array($key, self::KEYS, true)) {
            if (!is_int($value) || $value <= 0 || $value > 30000) throw new \InvalidArgumentException('Quantidade de cupão inválida');
            return;
        }
        if (!isset(self::BOOSTERS[$key]) || !is_array($value) || !is_int($value['type'] ?? null)
            || !is_int($value['duration'] ?? null) || $value['duration'] <= 0 || $value['duration'] > 3650) {
            throw new \InvalidArgumentException('Energético inválido');
        }
        if (self::boosterId($key, $value['type']) === null) throw new \InvalidArgumentException('Energético fora do catálogo');
    }

    public static function boosterId(string $key, int $amount): ?string
    {
        $type = self::BOOSTERS[$key][0] ?? 0;
        foreach (Live::BOOSTER_CATALOG as $id=>$entry) if ($entry[0] === $type && $entry[1] === $amount) return $id;
        return null;
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = Db::pdo();
        $nested = $pdo->inTransaction();
        if ($nested) $pdo->exec('SAVEPOINT user_vouchers'); else $pdo->beginTransaction();
        try {
            $result = $fn();
            if ($nested) $pdo->exec('RELEASE SAVEPOINT user_vouchers'); else $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($nested) { $pdo->exec('ROLLBACK TO SAVEPOINT user_vouchers'); $pdo->exec('RELEASE SAVEPOINT user_vouchers'); }
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function create(int $cid, string $key, int|array $amount): void
    {
        self::validateReward($key, $amount);
        $uid = (int)Db::value('SELECT user_id FROM `character` WHERE id = ?', [$cid]);
        if (!$uid) throw new GameError('errCheckVoucherRequirementsWrongUser');
        // Reutiliza schema existente; prefixo inclui dono e separa campanhas.
        Db::exec('INSERT INTO vouchers (code, rewards, user_id, ts_creation, ts_start, uses_max, status) VALUES (?, ?, ?, ?, 0, 1, 1)',
            ['msg_'.$cid.'_'.bin2hex(random_bytes(12)), json_encode([$key=>$amount], JSON_THROW_ON_ERROR), $uid, GameTime::now()]);
    }

    public static function data(int $cid): array
    {
        return array_map(static fn(array $r): array => ['id'=>(int)$r['id'], 'code'=>(string)$r['code'], 'rewards'=>(string)$r['rewards']],
            Db::rows('SELECT v.id, v.code, v.rewards FROM vouchers v JOIN `character` c ON c.user_id = v.user_id
                WHERE c.id = ? AND v.code LIKE ? AND v.status = 1 AND v.uses_current = 0 ORDER BY v.id', [$cid, 'msg\_'.$cid.'\_%']));
    }

    public static function attach(array $data, int $cid): array
    {
        $rows = self::data($cid);
        $data['user_vouchers'] = $rows;
        $unread = Db::column('SELECT id FROM vouchers WHERE code LIKE ? AND status = 1 AND uses_current = 0 AND ts_start = 0', ['msg\_'.$cid.'\_%']);
        $data['streams_info']['v'] = (object)[(string)$cid=>['id'=>$cid, 'type'=>'v', 'unread'=>count($unread)]];
        // Pending collection is refreshed as a whole, including after redemption.
        if (isset($data['character'])) $data['character']['new_user_voucher_ids'] = json_encode(array_map('intval', $unread));
        return $data;
    }

    public static function markRead(int $cid): void
    {
        Db::exec('UPDATE vouchers SET ts_start = ? WHERE code LIKE ? AND uses_current = 0', [max(1,GameTime::now()), 'msg\_'.$cid.'\_%']);
    }

    public static function parseCodes(array $params): array
    {
        if (isset($params['codes'])) {
            $codes = is_array($params['codes']) ? $params['codes'] : json_decode((string)$params['codes'], true);
            if (!is_array($codes) || !array_is_list($codes)) throw new GameError('errRedeemVoucherInvalidCode');
        } else $codes = [$params['code'] ?? ''];
        if (!$codes || count($codes) > 500) throw new GameError('errRedeemVoucherInvalidCode');
        foreach ($codes as $code) if (!is_string($code) || $code === '' || strlen($code) > 50) throw new GameError('errRedeemVoucherInvalidCode');
        if (count(array_unique($codes)) !== count($codes)) throw new GameError('errInvalidateAlreadyRedeemed');
        sort($codes, SORT_STRING);
        return $codes;
    }

    public static function redeem(int $cid, array $codes): array
    {
        return self::transaction(static function () use ($cid, $codes): array {
            $char = Db::row('SELECT * FROM `character` WHERE id = ? FOR UPDATE', [$cid]);
            if (!$char) throw new GameError('errCheckVoucherRequirementsWrongUser');
            $total = [];
            $ids = [];
            foreach ($codes as $code) {
                $v = Db::row('SELECT * FROM vouchers WHERE code = ? FOR UPDATE', [$code]);
                if (!$v) throw new GameError('errRedeemVoucherInvalidCode');
                if ((int)$v['user_id'] !== (int)$char['user_id'] || !str_starts_with($code, 'msg_'.$cid.'_')) throw new GameError('errCheckVoucherRequirementsWrongUser');
                if ((int)$v['uses_current'] !== 0) throw new GameError('errInvalidateAlreadyRedeemed');
                if ((int)$v['status'] !== 1) throw new GameError('errRedeemInactiveVoucher');
                $rewards = json_decode($v['rewards'], true, 512, JSON_THROW_ON_ERROR);
                foreach ($rewards as $key=>$amount) {
                    try { self::validateReward($key, $amount); } catch (\InvalidArgumentException $e) { throw new GameError('errRedeemVoucherInvalidCode'); }
                    if (isset(self::BOOSTERS[$key])) {
                        if (isset($total[$key]) && $total[$key]['type'] !== $amount['type']) throw new GameError('errRedeemVoucherInvalidCode');
                        $total[$key] = ['type'=>$amount['type'], 'duration'=>($total[$key]['duration'] ?? 0)+$amount['duration']];
                    } else $total[$key] = ($total[$key] ?? 0) + $amount;
                }
                $ids[] = (int)$v['id'];
            }
            if ((int)$char['quest_energy'] + ($total['quest_energy'] ?? 0) > 32767
                || (int)$char['training_count'] + ($total['training_sessions'] ?? 0) > 32767) throw new GameError('errRedeemLimitReached');
            // Energia e unidades podem ultrapassar o máximo diário: são prémios,
            // não regeneração. Não usar LEAST nem truncar o saldo existente.
            Db::exec('UPDATE `character` SET quest_energy = quest_energy + ?, training_count = training_count + ? WHERE id = ?',
                [$total['quest_energy'] ?? 0, $total['training_sessions'] ?? 0, $cid]);
            if (isset($total['hideout_glue']) || isset($total['hideout_stone'])) {
                $h = Db::row('SELECT * FROM hideout WHERE character_id = ? FOR UPDATE', [$cid]);
                foreach (['glue','stone'] as $kind) {
                    $amount = $total['hideout_'.$kind] ?? 0;
                    if ($amount && (!$h || (int)$h['current_resource_'.$kind] + $amount > (int)$h['max_resource_'.$kind])) {
                        throw new GameError($kind === 'glue' ? 'errRedeemVoucherGlueFull' : 'errRedeemVoucherStoneFull');
                    }
                }
                Db::exec('UPDATE hideout SET current_resource_glue = current_resource_glue + ?, current_resource_stone = current_resource_stone + ? WHERE character_id = ?',
                    [$total['hideout_glue'] ?? 0, $total['hideout_stone'] ?? 0, $cid]);
            }
            foreach (self::BOOSTERS as $key=>[$type,$kind,$unit]) {
                if (!isset($total[$key])) continue;
                $expiry = 'ts_active_'.$kind.'_boost_expires';
                if (!array_key_exists($expiry, $char)) throw new GameError('errRedeemInactiveVoucher');
                $id = self::boosterId($key, $total[$key]['type']);
                $idField = 'active_'.$kind.'_booster_id';
                // Não substituir um energético melhor ainda ativo por um inferior.
                $activeId = (string)($char[$idField] ?? '');
                if ((int)$char[$expiry] > GameTime::now() && isset(Live::BOOSTER_CATALOG[$activeId])
                    && Live::BOOSTER_CATALOG[$activeId][1] > $total[$key]['type']) throw new GameError('errRedeemInactiveVoucher');
                $expires = max(GameTime::now(), (int)$char[$expiry]) + $unit*$total[$key]['duration'];
                if ($expires > 2147483647) throw new GameError('errRedeemLimitReached');
                if ($key==='hideout_booster') { Hideout::activateProductionBooster($cid,$id,$expires); continue; }
                if (array_key_exists($idField, $char)) Db::exec("UPDATE `character` SET `{$idField}` = ?, `{$expiry}` = ? WHERE id = ?", [$id,$expires,$cid]);
                else Db::exec("UPDATE `character` SET `{$expiry}` = ? WHERE id = ?", [$expires,$cid]);
            }
            foreach ($ids as $id) {
                Db::exec('UPDATE vouchers SET uses_current = 1 WHERE id = ?', [$id]);
                Db::exec('INSERT INTO voucher_redemptions (voucher_id,user_id,character_id,ts_redeemed) VALUES (?,?,?,?)', [$id,(int)$char['user_id'],$cid,GameTime::now()]);
            }
            return $total;
        });
    }
}
