<?php
declare(strict_types=1);
namespace HeroZero;

/** Compras gratuitas do servidor privado; conteúdo validado pelo catálogo local. */
final class LocalPayments
{
    public static function catalog(?int $cid = null): array
    {
        $data=Live::template('getStandalonePaymentOffers');
        // Produto exigido por DialogBuyHideoutWorkerNew/Sd.requestProduct.
        // Preço de referência visual e disponibilidade são regras locais; não há cobrança.
        $methods=[];
        foreach($data['normal_offers'] as $normal) {
            if(isset($methods[$normal['payment_method']]))continue;
            $methods[$normal['payment_method']]=true;
            $worker=$data['special_offers'][0];
            $worker['payment_method']=$normal['payment_method'];
            $worker['currency']=$normal['currency'];$worker['locale']=$normal['locale'];
            $worker['price']='34.90';$worker['price_string']='34,90 R$';
            $worker['special_offer_id']='hideout_worker';
            $worker['premium_currency_amount']=0;
            $worker['content']=['premium_amount'=>0,'slotmachine_jeton_amount'=>0];
            $worker['display_info']=array_replace($worker['display_info'],[
                'name'=>'Segundo robô de trabalho','description'=>'Desbloqueia permanentemente o segundo robô de trabalho do esconderijo.',
                'index'=>31,'limit'=>1,'standalone_offer'=>true,'is_sidebar_offer'=>false,
                'background'=>'hideout_worker.webp','show_buy_button'=>true,'has_detail_page'=>false,'available_time_start'=>null,'available_time_end'=>null]);
            $data['special_offers'][]=$worker;
        }
        if ($cid===null) return $data;
        $cycle=Season::currentCycle();
        $casinoActive=Casino::current()!==null;
        $progress=Season::ensureProgress($cid);
        $uid=Character::load($cid)->userId();
        $counts=[];
        foreach(Db::rows('SELECT offer_key,COUNT(*) AS total FROM local_payments WHERE user_id=? GROUP BY offer_key',[$uid]) as $row) $counts[$row['offer_key']]=(int)$row['total'];
        foreach(['normal_offers','special_offers','consumable_offers'] as $group) {
            $offers=[];
            foreach($data[$group] as $offer) {
                $key=$offer['consumable_offer_id'] ?? $offer['special_offer_id'] ?? 'donuts_'.(int)($offer['content']['premium_amount'] ?? 0);
                if(str_starts_with($key,'pool_party_season_set_sale_')) continue;
                $general=$offer['display_info']['detail_page_rewards']['general'] ?? [];
                if(((int)($offer['content']['slotmachine_jeton_amount'] ?? 0)>0
                    || array_filter($general,static fn($r)=>(int)$r['type']===39)) && !$casinoActive) continue;
                if($key==='hideout_worker') {
                    $hideout=Hideout::rowFor($cid);
                    if(!$hideout || (int)$hideout['max_worker_count']>=2) continue;
                }
                if(str_ends_with($key,'_season_package')) {
                    $key=$cycle['identifier'].'_'.$cycle['ts_start'].'_season_package';
                    $offer['special_offer_id']=$key;
                    $offer['display_info']['name']='Passe da temporada atual';
                    $offer['display_info']['description']='Desbloqueia as recompensas premium da temporada atual.';
                    $offer['display_info']['background']=$cycle['identifier'].'_season_package.webp';
                    if($progress['premium_unlocked']) continue;
                }
                // As datas da captura de julho não são o calendário das ofertas locais.
                $offer['display_info']['available_time_start']=null;
                $offer['display_info']['available_time_end']=null;
                $limit=(int)($offer['display_info']['limit'] ?? 0);
                if($limit>0 && ($counts[$key] ?? 0)>=$limit) continue;
                $general=$offer['display_info']['detail_page_rewards']['general'] ?? [];
                if(array_filter($general,static fn($r)=>(int)$r['type']===45)) {
                    $treasure=EventTreasures::state($cid);
                    $current=EventTreasures::current();
                    if(!$current || !$treasure['id'] || $treasure['identifier']!==$current['identifier']) continue;
                }
                $offers[]=$offer;
            }
            $data[$group]=$offers;
        }
        return $data;
    }

