CREATE TABLE nexus_districts (
    district_id VARCHAR(255) PRIMARY KEY,
    memory DECIMAL(5,2) CHECK (memory >= 0 AND memory <= 100),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);