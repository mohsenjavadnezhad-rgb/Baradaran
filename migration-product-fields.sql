-- Add country, manufacturer company to products
ALTER TABLE products ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER description;
ALTER TABLE products ADD COLUMN manufacturer VARCHAR(200) DEFAULT NULL AFTER country;

-- Create indexes
CREATE INDEX idx_country ON products(country);
CREATE INDEX idx_manufacturer ON products(manufacturer);