    public static function resolve(array $params, ?int $cid = null): array
    {
        $group = ($params['action'] ?? '') === 'initConsumableOfferPayment' ? 'consumable_offers'
            : (!empty($params['special_offer_key']) ? 'special_offers' : 'normal_offers');
        $key = $group === 'consumable_offers' ? 'consumable_offer_id' : 'special_offer_id';
        $identifier = (string)($params[$group === 'consumable_offers' ? 'offer_identifier' : 'special_offer_key'] ?? '');
        foreach (self::catalog($cid)[$group] ?? [] as $offer) {
            if ((int)$offer['payment_method'] !== (int)($params['payment_method'] ?? 0)
                || $offer['currency'] !== ($params['currency'] ?? '')
                || $offer['locale'] !== ($params['locale'] ?? '')
                || !is_numeric($params['amount'] ?? null)
                || abs((float)$offer['price'] - (float)$params['amount']) > 0.001) continue;
            if ($group !== 'normal_offers' && ($offer[$key] ?? '') !== $identifier) continue;
            // No standalone, os donuts normais são identificados pelo preço/método.
            $content = $offer['content'] ?? [];
            if ($group === 'normal_offers' && isset($params['premium_amount'])
                && (int)$params['premium_amount'] !== (int)$content['premium_amount']) continue;
            return ['key'=>$group === 'normal_offers' ? 'donuts_'.(int)$content['premium_amount'] : $identifier,
                'group'=>$group, 'offer'=>$offer];
        }
        throw new GameError('errInitPaymentInvalidSpecialOffer');
    }

