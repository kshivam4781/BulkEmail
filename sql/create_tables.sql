-- Create scheduled_emails table
CREATE TABLE IF NOT EXISTS scheduled_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    to_emails TEXT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    scheduled_time DATETIME NOT NULL,
    attachment_path VARCHAR(255),
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(userId)
);

-- Create email_logs table
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    to_emails TEXT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent', 'scheduled', 'failed') NOT NULL,
    scheduled_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(userId)
);

-- Create contactlists table
CREATE TABLE IF NOT EXISTS contactlists (
    ListID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Name VARCHAR(100) NOT NULL,
    CreatedAt DATETIME NOT NULL,
    FOREIGN KEY (UserID) REFERENCES users(userId) ON DELETE CASCADE
);

-- Create contacts table
CREATE TABLE IF NOT EXISTS contacts (
    ContactID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    ListID INT NOT NULL,
    FullName VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    Phone VARCHAR(20),
    Company VARCHAR(100),
    CustomFields JSON,
    CreatedAt DATETIME NOT NULL,
    FOREIGN KEY (UserID) REFERENCES users(userId) ON DELETE CASCADE,
    FOREIGN KEY (ListID) REFERENCES contactlists(ListID) ON DELETE CASCADE
); 