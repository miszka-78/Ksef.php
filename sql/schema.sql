-- Database schema for KSeF Invoice Manager

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create entities table (companies/organizations with KSeF access)
CREATE TABLE IF NOT EXISTS entities (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    tax_id VARCHAR(20) NOT NULL,
    ksef_identifier VARCHAR(100),
    ksef_token TEXT,
    ksef_token_expiry TIMESTAMP WITH TIME ZONE,
    ksef_environment VARCHAR(10) NOT NULL DEFAULT 'test',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(tax_id, ksef_environment)
);

-- Create user_entity_access table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS user_entity_access (
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    entity_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
    can_view BOOLEAN NOT NULL DEFAULT true,
    can_download BOOLEAN NOT NULL DEFAULT false,
    can_export BOOLEAN NOT NULL DEFAULT false,
    PRIMARY KEY (user_id, entity_id)
);

-- Create invoice_templates table
CREATE TABLE IF NOT EXISTS invoice_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    html_content TEXT NOT NULL,
    css_content TEXT,
    is_default BOOLEAN NOT NULL DEFAULT false,
    entity_id INTEGER REFERENCES entities(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    ksef_reference_number VARCHAR(100) NOT NULL UNIQUE,
    entity_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
    invoice_number VARCHAR(100) NOT NULL,
    issue_date DATE NOT NULL,
    seller_name VARCHAR(255) NOT NULL,
    seller_tax_id VARCHAR(20) NOT NULL,
    buyer_name VARCHAR(255) NOT NULL,
    buyer_tax_id VARCHAR(20) NOT NULL,
    total_net NUMERIC(15, 2) NOT NULL,
    total_gross NUMERIC(15, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PLN',
    invoice_type VARCHAR(50) NOT NULL,
    xml_content TEXT NOT NULL,
    is_archived BOOLEAN NOT NULL DEFAULT false,
    is_exported BOOLEAN NOT NULL DEFAULT false,
    archived_at TIMESTAMP WITH TIME ZONE,
    export_date TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create invoice_items table
CREATE TABLE IF NOT EXISTS invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    quantity NUMERIC(15, 3) NOT NULL,
    unit VARCHAR(20),
    unit_price_net NUMERIC(15, 2) NOT NULL,
    net_value NUMERIC(15, 2) NOT NULL,
    vat_rate VARCHAR(10) NOT NULL,
    vat_value NUMERIC(15, 2) NOT NULL,
    gross_value NUMERIC(15, 2) NOT NULL
);

-- Create export_batches table
CREATE TABLE IF NOT EXISTS export_batches (
    id SERIAL PRIMARY KEY,
    entity_id INTEGER NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id),
    export_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(255) NOT NULL,
    invoice_count INTEGER NOT NULL,
    status VARCHAR(20) NOT NULL,
    symfonia_format VARCHAR(50) NOT NULL DEFAULT 'FK'
);

-- Create export_batch_invoices table
CREATE TABLE IF NOT EXISTS export_batch_invoices (
    batch_id INTEGER NOT NULL REFERENCES export_batches(id) ON DELETE CASCADE,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    PRIMARY KEY (batch_id, invoice_id)
);

-- Create a table for user activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    entity_id INTEGER REFERENCES entities(id) ON DELETE SET NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create index for common queries
CREATE INDEX IF NOT EXISTS idx_invoices_entity_id ON invoices(entity_id);
CREATE INDEX IF NOT EXISTS idx_invoices_issue_date ON invoices(issue_date);
CREATE INDEX IF NOT EXISTS idx_invoices_seller_tax_id ON invoices(seller_tax_id);
CREATE INDEX IF NOT EXISTS idx_invoices_buyer_tax_id ON invoices(buyer_tax_id);
CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice_id ON invoice_items(invoice_id);
CREATE INDEX IF NOT EXISTS idx_user_entity_access_user_id ON user_entity_access(user_id);
CREATE INDEX IF NOT EXISTS idx_user_entity_access_entity_id ON user_entity_access(entity_id);

-- Create an admin user with password 'admin' (hashed)
-- IMPORTANT: Change the password in production
INSERT INTO users (username, password, email, full_name, role)
VALUES ('admin', '$2y$10$XDLbq1JzEGKt5XyqvcyNK.OfT4zDJViSJUQgbXu8N7fBzlPZAqZl2', 'admin@example.com', 'System Administrator', 'admin')
ON CONFLICT (username) DO NOTHING;