    public static function purchase(array $params): int
    {
        if ((getenv('HZ_ENV') ?: 'development') === 'production' && getenv('HZ_ALLOW_FAKE_PAYMENTS') !== '1') {
            throw new GameError('errPaymentNotAvailable');
        }
        if (filter_var($params['is_gift'] ?? false, FILTER_VALIDATE_BOOLEAN)) throw new GameError('errInitPaymentInvalidSpecialOffer');
        $userId = Live::currentUserId($params);
        $cid = Character::loadByUser($userId)->id();
        $resolvedKey=(string)($params['offer_identifier'] ?? $params['special_offer_key'] ?? '');
        $requestId = (string)($params['purchase_request_id'] ?? '');
        if ($requestId === '') $requestId = bin2hex(random_bytes(16)); // Clientes antigos: cada init é uma nova compra.
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $requestId)) throw new GameError('errRequestInvalidParameter');
        return StoryDungeon::transaction($cid, static function(array $char) use ($params,$resolvedKey,$requestId,$userId,$cid): int {
            $previous = Db::row('SELECT * FROM local_payments WHERE user_id=? AND request_id=? FOR UPDATE',[$userId,$requestId]);
            if ($previous) {
                if ($resolvedKey!=='' && $previous['offer_key'] !== $resolvedKey) throw new GameError('errInitPaymentInvalidSpecialOffer');
                if ($resolvedKey==='' && self::resolve($params)['key']!==$previous['offer_key']) throw new GameError('errInitPaymentInvalidSpecialOffer');
                return (int)$previous['id'];
            }
            $resolved=self::resolve($params,$cid);
            $offer = $resolved['offer']; $display = $offer['display_info'];
            if ((int)$char['level'] < (int)($display['min_level'] ?? 0)) throw new GameError('errUseItemItemReqLevel');
            $limit = (int)($display['limit'] ?? 0);
            if ($limit > 0 && (int)Db::value('SELECT COUNT(*) FROM local_payments WHERE user_id=? AND offer_key=?',[$userId,$resolved['key']]) >= $limit) {
                throw new GameError('errInitPaymentInvalidSpecialOffer');
            }
            $content = $offer['content'] ?? [];
            $identifiers = $display['item_identifiers'] ?? [];
            foreach (($display['detail_page_rewards']['items'] ?? []) as $reward) {
                if ((int)$reward['type'] !== 6 || (int)$reward['amount'] !== 1) throw new GameError('errInitPaymentInvalidSpecialOffer');
                $identifiers[] = $reward['data']['identifier'];
            }
            $templates = self::templates(); $equipment=[]; $pets=[];
            foreach ($identifiers as $identifier) {
                $tpl = $templates[$identifier] ?? throw new GameError('errInitPaymentInvalidSpecialOffer');
                if ((int)$tpl['type'] === 9) $pets[$identifier]=$tpl;
                elseif ((int)$tpl['type'] >= 1 && (int)$tpl['type'] <= 8) $equipment[$identifier]=$tpl;
                else throw new GameError('errInitPaymentInvalidSpecialOffer');
            }
            $inv = Db::row('SELECT * FROM inventory WHERE character_id=? FOR UPDATE',[$cid]) ?? throw new GameError('errInventoryInvalidItem');
            $constants = Missiles::constants(); $level=(int)$char['level'];
            $bagLimit = $level >= (int)$constants['inventory_bag3_unlock_level'] ? 18 : ($level >= (int)$constants['inventory_bag2_unlock_level'] ? 12 : 6);
            $slots=[];for($i=1;$i<=$bagLimit;$i++) if ((int)$inv['bag_item'.$i.'_id']===0) $slots[]='bag_item'.$i.'_id';
            if (count($slots)<count($equipment)) throw new GameError('errInventoryNoEmptySlot');
            $character = Character::load($cid); $delivered=[];
            foreach ($equipment as $identifier=>$tpl) {
                $itemId=$character->createEventRewardItem($identifier,(int)$tpl['type'],3);
                Db::exec('UPDATE inventory SET `'.array_shift($slots).'`=? WHERE character_id=?',[$itemId,$cid]);
                $delivered['items'][]=$itemId;
            }
            foreach ($pets as $identifier=>$tpl) {
                $quality=(int)$tpl['quality'];
                $petId=Sidekick::create($cid,$identifier,$quality,$character->randomSidekickBases($quality),1,0,Sidekick::skillsForIdentifier($identifier));
                Db::exec('UPDATE sidekicks SET item_improvement_id=1 WHERE id=? AND character_id=?',[$petId,$cid]);
                $orders=Sidekick::ordersData($cid);$orders['orders'][]=$petId;
                Sidekick::saveOrders($cid,$orders['orders'],$orders['favorit_orders']);
                Character::bumpGoalStatFor($cid,'first_sidekick_collected',1);
                Character::bumpGoalStatFor($cid,'sidekick_collected',1);
                $delivered['sidekicks'][]=$petId;
            }
            $premium=(int)($content['premium_amount'] ?? 0); $jetons=(int)($content['slotmachine_jeton_amount'] ?? 0);
            if ($premium>0) Db::exec('UPDATE `user` SET premium_currency=premium_currency+? WHERE id=?',[$premium,$userId]);
            if ($jetons>0) {
                $resources=json_decode((string)$char['unused_resources'],true) ?: [];
                $resources[4]=(int)($resources[4] ?? 0)+$jetons;
                Db::exec('UPDATE `character` SET unused_resources=? WHERE id=?',[json_encode((object)$resources,JSON_THROW_ON_ERROR),$cid]);
            }
            if ($resolved['key']==='hideout_worker') {
                $hideout=Db::row('SELECT * FROM hideout WHERE character_id=? FOR UPDATE',[$cid]);
                if(!$hideout || (int)$hideout['max_worker_count']!==1 || (int)$hideout['ts_time_worker_expires']>0) {
                    throw new GameError('errInitPaymentInvalidSpecialOffer');
                }
                // Preserva o robô ocupado; só acrescenta o novo robô livre.
                Db::exec('UPDATE hideout SET max_worker_count=2,idle_worker_count=idle_worker_count+1 WHERE id=?',[(int)$hideout['id']]);
                $delivered['hideout_worker']=1;
            }
            if (str_ends_with($resolved['key'],'_season_package')) {
                $progress=Season::ensureProgress($cid);
                // O pacote só pode desbloquear a temporada que anuncia.
                if (!str_starts_with($resolved['key'],$progress['identifier'].'_')) throw new GameError('errInitPaymentInvalidSpecialOffer');
                if ($progress['premium_unlocked']) throw new GameError('errInitPaymentInvalidSpecialOffer');
                Db::exec('UPDATE season_progress SET premium_unlocked=1 WHERE character_id=?',[$cid]);
                $delivered['season']=$progress['identifier'];
            }
            foreach (($display['detail_page_rewards']['general'] ?? []) as $reward) {
                if ((int)$reward['type']===39 && $jetons===(int)$reward['amount']) continue; // Mesmas fichas já creditadas no content.
                if ((int)$reward['type']===45) EventTreasures::grantPurchaseShovels($cid,(int)$reward['amount']);
                else throw new GameError('errInitPaymentInvalidSpecialOffer');
            }
            if (!$premium && !$jetons && !$identifiers && !$delivered && empty($display['detail_page_rewards']['general'])) throw new GameError('errInitPaymentInvalidSpecialOffer');
            $delivered['premium']=$premium;$delivered['jetons']=$jetons;
            Db::exec('INSERT INTO local_payments (user_id,character_id,request_id,offer_key,ts_created,rewards) VALUES (?,?,?,?,?,?)',
                [$userId,$cid,$requestId,$resolved['key'],GameTime::now(),json_encode($delivered,JSON_THROW_ON_ERROR)]);
            return (int)Db::pdo()->lastInsertId();
        });
    }

    public static function confirmed(int $userId,int $paymentId): bool
    {
        return $paymentId>0 && Db::row('SELECT id FROM local_payments WHERE id=? AND user_id=?',[$paymentId,$userId])!==null;
    }

    /** O cliente infere o produto pelos campos presentes; não anunciar outros sistemas. */
    public static function paymentStatus(int $userId,int $paymentId): array
    {
        $receipt=Db::row('SELECT offer_key,rewards FROM local_payments WHERE id=? AND user_id=?',[$paymentId,$userId]);
        $data=Live::accountState($userId,['last_payment_confirmed'=>$receipt!==null,'payment_id'=>$paymentId]);
        if(!$receipt) {
            unset($data['hideout'],$data['season_progress'],$data['season_rewards']);
            return $data;
        }
        $key=$receipt['offer_key'];
        if($key!=='hideout_worker') unset($data['hideout']);
        if(!str_ends_with($key,'_season_package')) unset($data['season_progress'],$data['season_rewards']);
        if($key==='hideout_worker') return $data; // Texto e notificação nativos do robô.
        $name='o pacote comprado';
        foreach(self::catalog() as $offers) {
            if(!is_array($offers)) continue;
            foreach($offers as $offer) {
                if(!is_array($offer)) continue;
                if(($offer['consumable_offer_id'] ?? $offer['special_offer_id'] ?? '')===$key) {
                    $name=$offer['display_info']['name'] ?? $name;
                    break 2;
                }
            }
        }
        $rewards=json_decode($receipt['rewards'],true,512,JSON_THROW_ON_ERROR);
        if(str_ends_with($key,'_season_package')) $name='o passe da temporada';
        elseif(str_starts_with($key,'donuts_')) $name=(int)($rewards['premium'] ?? substr($key,7)).' donuts';
        $data['local_payment_message']='Compra concluída: '.$name.'. O conteúdo foi creditado na tua conta.';
        return $data;
    }

    public static function templates(): array
    {
        $src=__DIR__.'/../../public/assets/data/constants_json.data';
        return AssetCache::slice('local_payment_templates_v1',$src,static function()use($src):array {
            return json_decode(gzuncompress(file_get_contents($src)),true,512,JSON_THROW_ON_ERROR)['item_templates'];
        });
    }
}
