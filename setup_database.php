<?php
require_once 'config/database.php';

echo "<h2>Lost & Found Database Setup for MariaDB 11.5.2 has been initiated.</h2>";

try {
    $database = new Database();
    $pdo = $database->getConnection();

    // Testing MariaDB connection and version
    $version_info = $database->testConnection();
    if ($version_info) {
        echo "<p style='color: green;'>✓ Connected to MariaDB Version: " . $version_info['version'] . "</p>";
    }

    // Set MariaDB specific settings for better performance
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    $pdo->exec("SET innodb_strict_mode = 1");

    echo "<h3>Creating Database Tables...</h3>";

    // ! Creating DATABASE tables 😀. 191 was set due to utf encoding errors 
    // ? InnoDB engine is used for storage engine. Foreign keys maintaining relationships between tables, recovery.

    $queries = [
        "CREATE TABLE IF NOT EXISTS User (
            User_ID VARCHAR(191) PRIMARY KEY,
            Name VARCHAR(255) NOT NULL,
            Email VARCHAR(191) UNIQUE NOT NULL,
            Phone VARCHAR(191) UNIQUE NOT NULL,
            Password_Hash VARCHAR(255) NOT NULL,
            Profile_Picture VARCHAR(500) NULL,
            Email_Notifications TINYINT(1) NOT NULL DEFAULT 1,
            Push_Notifications TINYINT(1) NOT NULL DEFAULT 1,
            Role ENUM('user', 'admin', 'staff') NOT NULL DEFAULT 'user',  -- Mainly user based, admin and staff might be added later.
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Category (
            Category_ID VARCHAR(191) PRIMARY KEY,
            Category_Name VARCHAR(191) UNIQUE NOT NULL,
            Description TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Location (
            Location_ID VARCHAR(191) PRIMARY KEY,
            Location_Name VARCHAR(255) NOT NULL,
            Description TEXT,
            Address TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Event (
            Event_ID VARCHAR(191) PRIMARY KEY,
            Event_Name VARCHAR(255) NOT NULL,
            Event_Date TIMESTAMP NOT NULL,
            Description TEXT,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Event_Location (
            Event_ID VARCHAR(191) NOT NULL,
            Location_ID VARCHAR(191) NOT NULL,
            PRIMARY KEY (Event_ID, Location_ID),
            FOREIGN KEY (Event_ID) REFERENCES Event(Event_ID) ON DELETE CASCADE,
            FOREIGN KEY (Location_ID) REFERENCES Location(Location_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Item (
            Item_ID VARCHAR(191) PRIMARY KEY,
            Creator_ID VARCHAR(191) NOT NULL,
            Category_ID VARCHAR(191) NOT NULL,
            Location_ID VARCHAR(191) NOT NULL,
            Event_ID VARCHAR(191) NULL,
            Description TEXT NOT NULL,
            Priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
            Status ENUM('reported', 'claimed', 'returned', 'expired', 'donated') NOT NULL DEFAULT 'Reported',

            Item_Type ENUM('lost', 'found') NOT NULL DEFAULT 'lost',
            Reported_Date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Expiration_Date TIMESTAMP NOT NULL,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (Creator_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            FOREIGN KEY (Category_ID) REFERENCES Category(Category_ID),
            FOREIGN KEY (Location_ID) REFERENCES Location(Location_ID),
            FOREIGN KEY (Event_ID) REFERENCES Event(Event_ID) ON DELETE SET NULL,
            INDEX idx_status (Status),
            INDEX idx_reported_date (Reported_Date),
            INDEX idx_category (Category_ID),
            INDEX idx_location (Location_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Attachment (
            Attachment_ID VARCHAR(191) PRIMARY KEY,
            Item_ID VARCHAR(191) NULL,
            Report_ID VARCHAR(191) NULL,
            File_URL VARCHAR(500) NOT NULL,
            File_Name VARCHAR(255) NOT NULL,
            File_Type VARCHAR(50) NOT NULL,
            File_Size INT UNSIGNED,
            Uploaded_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE,
            INDEX idx_item (Item_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Claim (
            Claim_ID VARCHAR(191) PRIMARY KEY,
            Item_ID VARCHAR(191) NOT NULL,
            User_ID VARCHAR(191) NOT NULL,
            Claim_Status ENUM('pending', 'approved', 'rejected', 'withdrawn') NOT NULL DEFAULT 'pending',
            Claim_Date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Approved_Date TIMESTAMP NULL,
            Notes TEXT,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE,
            FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            INDEX idx_status (Claim_Status),
            INDEX idx_item (Item_ID),
            INDEX idx_user (User_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Verification_Question (
            Question_ID VARCHAR(191) PRIMARY KEY,
            Claim_ID VARCHAR(191) NOT NULL,
            Question_Text TEXT NOT NULL,
            Answer_Text TEXT NOT NULL,
            Question_Order TINYINT UNSIGNED NOT NULL DEFAULT 1,
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Claim_ID) REFERENCES Claim(Claim_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Donation (
            Donation_ID VARCHAR(191) PRIMARY KEY,
            Item_ID VARCHAR(191) NOT NULL,
            Donor_ID VARCHAR(191) NOT NULL,
            Donation_Date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Recipient_Organization VARCHAR(255),
            Notes TEXT,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE,
            FOREIGN KEY (Donor_ID) REFERENCES User(User_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Report (
            Report_ID VARCHAR(191) PRIMARY KEY,
            Reporter_ID VARCHAR(191) NOT NULL,
            Item_ID VARCHAR(191) NOT NULL,
            Reason ENUM('inappropriate', 'spam', 'fake', 'duplicate', 'other') NOT NULL,
            Description TEXT,
            Status ENUM('pending', 'reviewed', 'resolved', 'dismissed') NOT NULL DEFAULT 'pending',
            Reported_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Reporter_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Reward (
            Reward_ID VARCHAR(191) PRIMARY KEY,
            Item_ID VARCHAR(191) NOT NULL,
            Reward_Type ENUM('money', 'gift_card', 'service', 'other') NOT NULL,
            Description TEXT,
            Amount DECIMAL(10,2) UNSIGNED,
            Currency VARCHAR(3) DEFAULT 'USD',
            Status ENUM('offered', 'claimed', 'paid', 'cancelled') NOT NULL DEFAULT 'offered',
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Conversation (
            Conversation_ID VARCHAR(191) PRIMARY KEY,
            Item_ID VARCHAR(191) NOT NULL,
            Claim_ID VARCHAR(191) NULL,
            Participant1_ID VARCHAR(191) NOT NULL,
            Participant2_ID VARCHAR(191) NOT NULL,
            Status ENUM('active', 'closed', 'archived') NOT NULL DEFAULT 'active',
            Created_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE,
            FOREIGN KEY (Claim_ID) REFERENCES Claim(Claim_ID) ON DELETE SET NULL,
            FOREIGN KEY (Participant1_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            FOREIGN KEY (Participant2_ID) REFERENCES User(User_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Chat_Message (
            Message_ID VARCHAR(191) PRIMARY KEY,
            Conversation_ID VARCHAR(191) NOT NULL,
            Sender_ID VARCHAR(191) NOT NULL,
            Message_Text TEXT NOT NULL,
            Message_Type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text',
            Is_Read BOOLEAN NOT NULL DEFAULT FALSE,
            Sent_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Conversation_ID) REFERENCES Conversation(Conversation_ID) ON DELETE CASCADE,
            FOREIGN KEY (Sender_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            INDEX idx_conversation (Conversation_ID),
            INDEX idx_sent_at (Sent_At)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Chat_Attachment (
            Attachment_ID VARCHAR(191) PRIMARY KEY,
            Message_ID VARCHAR(191) NOT NULL,
            File_URL VARCHAR(500) NOT NULL,
            File_Name VARCHAR(255) NOT NULL,
            File_Type VARCHAR(50) NOT NULL,
            File_Size INT UNSIGNED,
            Uploaded_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Message_ID) REFERENCES Chat_Message(Message_ID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Notification (
            Notification_ID VARCHAR(191) PRIMARY KEY,
            User_ID VARCHAR(191) NOT NULL,
            Item_ID VARCHAR(191) NULL,
            Type ENUM('claim_received', 'claim_approved', 'claim_rejected', 'item_matched', 'item_expired', 'message_received', 'system_update') NOT NULL,
            Title VARCHAR(255) NOT NULL,
            Message TEXT NOT NULL,
            Is_Read BOOLEAN NOT NULL DEFAULT FALSE,
            Priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
            Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            Read_At TIMESTAMP NULL,
            FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE SET NULL,
            INDEX idx_user_read (User_ID, Is_Read),
            INDEX idx_created_at (Created_At)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS Feedback (
            Feedback_ID VARCHAR(191) PRIMARY KEY,
            Rater_ID VARCHAR(191) NOT NULL,
            Rated_ID VARCHAR(191) NOT NULL,
            Item_ID VARCHAR(191) NOT NULL,
            Rating TINYINT UNSIGNED NOT NULL CHECK (Rating >= 1 AND Rating <= 5),
            Comment TEXT,
            Status ENUM('active', 'hidden', 'flagged') NOT NULL DEFAULT 'active',
            Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (Rater_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            FOREIGN KEY (Rated_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
            FOREIGN KEY (Item_ID) REFERENCES Item(Item_ID) ON DELETE CASCADE,
            UNIQUE KEY unique_feedback (Rater_ID, Rated_ID, Item_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $i => $query) {
        try {
            $pdo->exec($query);
            echo "<p style='color: green;'>✓ Table " . ($i + 1) . " created successfully</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error creating table " . ($i + 1) . ": " . $e->getMessage() . "</p>";
        }
    }

    echo "<h3>Inserting Sample Data...</h3>";

    // ! Creating some predefined categories. 
    $categories = [
        ['cat_001', 'Electronics', 'Phones, Laptops, Tablets, Other Electronic Devices'],
        ['cat_002', 'Books & Documents', 'Textbooks, Notebooks, Papers, Documents'],
        ['cat_003', 'Clothing & Accessories', 'Jackets, Shoes, Bags, Personal Accessories'],
        ['cat_004', 'Keys & Cards', 'House keys, Car Keys, ID cards, Miscellenious cards'],
        ['cat_005', 'Jewelry & Watches', 'Rings, Necklaces, Bracelets, MISC'],
        ['cat_006', 'Sports & Gym Equipment', 'Sports gear, Equipment, accessories'],
        ['cat_007', 'Bags & Luggage', 'Backpacks, Suitcases, Purses, Travel bags'],
        ['cat_008', 'Personal Items', 'Wallets, Glasses, Other, Personal belongings'],
        ['cat_009', 'Study Materials', 'Pencil, Pen, Calculator, Ruller'],
        ['cat_010', 'Not belongs to category', 'Describe your item']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO Category (Category_ID, Category_Name, Description) VALUES (?, ?, ?)");
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    echo "<p style='color: green;'>✓ " . count($categories) . " categories inserted</p>";

    // ! Creating locations in mind of our university campus, rest is generic genarated.
    $locations = [
        ['loc_001', 'Main Library', 'Central campus library building', '123 University Ave'],
        ['loc_002', 'Student Center', 'Main student activities building', '456 Campus Dr'],
        ['loc_003', 'Science Building', 'Science and research facilities', '789 Research Blvd'],
        ['loc_004', 'Cafeteria', 'Main dining hall and food court', '321 Dining Way'],
        ['loc_005', 'Gymnasium', 'Sports and fitness center', '654 Athletic Dr'],
        ['loc_006', 'Parking Lot A', 'Main parking area near entrance', 'Campus Entrance'],
        ['loc_007', 'Lecture Hall B', 'Large lecture and seminar rooms', '987 Academic St'],
        ['loc_008', 'Computer Lab', 'IT and computer facilities', '147 Technology Ln'],
        ['loc_009', 'Campus Grounds', 'Outdoor areas and walkways', 'Various outdoor locations']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO Location (Location_ID, Location_Name, Description, Address) VALUES (?, ?, ?, ?)");
    foreach ($locations as $location) {
        $stmt->execute($location);
    }
    echo "<p style='color: green;'>✓ " . count($locations) . " locations inserted</p>";

    // Creating sample user
    $user_id = 'user_' . uniqid();
    $user_password = password_hash('user123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO User (User_ID, Name, Email, Phone, Password_Hash, Role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, 'John Doe', 'user@lostandfound.com', '+1234567891', $user_password, 'user']);
    echo "<p style='color: green;'>✓ Sample user created</p>";

    // Insert a sample Item and Attachment so the Attachment table is not empty
    try {
        $sample_item_id = 'item_' . uniqid();
        $reported_date = date('Y-m-d H:i:s');
        $expiration = date('Y-m-d', strtotime('+90 days')) . ' 23:59:59';
        $stmt = $pdo->prepare("INSERT IGNORE INTO Item (Item_ID, Creator_ID, Category_ID, Location_ID, Description, Priority, Status, Item_Type, Reported_Date, Expiration_Date) VALUES (?, ?, ?, ?, ?, 'medium', 'reported', 'found', ?, ?)");
        $stmt->execute([$sample_item_id, $user_id, 'cat_001', 'loc_001', 'Sample found item for attachment testing', $reported_date, $expiration]);

        // Use an existing sample image if available in uploads/chat
        $sample_file = 'uploads/chat/68bce2446019d_1757209156.png';
        $sample_file_name = basename($sample_file);
        $sample_file_type = pathinfo($sample_file_name, PATHINFO_EXTENSION);
        $sample_file_size = file_exists($sample_file) ? filesize($sample_file) : null;

        $attachment_id = 'att_' . uniqid();
        $stmt = $pdo->prepare("INSERT IGNORE INTO Attachment (Attachment_ID, Item_ID, Report_ID, File_URL, File_Name, File_Type, File_Size, Uploaded_At) VALUES (?, ?, NULL, ?, ?, ?, ?, ?)");
        $stmt->execute([$attachment_id, $sample_item_id, $sample_file, $sample_file_name, $sample_file_type, $sample_file_size, $reported_date]);

        echo "<p style='color: green;'>✓ Sample item and attachment inserted (Attachment ID: " . htmlspecialchars($attachment_id) . ")</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>! Could not insert sample attachment: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<div style='background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 style='color: #2d5a2d;'>✅ Database Setup Completed Successfully!</h3>";
    echo "<p><strong>Database:</strong> MariaDB " . $version_info['version'] . "</p>";
    echo "<p><strong>Tables Created:</strong> " . count($queries) . "</p>";
    echo "<p><strong>Sample Data:</strong> Categories, Locations, and Users inserted</p>";
    echo "<hr>";
    echo "<h4>Login Credentials:</h4>";
    echo "<p><strong>User:</strong><br>";
    echo "Email: <code>user@lostandfound.com</code><br>";
    echo "Password: <code>user123</code></p>";
    echo "<hr>";
    echo "<p><a href='auth.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; margin: 20px 0; color: #721c24;'>";
    echo "<h3>❌ MariaDB Database Error:</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    echo "<hr>";
    echo "<h4>Troubleshooting Steps:</h4>";
    echo "<ol>";
    echo "<li>Make sure MariaDB service is running in WampServer</li>";
    echo "<li>Verify the database 'lost_found_db' exists in phpMyAdmin</li>";
    echo "<li>Check the connection settings in <code>config/database.php</code></li>";
    echo "<li>Ensure MariaDB user 'root' has proper permissions</li>";
    echo "</ol>";
    echo "</div>";
}
