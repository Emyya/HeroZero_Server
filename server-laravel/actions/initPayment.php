<?php
declare(strict_types=1);
use HeroZero\{LocalPayments,Live};
return function(array $params): array {
    $paymentId=LocalPayments::purchase($params);
    return Live::accountState(Live::currentUserId($params),[
        'payment_id'=>$paymentId,
        'payment_link'=>'/payment-success.html?payment_id='.$paymentId,
        'last_payment_confirmed'=>true,
    ]);
};
