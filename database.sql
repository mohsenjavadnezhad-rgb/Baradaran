-- AutoPartsShop Database Schema
-- MySQL 5.7+

-- Categories (Brands/Cars)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    parent_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(500) NOT NULL,
    technical_number VARCHAR(200) NOT NULL,
    description TEXT,
    retail_price DECIMAL(15,0) NOT NULL DEFAULT 0,
    wholesale_price DECIMAL(15,0) NOT NULL DEFAULT 0,
    wholesale_min_qty INT NOT NULL DEFAULT 5,
    stock INT NOT NULL DEFAULT 0,
    image VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_technical_number (technical_number),
    INDEX idx_name (name),
    FULLTEXT idx_ft_search (name, technical_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product-Category many-to-many
CREATE TABLE product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Images Gallery
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(300) NOT NULL,
    customer_mobile VARCHAR(20) NOT NULL,
    customer_address TEXT NOT NULL,
    total_amount DECIMAL(15,0) NOT NULL DEFAULT 0,
    status ENUM('pending','confirmed','shipped','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(500) NOT NULL,
    price DECIMAL(15,0) NOT NULL,
    quantity INT NOT NULL,
    price_type ENUM('retail','wholesale') NOT NULL,
    subtotal DECIMAL(15,0) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert brands (parent categories)
INSERT INTO categories (name, slug, parent_id) VALUES
('ایران خودرو', 'ikco', NULL),
('سایپا', 'saipa', NULL),
('پارس خودرو', 'pars-khodro', NULL),
('کرمان موتور', 'kerman-motor', NULL),
('ام وی ام', 'mvm', NULL),
('بهمن موتور', 'bahman-motor', NULL),
('زامیاد', 'zamyad', NULL),
('گروه بهمن', 'bahman-group', NULL),
('خودروسازی راین', 'rayen', NULL),
('کیا', 'kia', NULL),
('هیوندای', 'hyundai', NULL),
('تویوتا', 'toyota', NULL),
('میتسوبیشی', 'mitsubishi', NULL),
('نیسان', 'nissan', NULL),
('رنو', 'renault', NULL),
('پژو', 'peugeot', NULL),
('سیتروئن', 'citroen', NULL),
('بی ام و', 'bmw', NULL),
('مرسدس بنز', 'mercedes-benz', NULL),
('آئودی', 'audi', NULL),
('فولکس واگن', 'volkswagen', NULL),
('لیفان', 'lifan', NULL),
('جک', 'jac', NULL),
('چری', 'chery', NULL),
('گک', 'gac', NULL),
('بی وای دی', 'byd', NULL),
('جیلی', 'geely', NULL),
('چانگان', 'changan', NULL),
('هاوال', 'haval', NULL),
('فونیکس', 'fonix', NULL),
('لاماری', 'lamari', NULL),
('تیگارد', 'tigard', NULL),
('اسکای ول', 'skywell', NULL),
('کی ام سی', 'kmc', NULL),
('آمیکو', 'amico', NULL),
('خودروسازی فردا', 'farda', NULL),
('سورن', 'soren', NULL);

-- Insert models for Iran Khodro (id=1)
INSERT INTO categories (name, slug, parent_id) VALUES
('پژو 206', 'peugeot-206', 1),
('پژو 206 صندوقدار', 'peugeot-206-sedan', 1),
('پژو 207', 'peugeot-207', 1),
('پژو 207 صندوقدار', 'peugeot-207-sedan', 1),
('پژو پارس', 'peugeot-pars', 1),
('پژو 405', 'peugeot-405', 1),
('سمند', 'samand', 1),
('سمند سورن', 'samand-soren', 1),
('دنا', 'dena', 1),
('دنا پلاس', 'dena-plus', 1),
('رانا', 'rana', 1),
('رانا پلاس', 'rana-plus', 1),
('تارا', 'tara', 1),
('هایما S5', 'haima-s5', 1),
('هایما S7', 'haima-s7', 1),
('پیکان', 'peykan', 1),
('آریسان', 'arisan', 1),
('وانت آریسان', 'arisan-pickup', 1);

-- Insert models for Saipa (id=2)
INSERT INTO categories (name, slug, parent_id) VALUES
('پراید 111', 'pride-111', 2),
('پراید 131', 'pride-131', 2),
('پراید 132', 'pride-132', 2),
('پراید 151', 'pride-151', 2),
('تیبا', 'tiba', 2),
('تیبا 2', 'tiba-2', 2),
('ساینا', 'saina', 2),
('کوییک', 'quik', 2),
('شاهین', 'shahin', 2),
('اطلس', 'atlas', 2),
('وانت 151', 'pride-pickup', 2),
('وانت زامیاد', 'zamyad-pickup', 2),
('نیسان پیکاپ', 'nissan-pickup', 2);

-- Insert models for Pars Khodro (id=3)
INSERT INTO categories (name, slug, parent_id) VALUES
('رنو ساندرو', 'renault-sandero', 3),
('رنو ساندرو استپ وی', 'renault-sandero-stepway', 3),
('رنو تندر 90', 'renault-tondar-90', 3),
('رنو تندر پلاس', 'renault-tondar-plus', 3),
('رنو کپچر', 'renault-captur', 3),
('نیسان قشقایی', 'nissan-qashqai', 3),
('نیسان جوک', 'nissan-juke', 3),
('وانت نیسان', 'nissan-pickup-pars', 3);

-- Insert models for Kerman Motor (id=4)
INSERT INTO categories (name, slug, parent_id) VALUES
('جک J3', 'jac-j3', 4),
('جک J4', 'jac-j4', 4),
('جک J5', 'jac-j5', 4),
('جک S3', 'jac-s3', 4),
('جک S5', 'jac-s5', 4),
('کی ام سی K7', 'kmc-k7', 4),
('کی ام سی T8', 'kmc-t8', 4),
('کی ام سی J7', 'kmc-j7', 4),
('لیفان 620', 'lifan-620', 4),
('لیفان 820', 'lifan-820', 4),
('لیفان X50', 'lifan-x50', 4),
('لیفان X60', 'lifan-x60', 4);

-- Insert models for MVM / Managers Khodro (id=5)
INSERT INTO categories (name, slug, parent_id) VALUES
('MVM 110', 'mvm-110', 5),
('MVM 315', 'mvm-315', 5),
('MVM 530', 'mvm-530', 5),
('MVM 550', 'mvm-550', 5),
('MVM X22', 'mvm-x22', 5),
('MVM X33', 'mvm-x33', 5),
('MVM X55', 'mvm-x55', 5),
('فونیکس آریزو 5', 'fonix-arrizo-5', 5),
('فونیکس آریزو 6', 'fonix-arrizo-6', 5),
('فونیکس تیگو 7', 'fonix-tiggo-7', 5),
('فونیکس تیگو 8', 'fonix-tiggo-8', 5),
('لاماری ایما', 'lamari-ima', 5),
('لاماری ایکس', 'lamari-x', 5);

-- Insert models for Bahman Motor (id=6)
INSERT INTO categories (name, slug, parent_id) VALUES
('مزدا 3', 'mazda-3', 6),
('مزدا 6', 'mazda-6', 6),
('بسترن B30', 'besturn-b30', 6),
('بسترن B50', 'besturn-b50', 6),
('دیگنیتی', 'dignity', 6),
('فیدلیتی', 'fidelity', 6),
('ریسپکت', 'respect', 6);

-- Insert models for Zamyad (id=7)
INSERT INTO categories (name, slug, parent_id) VALUES
('وانت زامیاد', 'zamyad-truck', 7),
('وانت پادرا', 'padra', 7),
('وانت کارون', 'karun', 7);

-- Insert models for Bahman Group (id=8)
INSERT INTO categories (name, slug, parent_id) VALUES
('وانت کاپرا', 'capra', 8);

-- Insert models for Rayen (id=9)
INSERT INTO categories (name, slug, parent_id) VALUES
('راین V5', 'rayen-v5', 9);

-- Insert models for Kia (id=10)
INSERT INTO categories (name, slug, parent_id) VALUES
('سراتو', 'cerato', 10),
('اپتیما', 'optima', 10),
('اسپورتیج', 'sportage', 10),
('سورنتو', 'sorento', 10),
('پیکانتو', 'picanto', 10),
('ریو', 'rio', 10),
('سول', 'soul', 10);

-- Insert models for Hyundai (id=11)
INSERT INTO categories (name, slug, parent_id) VALUES
('النترا', 'elantra', 11),
('سوناتا', 'sonata', 11),
('توسان', 'tucson', 11),
('سانتافه', 'santa-fe', 11),
('i10', 'hyundai-i10', 11),
('i20', 'hyundai-i20', 11),
('i30', 'hyundai-i30', 11),
('ورنا', 'verna', 11),
('وراکروز', 'veracruz', 11),
('جنسیس', 'genesis', 11);

-- Insert models for Toyota (id=12)
INSERT INTO categories (name, slug, parent_id) VALUES
('کرولا', 'corolla', 12),
('کمری', 'camry', 12),
('یاریس', 'yaris', 12),
('راوا 4', 'rav4', 12),
('لندکروزر', 'land-cruiser', 12),
('پرویا', 'previa', 12),
('هایلوکس', 'hilux', 12),
('پریوس', 'prius', 12);

-- Insert models for Mitsubishi (id=13)
INSERT INTO categories (name, slug, parent_id) VALUES
('لنسر', 'lancer', 13),
('گالانت', 'galant', 13),
('اوتلندر', 'outlander', 13),
('پاجرو', 'pajero', 13),
('ASX', 'mitsubishi-asx', 13);

-- Insert models for Nissan (id=14)
INSERT INTO categories (name, slug, parent_id) VALUES
('ماکسیما', 'maxima', 14),
('آلتیما', 'altima', 14),
('تیانا', 'teana', 14),
('مورانو', 'murano', 14),
('ایکس تریل', 'x-trail', 14),
('پتفایندر', 'pathfinder', 14),
('جوک', 'juke', 14),
('قشقایی', 'qashqai', 14);

-- Insert models for Renault (id=15)
INSERT INTO categories (name, slug, parent_id) VALUES
('کلیو', 'clio', 15),
('مگان', 'megane', 15),
('لاگونا', 'laguna', 15),
('کولئوس', 'koleos', 15);

-- Insert models for Peugeot (id=16)
INSERT INTO categories (name, slug, parent_id) VALUES
('206', 'peugeot-206-int', 16),
('207', 'peugeot-207-int', 16),
('208', 'peugeot-208', 16),
('301', 'peugeot-301', 16),
('308', 'peugeot-308', 16),
('508', 'peugeot-508', 16),
('2008', 'peugeot-2008', 16),
('3008', 'peugeot-3008', 16);

-- Insert models for Citroen (id=17)
INSERT INTO categories (name, slug, parent_id) VALUES
('C3', 'citroen-c3', 17),
('C4', 'citroen-c4', 17),
('C5', 'citroen-c5', 17);

-- Insert models for BMW (id=18)
INSERT INTO categories (name, slug, parent_id) VALUES
('سری 3', 'bmw-3-series', 18),
('سری 5', 'bmw-5-series', 18),
('سری 7', 'bmw-7-series', 18),
('X1', 'bmw-x1', 18),
('X3', 'bmw-x3', 18),
('X5', 'bmw-x5', 18),
('X6', 'bmw-x6', 18);

-- Insert models for Mercedes-Benz (id=19)
INSERT INTO categories (name, slug, parent_id) VALUES
('C-Class', 'mercedes-c-class', 19),
('E-Class', 'mercedes-e-class', 19),
('S-Class', 'mercedes-s-class', 19),
('GLC', 'mercedes-glc', 19),
('GLE', 'mercedes-gle', 19);

-- Insert models for Audi (id=20)
INSERT INTO categories (name, slug, parent_id) VALUES
('A3', 'audi-a3', 20),
('A4', 'audi-a4', 20),
('A5', 'audi-a5', 20),
('A6', 'audi-a6', 20),
('Q3', 'audi-q3', 20),
('Q5', 'audi-q5', 20),
('Q7', 'audi-q7', 20);

-- Insert models for Volkswagen (id=21)
INSERT INTO categories (name, slug, parent_id) VALUES
('گلف', 'golf', 21),
('پاسات', 'passat', 21),
('جتا', 'jetta', 21),
('پولو', 'polo', 21),
('تیگوان', 'tiguan', 21);

-- Insert models for Lifan (id=22)
INSERT INTO categories (name, slug, parent_id) VALUES
('لیفان 520', 'lifan-520', 22),
('لیفان 620', 'lifan-620-int', 22),
('لیفان 820', 'lifan-820-int', 22),
('لیفان X50', 'lifan-x50-int', 22),
('لیفان X60', 'lifan-x60-int', 22);

-- Insert models for JAC (id=23)
INSERT INTO categories (name, slug, parent_id) VALUES
('جک J4', 'jac-j4-int', 23),
('جک J5', 'jac-j5-int', 23),
('جک S3', 'jac-s3-int', 23),
('جک S5', 'jac-s5-int', 23);

-- Insert models for Chery (id=24)
INSERT INTO categories (name, slug, parent_id) VALUES
('چری اریزو 5', 'chery-arrizo-5', 24),
('چری اریزو 6', 'chery-arrizo-6', 24),
('چری تیگو 5', 'chery-tiggo-5', 24),
('چری تیگو 7', 'chery-tiggo-7', 24),
('چری تیگو 8', 'chery-tiggo-8', 24);

-- Insert models for GAC (id=25)
INSERT INTO categories (name, slug, parent_id) VALUES
('گک GS3', 'gac-gs3', 25),
('گک امپو', 'gac-empow', 25);

-- Insert models for BYD (id=26)
INSERT INTO categories (name, slug, parent_id) VALUES
('بی وای دی S6', 'byd-s6', 26),
('بی وای دی F3', 'byd-f3', 26),
('بی وای دی دلفین', 'byd-dolphin', 26),
('بی وای دی آتو 3', 'byd-atto-3', 26);

-- Insert models for Geely (id=27)
INSERT INTO categories (name, slug, parent_id) VALUES
('جیلی GC6', 'geely-gc6', 27),
('جیلی امگرند 7', 'geely-emgrand-7', 27),
('جیلی کول ری', 'geely-coolray', 27);

-- Insert models for Changan (id=28)
INSERT INTO categories (name, slug, parent_id) VALUES
('چانگان CS35', 'changan-cs35', 28),
('چانگان CS55', 'changan-cs55', 28),
('چانگان ایدو', 'changan-eado', 28);

-- Insert models for Haval (id=29)
INSERT INTO categories (name, slug, parent_id) VALUES
('هاوال H6', 'haval-h6', 29),
('هاوال جولیون', 'haval-jolion', 29);

-- Insert models for Fonix (id=30)
INSERT INTO categories (name, slug, parent_id) VALUES
('فونیکس FX', 'fonix-fx', 30),
('فونیکس تیگو 8 پرو', 'fonix-tiggo-8-pro', 30);

-- Insert models for Lamari (id=31)
INSERT INTO categories (name, slug, parent_id) VALUES
('لاماری ایما', 'lamari-ima-int', 31);

-- Insert models for Tigard (id=32)
INSERT INTO categories (name, slug, parent_id) VALUES
('تیگارد X35', 'tigard-x35', 32);

-- Insert models for Skywell (id=33)
INSERT INTO categories (name, slug, parent_id) VALUES
('اسکای ول ET5', 'skywell-et5', 33);

-- Insert models for KMC (id=34)
INSERT INTO categories (name, slug, parent_id) VALUES
('کی ام سی T8', 'kmc-t8-int', 34),
('کی ام سی K7', 'kmc-k7-int', 34);

-- Insert models for Amico (id=35)
INSERT INTO categories (name, slug, parent_id) VALUES
('آمیکو آسنا', 'amico-asena', 35);

-- Insert models for Farda (id=36)
INSERT INTO categories (name, slug, parent_id) VALUES
('فردا SX5', 'farda-sx5', 36);

-- Insert models for Soren (id=37)
INSERT INTO categories (name, slug, parent_id) VALUES
('سورن پلاس', 'soren-plus', 37);

-- Insert sample products
INSERT INTO products (name, technical_number, description, retail_price, wholesale_price, wholesale_min_qty, stock, is_active) VALUES
('لنت ترمز جلو پژو 206', 'LN-206-001', 'لنت ترمز جلو با کیفیت بالا مناسب پژو 206 تیپ 2 و 5', 850000, 780000, 5, 50, 1),
('دیسک ترمز جلو پژو 206', 'DSK-206-002', 'دیسک ترمز جلو اورجینال پژو 206', 2200000, 2050000, 3, 30, 1),
('فیلتر روغن پژو 206', 'FO-206-003', 'فیلتر روغن اصلی مناسب پژو 206', 350000, 310000, 10, 100, 1),
('کمک فنر جلو پراید', 'KF-PR-001', 'کمک فنر جلو شرکتی پراید', 1200000, 1100000, 4, 25, 1),
('صفحه کلاچ پراید', 'SK-PR-002', 'صفحه کلاچ با کیفیت مناسب پراید', 1800000, 1650000, 3, 20, 1),
('فیلتر هوا تیبا', 'FA-TB-001', 'فیلتر هوای اصلی تیبا', 420000, 380000, 10, 80, 1),
('شمع موتور سمند', 'SH-SM-001', 'شمع موتور EF7 مناسب سمند', 280000, 250000, 10, 120, 1),
('تسمه دینام سراتو', 'TD-CR-001', 'تسمه دینام کیا سراتو اصلی', 950000, 880000, 5, 35, 1);

-- Link products to categories using slug lookups
INSERT INTO product_categories (product_id, category_id)
SELECT 1, id FROM categories WHERE slug = 'peugeot-206';
INSERT INTO product_categories (product_id, category_id)
SELECT 2, id FROM categories WHERE slug = 'peugeot-206';
INSERT INTO product_categories (product_id, category_id)
SELECT 3, id FROM categories WHERE slug = 'peugeot-206';
INSERT INTO product_categories (product_id, category_id)
SELECT 4, id FROM categories WHERE slug = 'pride-111';
INSERT INTO product_categories (product_id, category_id)
SELECT 5, id FROM categories WHERE slug = 'pride-111';
INSERT INTO product_categories (product_id, category_id)
SELECT 6, id FROM categories WHERE slug = 'tiba';
INSERT INTO product_categories (product_id, category_id)
SELECT 7, id FROM categories WHERE slug = 'samand';
INSERT INTO product_categories (product_id, category_id)
SELECT 8, id FROM categories WHERE slug = 'cerato';

-- یک ادمین بسازید و رمزش را با password_hash('...', PASSWORD_DEFAULT) بگذارید.
-- (هش واقعی که اینجا بود عمداً حذف شد — دقیقاً همان چیزی است که روی
-- yadakii.ir زنده به کار می‌رود، پس هرگز نباید در گیت‌هاب بماند.)
-- INSERT INTO admins (username, password_hash) VALUES ('admin', '<hash-here>');