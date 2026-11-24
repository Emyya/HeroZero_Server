CREATE TABLE IF NOT EXISTS local_payments (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  character_id INT NOT NULL,
  request_id VARCHAR(80) NOT NULL,
  offer_key VARCHAR(190) NOT NULL,
  ts_created INT NOT NULL,
  rewards LONGTEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY user_request (user_id, request_id),
  KEY user_offer (user_id, offer_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
