CREATE TABLE generation (
    id INT NOT NULL, resource_id INT NOT NULL,
    INDEX IDX_D3266C3B89329D25 (resource_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE generation ADD CONSTRAINT FK_D3266C3B89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE generation ADD CONSTRAINT FK_D3266C3BBF396750 FOREIGN KEY (id) REFERENCES resource (id) ON DELETE CASCADE;
