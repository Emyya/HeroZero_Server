<?php
declare(strict_types=1);
namespace HeroZero;

/** Caixas solo HeroCon: conteúdo descrito nos textos do cliente html5_257. */
final class HeroConBox
{
    public const BOXES = ['surprise_box_solo_herocon_big_1'=>2,'surprise_box_solo_herocon_small_1'=>1];

    public static function open(int $cid, int $itemId): array
    {
        return StoryDungeon::transaction($cid, static function(array $char) use ($cid,$itemId): array {
            HeroCon::assertItemsAvailable($cid,[$itemId]);
            $inv=Db::row('SELECT * FROM inventory WHERE character_id=? FOR UPDATE',[$cid]);
            $box=Db::row('SELECT * FROM items WHERE character_id=? AND id=? FOR UPDATE',[$cid,$itemId]);
            if (!$inv || !$box || !isset(self::BOXES[$box['identifier']]) || (int)$box['type']!==10) throw new GameError('errInventoryInvalidItem');
            // Caixas já entregues por createSpecialItem têm charges=0: uso único.
            $charges=max(1,(int)$box['charges']);
            if ((int)$char['level']<(int)$box['required_level']) throw new GameError('errUseItemItemReqLevel');
            $source=null;$slots=[];
            $c=Missiles::constants();
            $limit=(int)$char['level']>=(int)$c['inventory_bag3_unlock_level']?18:((int)$char['level']>=(int)$c['inventory_bag2_unlock_level']?12:6);
            for($i=1;$i<=$limit;$i++) {
                $field='bag_item'.$i.'_id';
                if ((int)$inv[$field]===$itemId) $source=$field;
                elseif ((int)$inv[$field]===0) $slots[]=$field;
            }
            if ($source===null) throw new GameError('errInventoryInvalidItem');
            // O espaço da caixa só fica disponível quando a última carga é consumida.
            if ($charges===1) array_unshift($slots,$source);
            $count=self::BOXES[$box['identifier']];
            if (count($slots)<$count) throw new GameError('errInventoryNoEmptySlot');
            $src=__DIR__.'/../../public/assets/data/constants_json.data';
            $templates=AssetCache::slice('herocon_box_templates',$src,static function()use($src):array {
                $all=json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR);
                return array_filter($all['item_templates'],static fn($t)=>($t['item_set_identifier']??null)==='solo_herocon_set' && (int)$t['type']<=7 && (int)($t['effect_chance']??0)>0);
            });
            $owned=[];
            foreach(Db::rows('SELECT identifier FROM items WHERE character_id=?',[$cid]) as $item) $owned[preg_replace('/-effect-.*$/','',$item['identifier'])]=true;
            $goals=Character::goalStatsFor($cid);$pool=array_keys($templates);$ids=[];
            foreach(range(1,$count) as $_) {
                $missing=array_values(array_filter($pool,static fn($key)=>!isset($owned[$key]) && empty($goals['herocon_box_collected_'.$key])));
                $choices=$missing?:$pool;
                $base=$choices[random_int(0,count($choices)-1)];$tpl=$templates[$base];
                $identifier=$base;
                if (random_int(1,100)<=(int)$tpl['effect_chance']) $identifier.='-effect-'.$tpl['effects'];
                $id=Character::load($cid)->createEventRewardItem($identifier,(int)$tpl['type'],(int)$tpl['quality']);
                Db::exec('UPDATE inventory SET `'.$slots[count($ids)].'`=? WHERE character_id=?',[$id,$cid]);
                $ids[]=$id;$owned[$base]=true;
                Character::bumpGoalStatFor($cid,'herocon_box_collected_'.$base,1);
            }
            if ($charges===1) Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$itemId,$cid]);
            else Db::exec('UPDATE items SET charges=charges-1 WHERE id=? AND character_id=?',[$itemId,$cid]);
            return Db::rows('SELECT * FROM items WHERE character_id=? AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY FIELD(id,'.implode(',',$ids).')',array_merge([$cid],$ids));
        });
    }
}
