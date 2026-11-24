<?php
declare(strict_types=1);
namespace HeroZero;

/** Armas de arremesso pessoais; não são guild.missiles. */
final class Missiles
{
    // Quantidade local por pacote; o cliente/constantes não especificam a geração.
    public const PACKAGE_CHARGES = 100;
    private static ?array $constants = null;

    public static function constants(): array
    {
        if (self::$constants !== null) return self::$constants;
        $src = dirname(__DIR__, 3).'/assets/data/constants_json.data';
        return self::$constants = AssetCache::slice('missiles', $src, static function () use ($src): array {
            $all = json_decode(gzuncompress(file_get_contents($src)), true, 512, JSON_THROW_ON_ERROR);
            $keys = ['item_missile_damage_factor','item_missile_min_required_level','ammo_belt_min_required_level','inventory_bag2_unlock_level','inventory_bag3_unlock_level','battle_effects'];
            for ($i=1;$i<=4;$i++) $keys[] = 'ammo_belt_slot'.$i.'_unlock_premium_currency_amount';
            $data = array_intersect_key($all,array_flip($keys));
            $data['templates'] = array_filter($all['item_templates'], static fn($r) => (int)$r['type'] === 8);
            return $data;
        });
    }

    public static function damage(int $level, ?array $sidekick = null): int
    {
        $base = (int)floor($level * self::constants()['item_missile_damage_factor'] + 0.5);
        $bonus = 0.0;
        $effects = self::constants()['battle_effects'][104]['sidekick_battle_effect_ids'];
        if ($sidekick !== null) for ($stage=1;$stage<=3;$stage++) {
            if ((int)$sidekick['level'] < 10*$stage) continue;
            $skill = Sidekick::skill((int)($sidekick['stage'.$stage.'_skill_id'] ?? 0));
            if ($skill !== null && in_array((int)$skill['sidekick_battle_effect_id'],$effects,true)) $bonus += $base*(float)$skill['value1'];
        }
        return $base + (int)floor($bonus+0.5);
    }

    public static function template(string $identifier, int $level, int $buy = 0, int $sell = 0): array
    {
        $record = self::constants()['templates'][$identifier] ?? throw new GameError('errInventoryInvalidItem');
        return ['identifier'=>$identifier,'type'=>8,'quality'=>(int)$record['quality'],
            'required_level'=>max((int)$record['required_level'],(int)self::constants()['item_missile_min_required_level']),
            'charges'=>self::PACKAGE_CHARGES,'item_level'=>max(1,$level),'ts_availability_start'=>0,'ts_availability_end'=>0,
            'premium_item'=>false,'buy_price'=>$buy,'sell_price'=>$sell,'stat_stamina'=>0,'stat_strength'=>0,
            'stat_critical_rating'=>0,'stat_dodge_rating'=>0,'stat_weapon_damage'=>0];
    }

    public static function give(int $cid, string $identifier): int
    {
        return self::transaction([$cid], static function() use($cid,$identifier): int {
            $char=Db::row('SELECT level FROM `character` WHERE id = ?',[$cid]);
            $inv=Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE',[$cid]) ?? [];
            $free=null;
            $limit = (int)$char['level'] >= self::constants()['inventory_bag3_unlock_level'] ? 18 : ((int)$char['level'] >= self::constants()['inventory_bag2_unlock_level'] ? 12 : 6);
            for($i=1;$i<=$limit;$i++) if(array_key_exists('bag_item'.$i.'_id',$inv) && (int)$inv['bag_item'.$i.'_id']===0) {$free='bag_item'.$i.'_id';break;}
            if($free===null) throw new GameError('errInventoryNoEmptySlot');
            $data=self::template($identifier,(int)$char['level']);
            $columns=array_keys($data);
            Db::exec('INSERT INTO items (character_id, `'.implode('`,`',$columns).'`) VALUES (?,'.implode(',',array_fill(0,count($columns),'?')).')',array_merge([$cid],array_values($data)));
            $id=(int)Db::pdo()->lastInsertId();
            Db::exec("UPDATE inventory SET `$free` = ? WHERE character_id = ?",[$id,$cid]);
            return $id;
        });
    }

