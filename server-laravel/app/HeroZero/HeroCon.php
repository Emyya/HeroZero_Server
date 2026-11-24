<?php
declare(strict_types=1);
namespace HeroZero;

/** HeroCon: catálogo do cliente e política local descrita em docs/implementar/herocon.md. */
final class HeroCon
{
    public const EXCLUDED = 'default_guild_competition_complete_dungeon_quest';
    public const DISABLED = [self::EXCLUDED, 'default_guild_competition_daily_herobook_completed',
        'default_guild_competition_hideout_bots_build', 'default_guild_competition_collect_item_1',
        'default_guild_competition_collect_item_2', 'default_guild_competition_sell_items2'];
    private static ?bool $ready = null;

    public static function available(): bool
    {
        // Deploy antes da migração não impede as restantes funcionalidades.
        return self::$ready ??= (bool)Db::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='herocon_rewards'");
    }

    public static function constants(): array
    {
        $src=__DIR__.'/../../public/assets/data/constants_json.data';
        return AssetCache::slice('herocon_v2',$src,static function()use($src):array {
            $c=json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR);
            return array_filter($c,static fn($k)=>str_starts_with($k,'guild_competition')||str_starts_with($k,'solo_guild_competition'),ARRAY_FILTER_USE_KEY);
        });
    }

    public static function themes(): array
    {
        return array_values(array_diff(array_keys(self::constants()['guild_competitions']),self::DISABLED));
    }

    public static function period(int $now): array
    {
        $date=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone('Europe/Lisbon'));
        $start=$date->modify('thursday this week')->setTime(21,0);
        if($start->getTimestamp()>$now)$start=$start->modify('-1 week');
        $end=$start->modify('+1 week');
        $anchor=new \DateTimeImmutable('2026-10-01 21:00:00',new \DateTimeZone('Europe/Lisbon'));
        // Dias civis: evita mudar a rotação na semana de mudança da hora.
        $index=(int)floor((int)$anchor->diff($start)->format('%r%a')/7);
        $themes=self::themes();$index=(($index%count($themes))+count($themes))%count($themes);
        return ['identifier'=>$themes[$index],'ts_start'=>$start->getTimestamp(),'ts_end'=>$end->getTimestamp()];
    }

    /** Pontos diretos; fraction pertence a outro contador do protocolo. */
    public static function points(int $total): array
    {
        if ($total<0) throw new \InvalidArgumentException('Pontos inválidos.');
        return ['score'=>$total,'fraction'=>0];
    }

    public static function soloStats(int $rank): int
    {
        if($rank<1)return 0;
        foreach([3,10,20,30,40,50,100,150,200,300] as $limit)
            if($rank<=$limit)return (int)self::constants()['solo_guild_competition_reward_stat_points_top_'.$limit];
        return 0;
    }

    public static function teamReward(int $rank,int $guilds): array
    {
        if($rank<1||$rank>100||$guilds<1)return ['booster'=>0,'statPoints'=>0];
        $factor=max(1-$rank/$guilds*1.1,0.05,1.625-0.15*$rank);
        return ['booster'=>(int)ceil($factor*(int)self::constants()['guild_competition_reward_guild_booster']),
            'statPoints'=>(int)ceil($factor*(int)self::constants()['guild_competition_reward_stat_points'])];
    }

    /** Segundos antes do fim do ciclo em que se volta a exigir o lock (pontuação vs. fecho). */
    private const LOCK_MARGIN=5;

    /**
     * Ciclo atual sem lock. Só responde quando nada está por fechar nem criar e
     * ainda falta mais do que LOCK_MARGIN para o fim; caso contrário devolve null
     * e o chamador segue o caminho com lock, que continua a ser o único a fechar/criar.
     */
    private static function currentUnlocked(int $now): ?array
    {
        $current=Db::row('SELECT * FROM herocon_cycles WHERE finished=0 AND ts_end>? ORDER BY id DESC LIMIT 1',[$now]);
        if(!$current||(int)$current['ts_end']-$now<=self::LOCK_MARGIN||!in_array($current['identifier'],self::themes(),true))return null;
        if(Db::value('SELECT id FROM herocon_cycles WHERE finished=0 AND ts_end<=? LIMIT 1',[$now])!==null)return null;
        return $current;
    }

    /** Chamador deve estar numa transação. Lock único serializa pontuação e fecho. */
    private static function cycle(): array
    {
        if(($fast=self::currentUnlocked(GameTime::now()))!==null)return $fast;
        Db::row('SELECT id FROM herocon_control WHERE id=1 FOR UPDATE');
        $period=self::period(GameTime::now());
        foreach(Db::rows('SELECT * FROM herocon_cycles WHERE finished=0 AND ts_end<=? ORDER BY id',[GameTime::now()]) as $old)self::finish($old);
        $current=Db::row('SELECT * FROM herocon_cycles WHERE finished=0 AND ts_end>? ORDER BY id DESC LIMIT 1',[GameTime::now()]);
        if ($current && !in_array($current['identifier'],self::themes(),true)) {
            // Conservar o resultado antigo; o tema retirado não recebe novas atividades.
            self::finish($current);$current=null;
        }
        if ($current) return $current;
        $start=$period['ts_start'];
        if (Db::value('SELECT id FROM herocon_cycles WHERE ts_start=?',[$start])) {
            $start=GameTime::now();
            while (Db::value('SELECT id FROM herocon_cycles WHERE ts_start=?',[$start])) ++$start;
        }
        Db::exec('INSERT INTO herocon_cycles (identifier,ts_start,ts_end) VALUES (?,?,?)',[$period['identifier'],$start,$period['ts_end']]);
        return Db::row('SELECT * FROM herocon_cycles WHERE id=?',[(int)Db::pdo()->lastInsertId()]);
    }

    public static function sync(): ?array
    {
        if(!self::available())return null;
        // Caso comum: ciclo ativo, sem transação nem lock.
        if(($fast=self::currentUnlocked(GameTime::now()))!==null)return $fast;
        return Missiles::transaction([],static fn()=>self::cycle());
    }

    /** Eventos aceites apenas do backend, na transação da atividade. */
    public static function record(int $cid,int $type,int $amount=1,string $reference=''): void
    {
        if($amount<1||in_array($type,[5,15,22,25],true)||!self::available())return;
        StoryDungeon::transaction($cid,static function(array $char)use($cid,$type,$amount,$reference):void {
            if((int)$char['guild_id']<1)return;
            $cycle=self::cycle();$definition=self::constants()['guild_competitions'][$cycle['identifier']];$gain=0;
            foreach($definition['objectives'] as $objective) {
                if((int)$objective['type']!==$type)continue;
                $refs=explode(';',(string)$objective['reference']);
                if($objective['reference']!==''&&!in_array('all',$refs,true)&&!in_array($reference,$refs,true))continue;
                $gain+=(int)$objective['multiplier']*$amount;
            }
            if($gain<1)return;
            Db::exec('INSERT INTO herocon_contributions (cycle_id,character_id,guild_id,fractions,ts_score) VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE fractions=fractions+VALUES(fractions),ts_score=VALUES(ts_score)',
                [(int)$cycle['id'],$cid,(int)$char['guild_id'],$gain,GameTime::now()]);
            foreach([5=>(int)$char['guild_id'],6=>$cid] as $kind=>$id) {
                $field=$kind===5?'guild_id':'character_id';
                $total=(int)Db::value("SELECT SUM(fractions) FROM herocon_contributions WHERE cycle_id=? AND $field=?",[(int)$cycle['id'],$id]);
                $score=$total;
                Db::exec('INSERT INTO herocon_rank_times (cycle_id,kind,participant_id,score,ts_score) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE ts_score=IF(VALUES(score)>score,VALUES(ts_score),ts_score),score=VALUES(score)',[(int)$cycle['id'],$kind,$id,$score,GameTime::now()]);
            }
        });
    }

    /** A moldura dourada soma +3 aos pontos da raridade. */
    public static function sold(int $cid,array $item): void
    {
        $type=[1=>2,2=>3,3=>4,4=>32][(int)($item['quality']??0)]??0;
        if ($type) {
            self::record($cid,$type);
            if ((int)($item['ts_availability_start']??0)===15) self::record($cid,12);
        }
    }

    public static function questReward(int $cid,string $identifier): array
    {
        return [];
    }

    public static function collectItem(int $cid,array $reward): void
    {
        // Temas de coleção desativados. Prévias antigas não dão pontos.
    }

    public static function ranking(array $cycle,bool $solo): array
    {
        $key=$solo?'character_id':'guild_id';
        $rows=Db::rows("SELECT $key AS id,SUM(fractions) AS total,MAX(ts_score) AS ts_score FROM herocon_contributions WHERE cycle_id=? GROUP BY $key",[(int)$cycle['id']]);
        foreach($rows as &$r){$r['id']=(int)$r['id'];$r+=self::points((int)$r['total']);}unset($r);
        $times=Db::rows('SELECT participant_id,ts_score FROM herocon_rank_times WHERE cycle_id=? AND kind=?',[(int)$cycle['id'],$solo?6:5]);
        $times=array_column($times,'ts_score','participant_id');
        foreach($rows as &$r)$r['ts_score']=$times[$r['id']]??$r['ts_score'];unset($r);
        $rows=array_values(array_filter($rows,static fn($r)=>$r['score']>0));
        usort($rows,static fn($a,$b)=>($b['score']<=>$a['score'])?:((int)$a['ts_score']<=>(int)$b['ts_score'])?:($a['id']<=>$b['id']));
        foreach($rows as $i=>&$r)$r['rank']=$i+1;unset($r);
        return $rows;
    }

    private static function finish(array $cycle): void
    {
        $teams=self::ranking($cycle,false);$eligible=count(array_filter($teams,static fn($r)=>$r['rank']<=100&&$r['score']>=100));
        foreach($teams as $r) {
            $reward=$r['rank']<=100&&$r['score']>=100?self::teamReward($r['rank'],$eligible):['booster'=>0,'statPoints'=>0];
            // Só a equipa ganha o booster; a duração começa no fecho previsto.
            if ($reward['booster']>0) Db::exec('UPDATE `guild` SET guild_competition_reward_boost_factor=?,ts_guild_competition_reward_boost_expires=? WHERE id=?',
                [$reward['booster'],self::period((int)$cycle['ts_end'])['ts_end'],$r['id']]);
            foreach(Db::column('SELECT DISTINCT p.character_id FROM herocon_contributions p JOIN `character` c ON c.id=p.character_id AND c.guild_id=p.guild_id WHERE p.cycle_id=? AND p.guild_id=? AND p.fractions>0',[(int)$cycle['id'],$r['id']]) as $cid)
                self::entitle($cycle,(int)$cid,5,$r['rank'],$r['score'],$reward['statPoints'],'');
        }
        foreach(self::ranking($cycle,true) as $r) {
            $box=$r['rank']===1?self::constants()['solo_guild_competition_reward_reward_box_identifier_top_1']:
                ($r['rank']<=5?self::constants()['solo_guild_competition_reward_reward_box_identifier_top_5']:'');
            self::entitle($cycle,$r['id'],6,$r['rank'],$r['score'],self::soloStats($r['rank']),$box);
        }
        Db::exec('UPDATE herocon_cycles SET finished=1 WHERE id=?',[(int)$cycle['id']]);
    }

    private static function entitle(array $cycle,int $cid,int $kind,int $rank,int $score,int $stats,string $box): void
    {
        $season='';$points=0;
        if($kind===6&&$rank<=100) {
            $p=Db::row('SELECT * FROM season_progress WHERE character_id=? ORDER BY id DESC LIMIT 1',[$cid]);
            if($p&&(int)$p['status']===2&&(int)$cycle['ts_end']>=(int)$p['ts_start']&&(int)$cycle['ts_end']<(int)$p['ts_end']) {
                $season=(string)$p['identifier'];$location=$rank<=3?3:($rank<=10?4:5);
                $points=(int)(Herobook::constants()['season_locations'][$location][$season]??0);
            }
        }
        Db::exec('INSERT IGNORE INTO herocon_rewards (cycle_id,character_id,kind,rank_position,score,stat_points,box_identifier,season_identifier,season_points) VALUES (?,?,?,?,?,?,?,?,?)',
            [(int)$cycle['id'],$cid,$kind,$rank,$score,$stats,$box,$season,$points]);
    }

    public static function contribution(int $cycleId,int $cid,int $gid): array
    {
        $total=(int)Db::value('SELECT COALESCE(SUM(fractions),0) FROM herocon_contributions WHERE cycle_id=? AND character_id=? AND guild_id=?',[$cycleId,$cid,$gid]);
        return self::points($total);
    }

    public static function attach(array $data,int $cid): array
    {
        $cycle=self::sync();if(!$cycle)return $data;
        $char=Db::row('SELECT guild_id FROM `character` WHERE id=?',[$cid]);$gid=(int)($char['guild_id']??0);
        $offers=self::refreshOffers($cid,$cycle);
        if(isset($data['quests']))foreach($data['quests'] as &$quest) {
            if(isset($offers[(int)$quest['id']]))$quest['rewards']=$offers[(int)$quest['id']];
        }unset($quest);
        $part=self::contribution((int)$cycle['id'],$cid,$gid);
        $data['character']['guild_competition_points_gathered']=$part['score'];
        $data['character']['guild_competition_point_fractions_gathered']=$part['fraction'];
        foreach([5=>'pending_guild_competition_tournament_rewards',6=>'pending_solo_guild_competition_tournament_rewards'] as $kind=>$key)
            $data['character'][$key]=(int)Db::value('SELECT COUNT(*) FROM herocon_rewards WHERE character_id=? AND kind=? AND claimed=0',[$cid,$kind]);
        $data['character']['new_guild_competition_tournament']=(int)Db::value('SELECT cycle_id FROM herocon_views WHERE character_id=?',[$cid])!==(int)$cycle['id'];
        $data['guild_competition_tournament_end_timestamp']=(int)$cycle['ts_end'];
        $stats=Character::goalStatsFor($cid);
        foreach(['guild_competition_visit','guild_competition_top_100','guild_competition_first_place','guild_competition_score_3000','solo_guild_competition_top_1','solo_guild_competition_top_10','solo_guild_competition_top_50','solo_guild_competition_top_100','solo_guild_competition_participated'] as $key) {
            $v=(int)($stats[$key]??0);$data['current_goal_values'][$key]=['value'=>$v,'current_value'=>$v];
        }
        unset($data['current_guild_competition_identifier'],$data['guild_competition_data'],$data['next_guild_competition_data']);
        if($gid>0) {
            $boost=Db::row('SELECT guild_competition_reward_boost_factor,ts_guild_competition_reward_boost_expires FROM `guild` WHERE id=?',[$gid]);
            foreach(['guild','requested_guild'] as $key) if(isset($data[$key]) && (int)($data[$key]['id']??0)===$gid && $boost) {
                foreach($boost as $field=>$value)$data[$key][$field]=(int)$value;
            }
            $ranking=self::ranking($cycle,false);$own=null;
            foreach($ranking as $row)if($row['id']===$gid)$own=$row;
            $total=(int)Db::value('SELECT COALESCE(SUM(fractions),0) FROM herocon_contributions WHERE cycle_id=? AND guild_id=?',[(int)$cycle['id'],$gid]);
            $score=self::points($total);
            $data['guild_competition_data']=['identifier'=>$cycle['identifier'],'status'=>1,'ts_start'=>(int)$cycle['ts_start'],'ts_end'=>(int)$cycle['ts_end'],
                'rank'=>$own['rank']??0]+$score+['guilds_with_rewards'=>count(array_filter($ranking,static fn($r)=>$r['rank']<=100&&$r['score']>=100)),
                    'guilds_same_score'=>count(array_filter($ranking,static fn($r)=>$r['score']===$score['score'])),
                    'guilds_higher_score'=>self::neighbour($ranking,$score['score'],true),'guilds_lower_score'=>self::neighbour($ranking,$score['score'],false)];
            $data['next_guild_competition_data']=self::period((int)$cycle['ts_end'])+['status'=>0];
            $data['current_guild_competition_identifier']=$cycle['identifier'];
        }
        return $data;
    }


    private static function refreshOffers(int $cid,array $cycle): array
    {
        $updated=[];
        // Compare-and-swap protects concurrent starts; active missions retain their preview.
        foreach(Db::rows('SELECT id,identifier,rewards FROM quests WHERE character_id=? AND status=1',[$cid]) as $q) {
            $r=json_decode($q['rewards'],true)?:[];
            if((int)($r['herocon_offer_cycle']??0)===(int)$cycle['id']&&!isset($r['guild_competition_item'])&&!isset($r['herocon_cycle_id']))continue;
            unset($r['guild_competition_item'],$r['herocon_cycle_id']);
            $r+=self::questReward($cid,$q['identifier']);$r['herocon_offer_cycle']=(int)$cycle['id'];
            $encoded=json_encode($r,JSON_THROW_ON_ERROR);
            if (Db::exec('UPDATE quests SET rewards=? WHERE id=? AND character_id=? AND status=1 AND rewards=?',
                [$encoded,(int)$q['id'],$cid,$q['rewards']])===1) $updated[(int)$q['id']]=$encoded;
        }
        return $updated;
    }


    private static function neighbour(array $ranking,int $score,bool $higher): ?array
    {
        $candidates=array_values(array_filter($ranking,static fn($r)=>$higher?$r['score']>$score:$r['score']<$score));
        if(!$candidates)return null;
        $target=$higher?min(array_column($candidates,'score')):max(array_column($candidates,'score'));
        $group=array_values(array_filter($candidates,static fn($r)=>$r['score']===$target));
        $g=Db::row('SELECT * FROM `guild` WHERE id=?',[$group[0]['id']]);if(!$g)return null;
        $out=['score'=>$target,'guild_count'=>count($group),'guild_name'=>$g['name']];
        foreach(['emblem_background_shape','emblem_background_color','emblem_background_border_color','emblem_icon_shape','emblem_icon_color','emblem_icon_size'] as $k)$out[$k]=(int)($g[$k]??0);
        return $out;
    }

    public static function assertItemsAvailable(int $cid,array $ids): void
    {
        $ids=array_values(array_filter(array_map('intval',$ids),static fn($id)=>$id>0));
        if(!$ids||!self::available())return;
        $marks=implode(',',array_fill(0,count($ids),'?'));
        if(Db::value("SELECT id FROM herocon_rewards WHERE character_id=? AND claimed=0 AND item_id IN ($marks) LIMIT 1",array_merge([$cid],$ids)))
            throw new GameError('errInventoryInvalidItem');
    }

    public static function leaderboard(int $cid,bool $solo,array $params): array
    {
        $cycle=self::sync();if(!$cycle)throw new GameError('errTournamentLocked');
        $rows=self::ranking($cycle,$solo);$list=[];
        $own=$solo?$cid:(int)Db::value('SELECT guild_id FROM `character` WHERE id=?',[$cid]);
        foreach($rows as $r) {
            $profile=$solo?Db::row('SELECT c.id,c.name,c.gender,c.guild_id,c.ts_last_action,g.name AS guild_name,g.emblem_background_shape,g.emblem_background_color,g.emblem_background_border_color,g.emblem_icon_shape,g.emblem_icon_color,g.emblem_icon_size FROM `character` c LEFT JOIN `guild` g ON g.id=c.guild_id WHERE c.id=?',[$r['id']]):Db::row('SELECT * FROM `guild` WHERE id=?',[$r['id']]);
            if(!$profile)continue;
            $entry=['rank'=>$r['rank'],'id'=>$r['id'],'n'=>$profile['name'],'s'=>$r['score']];
            foreach(['ebs'=>'emblem_background_shape','ebc'=>'emblem_background_color','ebbc'=>'emblem_background_border_color','eis'=>'emblem_icon_shape','eic'=>'emblem_icon_color','eiz'=>'emblem_icon_size'] as $short=>$field)$entry[$short]=(int)($profile[$field]??0);
            if($solo)$entry+=['r'=>$r['rank'],'g'=>$profile['gender'],'os'=>GameTime::now()-(int)$profile['ts_last_action']<300?1:2,'gi'=>(int)$profile['guild_id'],'gn'=>(string)($profile['guild_name']??'')];
            $list[]=$entry;
        }
        $center=1;$nameKey=$solo?'character_name':'guild_name';
        if(isset($params['rank'])) {
            $center=(int)$params['rank'];if($center<1||$center>count($list))throw new GameError('errRetrieveLeaderboardInvalidRank');
        } elseif(!empty($params[$nameKey])) {
            $found=false;foreach($list as $r)if(mb_strtolower($r['n'])===mb_strtolower((string)$params[$nameKey])){$center=$r['rank'];$found=true;break;}
            if(!$found)throw new GameError($solo?'errRetrieveLeaderboardInvalidCharacter':'errRetrieveLeaderboardInvalidGuild');
        } else foreach($list as $r)if($r['id']===$own)$center=$r['rank'];
        $suffix=$solo?'solo_guild_competitions':'guild_competitions';
        return ['leaderboard_'.$suffix=>array_slice($list,max(0,$center-50),100),'max_'.$suffix=>count($list),'centered_rank'=>$center,
            'guilds_with_rewards'=>count(array_filter($rows,static fn($r)=>$r['rank']<=100&&$r['score']>=100)),
            'guild_competition_tournament_end_timestamp'=>(int)$cycle['ts_end']];
    }

    public static function viewed(int $cid): void
    {
        $cycle=self::sync();if(!$cycle)return;
        StoryDungeon::transaction($cid,static function()use($cid,$cycle):void {
            if ((int)Db::value('SELECT cycle_id FROM herocon_views WHERE character_id=?',[$cid]) === (int)$cycle['id']) return;
            Db::exec('INSERT INTO herocon_views (character_id,cycle_id) VALUES (?,?) ON DUPLICATE KEY UPDATE cycle_id=VALUES(cycle_id)',[$cid,(int)$cycle['id']]);
            Character::bumpGoalStatFor($cid,'guild_competition_visit');
        });
    }

    public static function reward(int $cid,int $kind,bool $claim=false,bool $discard=false): array
    {
        if(!self::available())throw new GameError('errTournamentLocked');
        return StoryDungeon::transaction($cid,static function(array $char)use($cid,$kind,$claim,$discard):array {
            self::cycle();
            $row=Db::row('SELECT r.*,c.identifier,c.ts_end FROM herocon_rewards r JOIN herocon_cycles c ON c.id=r.cycle_id WHERE r.character_id=? AND r.kind=? AND r.claimed=0 ORDER BY r.id LIMIT 1 FOR UPDATE',[$cid,$kind]);
            if(!$row)throw new GameError($kind===5?'errClaimGuildCompetitionTournamentRewardsNoRewards':'errClaimSoloGuildCompetitionTournamentRewardsNoRewards');
            if($row['box_identifier']!==''&&(int)$row['item_id']===0) {
                $row['item_id']=Character::load($cid)->createSpecialItem($row['box_identifier'],10);
                Db::exec('UPDATE herocon_rewards SET item_id=? WHERE id=?',[(int)$row['item_id'],(int)$row['id']]);
            }
            $reward=['statPoints'=>(int)$row['stat_points']];if((int)$row['item_id']>0)$reward['item']=(int)$row['item_id'];
            $result=['identifier'=>$row['identifier'],'rewards'=>json_encode(['rank'=>(int)$row['rank_position'],'value'=>(int)$row['score'],'reward'=>json_encode($reward,JSON_THROW_ON_ERROR)],JSON_THROW_ON_ERROR)];
            if(!$claim)return $result;
            $item=(int)$row['item_id'];
            if($item>0) {
                if (!Db::row('SELECT id FROM items WHERE id=? AND character_id=? AND identifier=? FOR UPDATE',[$item,$cid,$row['box_identifier']])) throw new GameError('errInventoryInvalidItem');
                if($discard)Db::exec('DELETE FROM items WHERE id=? AND character_id=?',[$item,$cid]);
                else {
                    $inv=Db::row('SELECT * FROM inventory WHERE character_id=? FOR UPDATE',[$cid]);$c=Missiles::constants();
                    $limit=(int)$char['level']>=(int)$c['inventory_bag3_unlock_level']?18:((int)$char['level']>=(int)$c['inventory_bag2_unlock_level']?12:6);
                    $slot=null;for($i=1;$i<=$limit;$i++)if($inv&&(int)$inv['bag_item'.$i.'_id']===0){$slot='bag_item'.$i.'_id';break;}
                    if(!$slot)throw new GameError('errInventoryNoEmptySlot');
                    Db::exec("UPDATE inventory SET `$slot`=? WHERE character_id=?",[$item,$cid]);
                }
            }
            Db::exec('UPDATE `character` SET stat_points_available=stat_points_available+? WHERE id=?',[(int)$row['stat_points'],$cid]);
            if((int)$row['season_points']>0) {
                $p=Season::ensureProgress($cid);
                if($p['identifier']===$row['season_identifier']&&(int)$p['status']===2&&(int)$p['ts_end']>GameTime::now())Season::givePoints($cid,(int)$row['season_points']);
            }
            Db::exec('UPDATE herocon_rewards SET claimed=1 WHERE id=?',[(int)$row['id']]);
            if($kind===6) {
                Character::bumpGoalStatFor($cid,'solo_guild_competition_participated');
                foreach([1,10,50,100] as $rank)if((int)$row['rank_position']<=$rank)Character::bumpGoalStatFor($cid,'solo_guild_competition_top_'.$rank);
            } else {
                if ((int)$row['rank_position']<=100 && (int)$row['score']>=100) Character::bumpGoalStatFor($cid,'guild_competition_top_100');
                if((int)$row['rank_position']===1 && (int)$row['score']>=100)Character::bumpGoalStatFor($cid,'guild_competition_first_place');
                if((int)$row['score']>=3000)Character::bumpGoalStatFor($cid,'guild_competition_score_3000');
            }
            return $result;
        });
    }

    /** Âmbito local: XP/moedas de missões; fixado na geração das ofertas com o booster ativo. */
    public static function boost(int $gid): float
    {
        if($gid<1||!self::available())return 1;
        $g=Db::row('SELECT guild_competition_reward_boost_factor,ts_guild_competition_reward_boost_expires FROM `guild` WHERE id=?',[$gid]);
        return $g&&(int)$g['ts_guild_competition_reward_boost_expires']>GameTime::now()?1+max(0,(int)$g['guild_competition_reward_boost_factor'])/100:1;
    }
}
