-- =============================================================================
-- KND RETAIL SaaS MODULE — Database Schema
-- Compatible: MySQL 8.0+ / MariaDB 10.5+
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- All tables prefixed with retail_ (except multi-tenant foundation tables)
-- business_id ALWAYS injected server-side — NEVER from client input
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- MULTI-TENANCY FOUNDATION
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS businesses (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)    NOT NULL,
  base_currency   ENUM('USD','EUR') NOT NULL DEFAULT 'USD',
  owner_user_id   INT UNSIGNED    NOT NULL,          -- FK → users.id (dr_user_id)
  active          TINYINT(1)      NOT NULL DEFAULT 1,
  settings_json   JSON,                              -- Extensible config por negocio
  created_at      DATETIME        NOT NULL DEFAULT NOW(),
  INDEX idx_owner (owner_user_id),
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un usuario puede pertenecer a múltiples negocios con roles distintos
CREATE TABLE IF NOT EXISTS business_users (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  user_id         INT UNSIGNED    NOT NULL,          -- FK → users.id (dr_user_id)
  role            ENUM('admin','cashier') NOT NULL DEFAULT 'cashier',
  created_at      DATETIME        NOT NULL DEFAULT NOW(),
  UNIQUE KEY  uq_biz_user   (business_id, user_id),
  INDEX       idx_user      (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- CATÁLOGO DE PRODUCTOS
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS retail_products (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  sku             VARCHAR(50)     DEFAULT NULL,       -- Código de producto (escalabilidad)
  name            VARCHAR(200)    NOT NULL,
  price_base      DECIMAL(12,4)   NOT NULL,           -- Siempre en base_currency del negocio
  stock           INT             NOT NULL DEFAULT 0,
  min_stock       INT             NOT NULL DEFAULT 0,
  active          TINYINT(1)      NOT NULL DEFAULT 1,
  created_at      DATETIME        NOT NULL DEFAULT NOW(),
  INDEX       idx_business  (business_id),
  INDEX       idx_name      (business_id, name),
  INDEX       idx_sku       (business_id, sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- CLIENTES
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS retail_customers (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  name            VARCHAR(150)    NOT NULL,
  document_id     VARCHAR(50)     DEFAULT NULL,       -- Cédula / RIF / pasaporte
  created_at      DATETIME        NOT NULL DEFAULT NOW(),
  INDEX       idx_business    (business_id),
  -- document_id único por negocio (evita duplicados silenciosos)
  UNIQUE KEY  uq_doc_per_biz  (business_id, document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- TASAS DE CAMBIO (append-only — snapshot histórico)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS retail_exchange_rates (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  currency        VARCHAR(10)     NOT NULL,           -- VES, USD, EUR, COP, etc.
  rate_to_base    DECIMAL(18,6)   NOT NULL,           -- Cuántas unidades locales = 1 base
  created_at      DATETIME        NOT NULL DEFAULT NOW(),
  -- Índice optimizado para obtener la tasa vigente más reciente por moneda
  INDEX idx_latest_lookup (business_id, currency, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- VENTAS
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS retail_sales (
  id                      INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id             INT UNSIGNED    NOT NULL,
  customer_id             INT UNSIGNED    DEFAULT NULL,  -- NULL = venta anónima
  cashier_user_id         INT UNSIGNED    NOT NULL,
  total_base              DECIMAL(12,4)   NOT NULL,      -- Total en base_currency
  total_local             DECIMAL(18,4)   NOT NULL,      -- Total en moneda usada
  currency_used           VARCHAR(10)     NOT NULL,      -- Moneda de la transacción
  exchange_rate_snapshot  DECIMAL(18,6)   NOT NULL,      -- Tasa al momento — NUNCA recalcular
  type                    ENUM('cash','credit') NOT NULL DEFAULT 'cash',
  idempotency_key         VARCHAR(64)     DEFAULT NULL,  -- Prevenir doble-POST
  invoice_number          VARCHAR(50)     DEFAULT NULL,  -- INV-{biz}-{year}-{seq}
  created_at              DATETIME        NOT NULL DEFAULT NOW(),
  INDEX       idx_business_date   (business_id, created_at),
  INDEX       idx_cashier         (business_id, cashier_user_id),
  INDEX       idx_customer        (business_id, customer_id),
  -- Idempotency: misma key no puede crear dos ventas en el mismo negocio
  UNIQUE KEY  uq_idempotency      (business_id, idempotency_key),
  -- invoice_number único por negocio
  UNIQUE KEY  uq_invoice          (business_id, invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items de cada venta (price_snapshot INMUTABLE — nunca tocar después de INSERT)
CREATE TABLE IF NOT EXISTS retail_sale_items (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  sale_id         INT UNSIGNED    NOT NULL,
  product_id      INT UNSIGNED    NOT NULL,
  qty             INT             NOT NULL,
  price_snapshot  DECIMAL(12,4)   NOT NULL,           -- Precio al momento de la venta
  INDEX idx_sale      (sale_id),
  INDEX idx_product   (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- CRÉDITO DE CLIENTES
-- -----------------------------------------------------------------------------

-- Balance actual (siempre se recalcula con credit_transactions, pero se cachea aquí)
CREATE TABLE IF NOT EXISTS retail_credits (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  customer_id     INT UNSIGNED    NOT NULL,
  balance         DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
  updated_at      DATETIME        NOT NULL DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY  uq_biz_customer (business_id, customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ledger de movimientos de crédito (append-only)
CREATE TABLE IF NOT EXISTS retail_credit_transactions (
  id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  credit_id           INT UNSIGNED    NOT NULL,
  amount              DECIMAL(12,4)   NOT NULL,   -- Positivo = pago, negativo = deuda
  type                ENUM('debit','payment') NOT NULL,
  reference_sale_id   INT UNSIGNED    DEFAULT NULL,
  created_at          DATETIME        NOT NULL DEFAULT NOW(),
  INDEX idx_credit        (credit_id),
  INDEX idx_reference     (reference_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- SOLICITUDES DE RESTOCK
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS retail_restock_requests (
  id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  product_name    VARCHAR(200)    NOT NULL,
  requested_by    INT UNSIGNED    DEFAULT NULL,   -- user_id que lo solicitó
  status          ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending',
  created_at      DATETIME        NOT NULL DEFAULT NOW(),
  INDEX idx_business          (business_id),
  INDEX idx_business_status   (business_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- AUDIT LOGS (CRÍTICO — inmutables, append-only)
-- before_json / after_json: snapshot completo del estado antes y después
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS retail_audit_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id     INT UNSIGNED    NOT NULL,
  user_id         INT UNSIGNED    DEFAULT NULL,
  action          VARCHAR(100)    NOT NULL,       -- create_sale, adjust_stock, etc.
  entity_type     VARCHAR(50)     DEFAULT NULL,   -- product, sale, credit, rate
  entity_id       INT UNSIGNED    DEFAULT NULL,
  before_json     JSON            DEFAULT NULL,
  after_json      JSON            DEFAULT NULL,
  ip_address      VARCHAR(45)     DEFAULT NULL,
  created_at      DATETIME(3)     NOT NULL DEFAULT NOW(3),  -- Precisión ms
  -- Consultas de negocio
  INDEX idx_business_date (business_id, created_at),
  INDEX idx_entity        (business_id, entity_type, entity_id),
  -- Detección rápida de fraude / actividad sospechosa por usuario
  INDEX idx_user          (business_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SAMPLE DATA (comentado — descomentar para testing)
-- =============================================================================
-- INSERT INTO businesses (name, base_currency, owner_user_id) VALUES ('Demo Shop', 'USD', 1);
-- INSERT INTO business_users (business_id, user_id, role) VALUES (1, 1, 'admin');
-- INSERT INTO retail_products (business_id, sku, name, price_base, stock, min_stock) VALUES
--   (1, 'COCA001', 'Coca Cola 600ml', 1.50, 100, 10),
--   (1, 'AZUC001', 'Azúcar 1kg', 0.80, 50, 5),
--   (1, 'LECHE001', 'Leche entera 1L', 1.20, 30, 5);
-- INSERT INTO retail_exchange_rates (business_id, currency, rate_to_base) VALUES
--   (1, 'USD', 1.000000),
--   (1, 'VES', 36.500000);

SET FOREIGN_KEY_CHECKS = 1;
