CREATE TABLE IF NOT EXISTS discord_logins (
    discord_id BIGINT PRIMARY KEY,
    username VARCHAR(100),
    discriminator VARCHAR(10),
    avatar VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
