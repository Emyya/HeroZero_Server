<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__,2).'/server-laravel/app/HeroZero/Protocol.php';
$app = require dirname(__DIR__,2).'/server-laravel/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/** Simula apenas as queries do resgate; SQL desconhecido faz o teste falhar. */
final class VoucherPDO extends PDO
{
    public array $char = ['id'=>1,'user_id'=>10,'quest_energy'=>100,'training_count'=>10,
        'active_quest_booster_id'=>'booster_quest3','ts_active_quest_boost_expires'=>0];
    public array $coupons = [];
    public array $redemptions = [];
    public array $hideout = ['current_resource_glue'=>100,'max_resource_glue'=>100,'current_resource_stone'=>0,'max_resource_stone'=>100];
    private bool $active = false;
    private array $snapshots = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new VoucherStatement($this,$query); }
    public function inTransaction(): bool { return $this->active; }
    private function snapshot(): array { return [$this->char,$this->coupons,$this->redemptions,$this->hideout]; }
    private function restore(array $s): void { [$this->char,$this->coupons,$this->redemptions,$this->hideout] = $s; }
    public function beginTransaction(): bool { $this->snapshots['outer']=$this->snapshot(); $this->active=true; return true; }
    public function commit(): bool { $this->active=false; return true; }
    public function rollBack(): bool { $this->restore($this->snapshots['outer']); $this->active=false; return true; }
    public function exec(string $statement): int|false {
        if ($statement === 'SAVEPOINT user_vouchers') $this->snapshots['save']=$this->snapshot();
        elseif ($statement === 'ROLLBACK TO SAVEPOINT user_vouchers') $this->restore($this->snapshots['save']);
        elseif ($statement !== 'RELEASE SAVEPOINT user_vouchers') throw new RuntimeException('SQL inesperado: '.$statement);
        return 0;
    }
    public function seed(string $code, array $reward, int $owner=10): void {
        $this->coupons[$code]=['id'=>count($this->coupons)+1,'user_id'=>$owner,'status'=>1,'uses_current'=>0,'rewards'=>json_encode($reward)];
    }
    public function queryFixture(string $sql, array $p): array {
        if (str_starts_with($sql,'SELECT * FROM `character`')) return [$this->char];
        if (str_starts_with($sql,'SELECT user_id FROM `character`')) return [['user_id'=>$this->char['user_id']]];
        if (str_starts_with($sql,'INSERT INTO vouchers')) { $this->seed($p[0],json_decode($p[1],true),(int)$p[2]); return []; }
        if (str_starts_with($sql,'SELECT * FROM vouchers')) return isset($this->coupons[$p[0]]) ? [$this->coupons[$p[0]]] : [];
        if (str_starts_with($sql,'SELECT * FROM hideout')) return [$this->hideout];
        if (str_starts_with($sql,'UPDATE `character` SET quest_energy')) { $this->char['quest_energy']+=$p[0]; $this->char['training_count']+=$p[1]; return []; }
        if (str_starts_with($sql,'UPDATE vouchers SET uses_current')) { foreach ($this->coupons as &$v) if ($v['id']===$p[0]) $v['uses_current']=1; return []; }
        if (str_starts_with($sql,'INSERT INTO voucher_redemptions')) { $this->redemptions[]=$p; return []; }
        if (str_starts_with($sql,'UPDATE hideout SET current_resource_glue')) { $this->hideout['current_resource_glue']+=$p[0]; $this->hideout['current_resource_stone']+=$p[1]; return []; }
        if (str_starts_with($sql,'UPDATE `character` SET `active_quest_booster_id`')) { $this->char['active_quest_booster_id']=$p[0]; $this->char['ts_active_quest_boost_expires']=$p[1]; return []; }
        throw new RuntimeException('SQL inesperado: '.$sql);
    }
}
final class VoucherStatement extends PDOStatement
{
    private array $rows=[];
    public function __construct(private VoucherPDO $db, private string $sql) {}
    public function execute(?array $params=null): bool { $this->rows=$this->db->queryFixture($this->sql,$params??[]); return true; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return array_shift($this->rows) ?? false; }
    public function rowCount(): int { return 1; }
    public function fetchColumn(int $column=0): mixed { $r=array_shift($this->rows); return $r ? array_values($r)[$column] : false; }
}
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: {$message}\n"; }
function rejects(callable $fn,string $error): void {
    try { $fn(); } catch (HeroZero\GameError $e) { check($e->getMessage()===$error,$error); return; }
    throw new RuntimeException('Esperado erro '.$error);
}
try {
    $db=new VoucherPDO();
    $property=new ReflectionProperty(HeroZero\Db::class,'pdo');
    $property->setValue(null,$db);
    $db->seed('msg_1_a',['quest_energy'=>20]);
    $db->seed('msg_1_b',['training_sessions'=>5]);
    $r=HeroZero\UserVouchers::redeem(1,['msg_1_a','msg_1_b']);
    check($r===['quest_energy'=>20,'training_sessions'=>5] && $db->char['quest_energy']===120 && $db->char['training_count']===15,'Entrega lote sem truncar energia/unidades');
    check(count($db->redemptions)===2 && !$db->inTransaction(),'Entrega e marcação em transação');
    rejects(fn()=>HeroZero\UserVouchers::redeem(1,['msg_1_a']),'errInvalidateAlreadyRedeemed');
    $db->seed('msg_1_c',['quest_energy'=>5]);
    $db->seed('msg_1_d',['hideout_glue'=>10]);
    rejects(fn()=>HeroZero\UserVouchers::redeem(1,['msg_1_c','msg_1_d']),'errRedeemVoucherGlueFull');
    check($db->char['quest_energy']===120 && $db->coupons['msg_1_c']['uses_current']===0 && count($db->redemptions)===2,'Depósito cheio reverte energia e consumo de todo o lote');
    $db->seed('msg_2_foreign',['quest_energy'=>1],20);
    rejects(fn()=>HeroZero\UserVouchers::redeem(1,['msg_2_foreign']),'errCheckVoucherRequirementsWrongUser');
    $db->seed('msg_1_boost',['quest_booster'=>['type'=>50,'duration'=>1]]);
    $base=HeroZero\GameTime::now()+100;
    $db->char['ts_active_quest_boost_expires']=$base;
    HeroZero\UserVouchers::redeem(1,['msg_1_boost']);
    check($db->char['ts_active_quest_boost_expires']===$base+86400,'Energético prolonga duração sem donuts');
    $db->beginTransaction();
    HeroZero\UserVouchers::redeem(1,['msg_1_c']);
    check($db->inTransaction() && $db->char['quest_energy']===125,'Savepoint não faz commit da transação exterior');
    $db->rollBack();
    check($db->char['quest_energy']===120 && $db->coupons['msg_1_c']['uses_current']===0,'Rollback exterior preserva cupão');
    $deliver=new ReflectionMethod(HeroZero\Season::class,'deliverReward');
    foreach ([6=>'training_sessions',8=>'quest_energy',11=>'hideout_glue',12=>'hideout_stone'] as $type=>$key) {
        $count=count($db->coupons);
        $deliver->invoke(null,1,['reward_type'=>$type,'reward_factor'=>60]);
        $coupon=end($db->coupons);
        check(count($db->coupons)===$count+1 && json_decode($coupon['rewards'],true)===[$key=>60],"Dispatch real tipo {$type} cria cupão {$key}, sem XP/atributos indevidos");
    }
    $property->setValue(null,null);
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
