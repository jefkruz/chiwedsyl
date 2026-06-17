<?php
$db = null;

function getDb(): PDO {
    global $db;
    if ($db !== null) return $db;
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    initSchema($db);
    return $db;
}

function initSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            num_guests INTEGER DEFAULT 1,
            qr_code TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            checked_in INTEGER DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS gift_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            image_path TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS receipts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            guest_name TEXT NOT NULL,
            guest_email TEXT NOT NULL,
            gift_item_id INTEGER,
            receipt_path TEXT NOT NULL,
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_item_id) REFERENCES gift_items(id)
        );
        CREATE TABLE IF NOT EXISTS site_settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );
        CREATE TABLE IF NOT EXISTS admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS gallery_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_path TEXT NOT NULL,
            caption TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS well_wishes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_name TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS gift_transfers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            gift_item_id INTEGER,
            guest_name TEXT NOT NULL,
            amount TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_item_id) REFERENCES gift_items(id)
        );
    ");
    // Insert default admin if none exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
    if ($stmt && (int)$stmt->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO admin (username, password_hash) VALUES (?, ?)")
            ->execute([ADMIN_USER, password_hash(ADMIN_PASS_PLAIN, PASSWORD_DEFAULT)]);
    }
    // Add new columns to guests if missing (migration)
    $info = $pdo->query("PRAGMA table_info(guests)")->fetchAll(PDO::FETCH_ASSOC);
    $cols = array_column($info, 'name');
    if (!in_array('invited_by', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN invited_by TEXT");
    }
    if (!in_array('guest_photo_path', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN guest_photo_path TEXT");
    }
    if (!in_array('registration_confirmed', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN registration_confirmed INTEGER DEFAULT 0");
        // Existing RSVPs were already treated as complete; keep their passes valid.
        $pdo->exec("UPDATE guests SET registration_confirmed = 1");
    }
    if (!in_array('first_name', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN first_name TEXT");
        $pdo->exec("ALTER TABLE guests ADD COLUMN last_name TEXT");
        $pdo->exec("ALTER TABLE guests ADD COLUMN gender TEXT");
    }
    if (!in_array('title', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN title TEXT");
    }
    if (!in_array('checked_in_at', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN checked_in_at TEXT");
    }
    if (!in_array('check_in_count', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN check_in_count INTEGER DEFAULT 0");
        $pdo->exec("UPDATE guests SET check_in_count = COALESCE(num_guests, 1) WHERE checked_in = 1 AND COALESCE(check_in_count, 0) = 0");
    }
    if (!in_array('whatsapp_invite_sent_at', $cols)) {
        $pdo->exec("ALTER TABLE guests ADD COLUMN whatsapp_invite_sent_at TEXT");
    }
    // One-time: old RSVP stored 2 for a party of 3 at the gate; now num_guests = total including self.
    $partyMigrated = $pdo->query("SELECT value FROM site_settings WHERE key = 'guest_party_total_includes_self' LIMIT 1")->fetchColumn();
    if ($partyMigrated !== '1') {
        $pdo->exec('UPDATE guests SET num_guests = num_guests + 1 WHERE num_guests > 1');
        $pdo->prepare("INSERT OR REPLACE INTO site_settings (key, value) VALUES ('guest_party_total_includes_self', '1')")->execute();
    }
    // Add price to gift_items if missing
    $giftInfo = $pdo->query("PRAGMA table_info(gift_items)")->fetchAll(PDO::FETCH_ASSOC);
    $giftCols = array_column($giftInfo, 'name');
    if (!in_array('price', $giftCols)) {
        $pdo->exec("ALTER TABLE gift_items ADD COLUMN price TEXT");
    }
    // Well wishes table for old installs
    $pdo->exec("CREATE TABLE IF NOT EXISTS well_wishes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_name TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    // Gift transfers table for old installs
    $pdo->exec("CREATE TABLE IF NOT EXISTS gift_transfers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gift_item_id INTEGER,
        guest_name TEXT NOT NULL,
        amount TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_item_id) REFERENCES gift_items(id)
    )");
    // Gallery table created in schema above; ensure it exists for old installs
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        image_path TEXT NOT NULL,
        caption TEXT,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}
