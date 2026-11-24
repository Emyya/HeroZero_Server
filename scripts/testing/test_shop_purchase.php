<?php
declare(strict_types=1);
// PDO simulado; não acede à BD nem altera saldos reais.
require dirname(__DIR__, 2) . '/server-laravel/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/server-laravel/app/HeroZero/Protocol.php';
use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;

final class ShopPDO extends PDO
{
    public array $char = ['id' => 1, 'user_id' => 10, 'level' => 300, 'game_currency' => 1000, 'tutorial_flags' => ''];
    public array $user = ['id' => 10, 'premium_currency' => 100];
    public array $inv;
    public array $item = ['id' => 20, 'character_id' => 1, 'type' => 6, 'required_level' => 300, 'premium_item' => 0, 'buy_price' => 100];
    public bool $failDebit = false;
    private bool $active = false;
    private array $snapshots = [];
    public int $affected = 1;
    public function __construct() {
        $this->inv = ['character_id' => 1, 'weapon_item_id' => 30, 'suit_item_id' => 31, 'shop_item1_id' => 20];
        foreach (['mask','cape','belt','boots','gadget','missiles'] as $slot) $this->inv[$slot . '_item_id'] = 0;
        for ($i = 1; $i <= 18; $i++) $this->inv['bag_item' . $i . '_id'] = 0;
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ShopStatement($this, preg_replace('/\s+/', ' ', trim($query))); }
    public function inTransaction(): bool { return $this->active; }
    private function snapshot(): array { return [$this->char, $this->user, $this->inv]; }
    public function beginTransaction(): bool { $this->snapshots['outer'] = $this->snapshot(); $this->active = true; return true; }
    public function commit(): bool { $this->active = false; return true; }
    public function rollBack(): bool { [$this->char, $this->user, $this->inv] = $this->snapshots['outer']; $this->active = false; return true; }
    public function exec(string $sql): int|false {
        if (str_starts_with($sql, 'SAVEPOINT ')) $this->snapshots[substr($sql, 10)] = $this->snapshot();
        elseif (str_starts_with($sql, 'ROLLBACK TO SAVEPOINT ')) [$this->char, $this->user, $this->inv] = $this->snapshots[substr($sql, 22)];
        elseif (str_starts_with($sql, 'RELEASE SAVEPOINT ')) unset($this->snapshots[substr($sql, 18)]);
        else throw new RuntimeException('SQL inesperado: ' . $sql);
        return 0;
    }
    public function run(string $sql, array $p): array {
        $this->affected = 1;
        if (str_starts_with($sql, 'SELECT * FROM `character`')) return [$this->char];
        if ($sql === 'SELECT id FROM `user` WHERE id = ? FOR UPDATE' || $sql === 'SELECT * FROM `user` WHERE id = ?') return [$this->user];
        if ($sql === 'SELECT * FROM `inventory` WHERE character_id = ? FOR UPDATE') return [$this->inv];
        if ($sql === 'SELECT * FROM `items` WHERE id = ? AND character_id = ? FOR UPDATE') return $p === [20, 1] ? [$this->item] : [];
        if (str_starts_with($sql, 'UPDATE `inventory` SET ')) {
            preg_match_all('/`([a-z0-9_]+)` = (\?|0)/', $sql, $fields, PREG_SET_ORDER); $i = 0;
            foreach ($fields as $f) $this->inv[$f[1]] = $f[2] === '?' ? $p[$i++] : 0;
            return [];
        }
        if (str_starts_with($sql, 'UPDATE `character` SET game_currency = game_currency - ?')) {
            if ($this->failDebit || $this->char['game_currency'] < $p[2]) $this->affected = 0;
            else $this->char['game_currency'] -= $p[0];
            return [];
        }
        if (str_starts_with($sql, 'UPDATE `user` SET premium_currency = premium_currency - ?')) {
            if ($this->failDebit || $this->user['premium_currency'] < $p[2]) $this->affected = 0;
            else $this->user['premium_currency'] -= $p[0];
            return [];
        }
        if ($sql === 'UPDATE `character` SET tutorial_flags = ? WHERE id = ?') { $this->char['tutorial_flags'] = $p[0]; return []; }
        throw new RuntimeException('SQL inesperado: ' . $sql);
    }
}
final class ShopStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private ShopPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->db->run($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function rowCount(): int { return $this->db->affected; }
}
function fixture(): ShopPDO { $db = new ShopPDO(); (new ReflectionProperty(Db::class, 'pdo'))->setValue(null, $db); return $db; }
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "OK: $message\n"; }
function reject(ShopPDO $db, int $target, string $error): void {
    $before = [$db->inv, $db->char, $db->user];
    try { Character::load(1)->buyShopItem(20, $target); } catch (GameError $e) {
        check($e->getMessage() === $error && [$db->inv, $db->char, $db->user] === $before, 'Rejeição e rollback: ' . $error); return;
    }
    throw new RuntimeException('Compra devia ter falhado');
}
try {
    foreach ([9, 11, 26] as $target) {
        $db = fixture(); $db->item['type'] = $target === 11 ? 3 : 6;
        Character::load(1)->buyShopItem(20, $target);
        check($db->inv['bag_item' . ($target - 8) . '_id'] === 20 && $db->inv['weapon_item_id'] === 30 && $db->inv['suit_item_id'] === 31, "Destino $target vai para mochila sem equipar (HAR)");
        check($db->inv['shop_item1_id'] === 0 && $db->char['game_currency'] === 900, 'Remove da loja e debita uma vez');
        reject($db, $target, 'errInventoryInvalidItem');
    }
    $db = fixture(); Character::load(1)->buyShopItem(20, 6);
    check($db->inv['weapon_item_id'] === 20 && $db->inv['bag_item1_id'] === 30, 'Equipar conserva arma anterior');
    $db = fixture(); $db->inv['bag_item3_id'] = 40; Character::load(1)->buyShopItem(20, 11);
    check($db->inv['bag_item3_id'] === 20 && $db->inv['bag_item1_id'] === 40, 'Destino ocupado conserva item deslocado');
    $db = fixture(); for ($i = 1; $i <= 18; $i++) $db->inv['bag_item' . $i . '_id'] = 40 + $i;
    reject($db, 9, 'errInventoryNoEmptySlot'); reject($db, 6, 'errInventoryNoEmptySlot');
    $db->inv['bag_item18_id'] = 0; Character::load(1)->buyShopItem(20, 26);
    check($db->inv['bag_item18_id'] === 20, 'Compra cabe na última vaga');
    foreach ([0, 3, 27] as $target) { $db = fixture(); reject($db, $target, 'errRequestInvalidParameter'); }
    $db = fixture(); $db->item['required_level'] = 301; reject($db, 6, 'errInventoryInvalidItem');
    $db = fixture(); $db->char['game_currency'] = 99; reject($db, 9, 'errRemoveGameCurrencyNotEnough');
    $db = fixture(); $db->failDebit = true; reject($db, 9, 'errRemoveGameCurrencyNotEnough');
    $db = fixture(); $db->item['premium_item'] = 1; $db->item['buy_price'] = 5;
    Character::load(1)->buyShopItem(20, 9); check($db->user['premium_currency'] === 95 && $db->char['game_currency'] === 1000, 'Compra premium usa donuts');
    $db = fixture(); $db->item['type'] = 11; Character::load(1)->buyShopItem(20, 11);
    check($db->inv['bag_item3_id'] === 20 && $db->char['game_currency'] === 900, 'Consumível respeita mochila e moeda do preço');
    $db = fixture(); $db->beginTransaction(); Character::load(1)->buyShopItem(20, 9);
    check($db->inTransaction(), 'Compra usa savepoint na transação exterior'); $db->rollBack();
    check($db->inv['shop_item1_id'] === 20 && $db->char['game_currency'] === 1000, 'Rollback exterior reverte compra');
    echo "PASS: compra com PDO simulado; integração MySQL não validada.\n";
} catch (Throwable $e) { fwrite(STDERR, 'FALHOU: ' . $e->getMessage() . "\n"); exit(1); }
