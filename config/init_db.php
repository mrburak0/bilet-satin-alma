<?php
require_once __DIR__ . '/db.php';


$queries = [

    "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT CHECK(role IN ('user', 'firma_admin', 'admin')) NOT NULL DEFAULT 'user',
        firm_id INTEGER,
        credit REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );",

    "CREATE TABLE IF NOT EXISTS firms (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT
    );",


    "CREATE TABLE IF NOT EXISTS trips (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        firm_id INTEGER NOT NULL,
        from_city TEXT NOT NULL,
        to_city TEXT NOT NULL,
        date TEXT NOT NULL,
        time TEXT NOT NULL,
        price REAL NOT NULL,
        seat_count INTEGER NOT NULL,
        FOREIGN KEY(firm_id) REFERENCES firms(id)
    );",


    "CREATE TABLE IF NOT EXISTS tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        trip_id INTEGER NOT NULL,
        seat_no INTEGER NOT NULL,
        price REAL NOT NULL,
        coupon_code TEXT,
        status TEXT CHECK(status IN ('active', 'cancelled')) DEFAULT 'active',
        purchase_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(trip_id) REFERENCES trips(id)
    );",

 
    "CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        discount_percent INTEGER NOT NULL,
        usage_limit INTEGER DEFAULT 1,
        used_count INTEGER DEFAULT 0,
        expiry_date TEXT NOT NULL,
        firm_id INTEGER,
        is_global INTEGER DEFAULT 0
    );"
];

foreach ($queries as $sql) {
    $db->exec($sql);
}

echo "Veritabanı başarıyla oluşturuldu";
?>
