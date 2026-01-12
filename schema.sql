CREATE TABLE IF NOT EXISTS office_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  unique_file_key VARCHAR(32) PRIMARY KEY,
  document_name VARCHAR(255) NOT NULL,
  referring_to VARCHAR(255) NULL,
  document_type VARCHAR(64) NOT NULL,
  source_username VARCHAR(64) NOT NULL,
  receiver_username VARCHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_docs_receiver (receiver_username),
  INDEX idx_docs_source (source_username),
  CONSTRAINT fk_docs_source FOREIGN KEY (source_username) REFERENCES office_accounts(username),
  CONSTRAINT fk_docs_receiver FOREIGN KEY (receiver_username) REFERENCES office_accounts(username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

