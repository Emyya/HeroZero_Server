<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\GameTime;
use HeroZero\Live;

/** Registo atómico usado exclusivamente pelo provisionamento local dos bots. */
return function (array $params): array {
    $email = trim((string)($params['email'] ?? ''));
    $password = (string)($params['password'] ?? '');
    $name = trim((string)($params['name'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100
        || strlen($password) < 4 || $name === '' || mb_strlen($name) > 32) {
        throw new GameError('errRequestInvalidParameter');
    }
    $gender = (string)($params['gender'] ?? 'm') === 'f' ? 'f' : 'm';
    $session = bin2hex(random_bytes(15));
    $pdo = Db::pdo();
    $nested = $pdo->inTransaction();
    if ($nested) $pdo->exec('SAVEPOINT register_bot_account');
    else $pdo->beginTransaction();
    try {
        if (Db::value('SELECT id FROM `user` WHERE email = ? LIMIT 1', [$email]) !== null) {
            throw new GameError('errRegisterEmailAlreadyExists');
        }
        Db::exec(
            "INSERT INTO `user`
                (email, password_hash, session_id, premium_currency, locale, confirmed, trusted,
                 network, ts_creation, registration_source, last_login_ip, login_count, ts_last_login)
             VALUES (?, ?, ?, 30, 'pt_BR', 0, 0, 'guest', ?, 'bot_population', ?, 1, ?)",
            [$email, password_hash($password, PASSWORD_DEFAULT), $session,
             GameTime::now(), (string)($_SERVER['REMOTE_ADDR'] ?? ''), GameTime::now()]
        );
        $userId = (int)$pdo->lastInsertId();
        $char = Character::createNew($userId, '', $gender);
        $char->setName($name);
        $char->setAppearance($params);
        $data = Live::accountState($userId);
        if ($nested) $pdo->exec('RELEASE SAVEPOINT register_bot_account');
        else $pdo->commit();
        return $data;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            if ($nested) {
                $pdo->exec('ROLLBACK TO SAVEPOINT register_bot_account');
                $pdo->exec('RELEASE SAVEPOINT register_bot_account');
            } else $pdo->rollBack();
        }
        throw $e;
    }
};
