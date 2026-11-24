<?php
declare(strict_types=1);

/**
 * action: createCharacter  (tela de criacao do heroi, apos registerUserSSO)
 * Entrada: gender, hair_color, skin_color, hair_type, head_type, eyes_type,
 *          eyebrows_type, nose_type, mouth_type, facial_hair_type, decoration_type
 * Cria o character (nome vazio; setCharacterName roda logo em seguida no fluxo real)
 * com a aparencia escolhida. Idempotente: se a conta ja tem character, so reaplica
 * a aparencia enviada em vez de falhar (o cliente pode reenviar em retry de rede).
 * SEM captura real desta resposta no HAR; shape best-effort (boot completo).
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new GameError('errRequestInvalidParameter');
    }
    $gender = ((string)($params['gender'] ?? 'm')) === 'f' ? 'f' : 'm';

    $pdo = Db::pdo();
    $nested = $pdo->inTransaction();
    if ($nested) $pdo->exec('SAVEPOINT create_character');
    else $pdo->beginTransaction();
    try {
        // O nome é opcional no cliente oficial, que chama setCharacterName depois.
        // O provisionamento dos bots envia-o já aqui para nunca publicar um herói
        // sem nome se a resposta HTTP ou uma etapa posterior falhar.
        $botName = null;
        if (array_key_exists('name', $params)) {
            $source = (string)Db::value('SELECT registration_source FROM `user` WHERE id = ?', [$userId]);
            if ($source === 'bot_population') $botName = (string)$params['name'];
        }
        try {
            $char = Character::loadByUser($userId);
        } catch (GameError $e) {
            $char = Character::createNew($userId, '', $gender);
        }
        if ($botName !== null && $char->name() === '') $char->setName($botName);
        $char->setAppearance($params);
        $data = Live::accountState($userId);
        if ($nested) $pdo->exec('RELEASE SAVEPOINT create_character');
        else $pdo->commit();
        return $data;
    } catch (\Throwable $e) {
        // Um deadlock InnoDB pode cancelar a transação inteira e apagar o
        // savepoint; não mascarar o erro original com "SAVEPOINT inexistente".
        if ($pdo->inTransaction()) {
            if ($nested) {
                $pdo->exec('ROLLBACK TO SAVEPOINT create_character');
                $pdo->exec('RELEASE SAVEPOINT create_character');
            } else $pdo->rollBack();
        }
        throw $e;
    }
};
