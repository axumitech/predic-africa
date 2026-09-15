-- backend/schema.sql

-- Types énumérés pour la sécurité des données
CREATE TYPE order_status AS ENUM ('OPEN', 'PARTIAL', 'FILLED', 'CANCELLED');
CREATE TYPE outcome_type AS ENUM ('YES', 'NO');
CREATE TYPE market_status AS ENUM ('ACTIVE', 'RESOLVED', 'CLOSED');

-- Table des utilisateurs
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Table des portefeuilles (Wallets) avec contrainte stricte sur le solde
CREATE TABLE wallets (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    balance DECIMAL(20, 4) NOT NULL DEFAULT 0.0000,
    currency VARCHAR(10) DEFAULT 'XOF',
    CONSTRAINT positive_balance CHECK (balance >= 0) -- Empêche formellement un solde négatif en DB
);

-- Table des transactions pour l'historique financier complet
CREATE TABLE transactions (
    id SERIAL PRIMARY KEY,
    wallet_id INT NOT NULL REFERENCES wallets(id),
    type VARCHAR(50) NOT NULL, -- 'DEPOSIT', 'WITHDRAW', 'TRADE', 'FEE', 'RESOLUTION'
    amount DECIMAL(20, 4) NOT NULL, -- Peut être négatif ou positif selon le mouvement
    reference VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Table des marchés prédictifs
CREATE TABLE markets (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    status market_status DEFAULT 'ACTIVE',
    winning_outcome outcome_type, -- Renseigné uniquement à la résolution
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Table du pool de liquidité CPMM (Constant Product Market Maker)
CREATE TABLE cpmm_pools (
    market_id INT PRIMARY KEY REFERENCES markets(id),
    yes_pool DECIMAL(20, 4) NOT NULL DEFAULT 0.0000,
    no_pool DECIMAL(20, 4) NOT NULL DEFAULT 0.0000,
    k_constant DECIMAL(40, 8) NOT NULL DEFAULT 0.00000000
);

-- Table du carnet d'ordres (Limit Orders)
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    market_id INT NOT NULL REFERENCES markets(id),
    user_id INT NOT NULL REFERENCES users(id),
    outcome outcome_type NOT NULL,
    price DECIMAL(10, 4) NOT NULL CHECK (price > 0 AND price < 1), -- Prix par part (ex: 0.85 XOF/part)
    quantity DECIMAL(20, 4) NOT NULL,
    remaining_quantity DECIMAL(20, 4) NOT NULL,
    status order_status DEFAULT 'OPEN',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Table des positions (Portefeuille d'actifs par utilisateur)
CREATE TABLE positions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id),
    market_id INT NOT NULL REFERENCES markets(id),
    outcome outcome_type NOT NULL,
    shares DECIMAL(20, 4) NOT NULL DEFAULT 0.0000,
    total_invested DECIMAL(20, 4) NOT NULL DEFAULT 0.0000,
    CONSTRAINT unique_position UNIQUE (user_id, market_id, outcome)
);

-- INDEXATION OPTIMISÉE POUR LE CARNET D'ORDRES
-- Optimise la recherche "Price-Time Priority" : prix ascendant ou descendant, puis chronologique
CREATE INDEX idx_orders_price_time_asc ON orders (market_id, outcome, price ASC, created_at ASC);
CREATE INDEX idx_orders_price_time_desc ON orders (market_id, outcome, price DESC, created_at ASC);
CREATE INDEX idx_orders_status ON orders (status);

-- INDEXATION POUR LES RÉSOLUTIONS
CREATE INDEX idx_positions_market_outcome ON positions (market_id, outcome);
CREATE INDEX idx_wallets_user ON wallets (user_id);
