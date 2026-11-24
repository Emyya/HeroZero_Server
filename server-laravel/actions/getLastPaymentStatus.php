<?php
declare(strict_types=1);
use HeroZero\{LocalPayments,Live};
return function(array $params): array {
    return LocalPayments::paymentStatus(Live::currentUserId($params),(int)($params['payment_id'] ?? 0));
};