    /** Transação de inventário/combate, com locks de personagens em ordem estável. */
    public static function transaction(array $cids, callable $fn): mixed
    {
        $pdo=Db::pdo(); $nested=$pdo->inTransaction();
        static $serial=0; $save='missiles_'.++$serial;
        if ($nested) $pdo->exec('SAVEPOINT '.$save); else $pdo->beginTransaction();
        try {
            $cids=array_unique(array_filter(array_map('intval',$cids),static fn($id)=>$id>0));sort($cids);
            foreach($cids as $cid) Db::row('SELECT id FROM `character` WHERE id = ? FOR UPDATE',[$cid]);
            $result=$fn();
            if ($nested) $pdo->exec('RELEASE SAVEPOINT '.$save); else $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if($nested && $pdo->inTransaction()) {$pdo->exec('ROLLBACK TO SAVEPOINT '.$save);$pdo->exec('RELEASE SAVEPOINT '.$save);}
            elseif($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function snapshot(int $cid): array
    {
        $inv=Db::row('SELECT * FROM inventory WHERE character_id = ?',[$cid]) ?? [];
        $out=[]; $seen=[];
        foreach(['missiles_item_id','missiles1_item_id','missiles2_item_id','missiles3_item_id','missiles4_item_id'] as $i=>$field) {
            $id=(int)($inv[$field] ?? 0);
            if($id<=0 || isset($seen[$id])) continue;
            $item=Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? AND type = 8 AND charges > 0',[$id,$cid]);
            if($item===null) continue;
            $seen[$id]=true;
            $out[]=['id'=>$id,'index'=>$i+1,'charges'=>(int)$item['charges'],'damage'=>self::damage((int)$item['item_level'],Sidekick::activeRow($cid))];
        }
        return $out;
    }

    /** Persistir só depois de gravar a batalha e na mesma transação. */
    public static function consume(int $cid, array $usage): void
    {
        if (!Db::pdo()->inTransaction()) throw new \LogicException('Consumo exige transação da batalha.');
        $inv=Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE',[$cid]) ?? [];
        foreach($usage as $id=>$count) {
            $item=Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? AND type = 8 FOR UPDATE',[(int)$id,$cid]);
            if($item===null || (int)$item['charges'] < $count) throw new GameError('errInventoryInvalidItem');
            $left=(int)$item['charges']-$count;
            if($left>0) Db::exec('UPDATE items SET charges = ? WHERE id = ? AND character_id = ?',[$left,(int)$id,$cid]);
            else {
                foreach(['missiles_item_id','missiles1_item_id','missiles2_item_id','missiles3_item_id','missiles4_item_id'] as $field) if((int)($inv[$field] ?? 0)===(int)$id) {
                    Db::exec("UPDATE inventory SET `$field` = 0 WHERE character_id = ?",[$cid]); $inv[$field]=0;
                }
                Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?',[(int)$id,$cid]);
                // Um pacote guardado no cofre pode ter sido bloqueado antes de equipar.
                $bank=Db::row('SELECT locked_item_ids FROM bank_inventory WHERE character_id = ?',[$cid]);
                if($bank!==null) {
                    $locked=json_decode($bank['locked_item_ids'],true) ?: [];
                    $locked=array_values(array_filter($locked,static fn($v)=>(int)$v!==(int)$id));
                    Db::exec('UPDATE bank_inventory SET locked_item_ids = ? WHERE character_id = ?',[json_encode($locked),$cid]);
                }
            }
        }
        if((int)($inv['missiles_item_id'] ?? 0)===0) for($i=1;$i<=4;$i++) {
            $field='missiles'.$i.'_item_id';$id=(int)($inv[$field] ?? 0);
            if($id>0) {Db::exec("UPDATE inventory SET missiles_item_id = ?, `$field` = 0 WHERE character_id = ?",[$id,$cid]);break;}
        }
    }

    public static function operate(int $cid,string $action,array $params): void
    {
        self::transaction([$cid],static function() use($cid,$action,$params): void {
            $char=Db::row('SELECT * FROM `character` WHERE id = ?',[$cid]);
            $inv=Db::row('SELECT * FROM inventory WHERE character_id = ? FOR UPDATE',[$cid]) ?? [];
            if($action==='upgradeAmmoBelt') {
                if((int)$char['level'] < self::constants()['ammo_belt_min_required_level']) throw new GameError('errInventoryInvalidItem');
                for($i=1;$i<=4;$i++) if((int)$inv['missiles'.$i.'_item_id']===-1) {
                    $cost=(int)self::constants()['ammo_belt_slot'.$i.'_unlock_premium_currency_amount'];
                    if(Db::exec('UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',[$cost,(int)$char['user_id'],$cost])!==1) throw new GameError('errRemovePremiumCurrencyNotEnough');
                    Db::exec("UPDATE inventory SET missiles{$i}_item_id = 0 WHERE character_id = ?",[$cid]);return;
                }
                return;
            }
            $id=(int)($params['item_id'] ?? 0);$source=null;
            foreach($inv as $field=>$value) if(preg_match('/^(bag_item\d+_id|missiles[1-4]?_item_id)$/',$field) && (int)$value===$id && $id>0) {$source=$field;break;}
            $item=Db::row('SELECT * FROM items WHERE id = ? AND character_id = ? FOR UPDATE',[$id,$cid]);
            if($source===null || $item===null || (int)$item['type']!==8 || (int)$item['charges']<=0) throw new GameError('errInventoryInvalidItem');
            if ($action === 'sellInventoryItem') {
                $price = ItemImprovement::sellPrice($cid, $item);
                HeroCon::sold($cid, $item);
                Db::exec("UPDATE inventory SET `$source` = 0 WHERE character_id = ?", [$cid]);
                ItemImprovement::deleteAttached($cid, $id);
                Db::exec('DELETE FROM items WHERE id = ? AND character_id = ?', [$id, $cid]);
                Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$price, $cid]);
                return;
            }
            if ((int)$char['level'] < (int)$item['required_level']) throw new GameError('errInventoryInvalidItem');
            $slot=(int)($params['target_slot'] ?? 0);
            $equip=filter_var($params['equip'] ?? false,FILTER_VALIDATE_BOOLEAN);
            if($action==='moveAmmoBeltItem' && $equip) {
                if((int)$char['level']<self::constants()['ammo_belt_min_required_level'] || $slot<1 || $slot>4) throw new GameError('errInventoryInvalidItem');
                $target='missiles'.$slot.'_item_id';
                // O cliente só aceita munição da mochila para uma vaga vazia do cinto.
                if(!str_starts_with($source,'bag_item') || (int)$inv[$target]!==0) throw new GameError('errInventoryInvalidItem');
            } else {
                if($slot===8 && $action==='moveInventoryItem') {
                    if((int)$char['level'] < max((int)$item['required_level'],(int)self::constants()['item_missile_min_required_level'])) throw new GameError('errInventoryInvalidItem');
                    $target='missiles_item_id';
                } elseif($slot>=9 && $slot<=26) {
                    if($slot>=21 && (int)$char['level']<self::constants()['inventory_bag3_unlock_level'] || $slot>=15 && (int)$char['level']<self::constants()['inventory_bag2_unlock_level']) throw new GameError('errInventoryInvalidItem');
                    $target='bag_item'.($slot-8).'_id';
                } else throw new GameError('errInventoryInvalidItem');
                if(str_starts_with($source,'missiles') && $source!=='missiles_item_id' && (int)$inv[$target]>0) throw new GameError('errInventoryNoEmptySlot');
            }
            if($source===$target) return;
            $displaced=max(0,(int)$inv[$target]);
            if($source==='missiles_item_id' && $displaced>0) {
                $other=Db::row('SELECT type, required_level, charges FROM items WHERE id = ? AND character_id = ?',[$displaced,$cid]);
                if($other===null || (int)$other['type']!==8 || (int)$other['charges']<=0 || (int)$other['required_level']>(int)$char['level']) throw new GameError('errInventoryInvalidItem');
            }
            Db::exec("UPDATE inventory SET `$source` = ?, `$target` = ? WHERE character_id = ?",[$displaced,$id,$cid]);
        });
    }
}
