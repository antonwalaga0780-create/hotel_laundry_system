USE hotel_laundry_db;

CREATE TABLE IF NOT EXISTS laundry_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    laundry_item_id INT NOT NULL,
    transaction_type ENUM('SENT','RETURNED','ADDED','ADJUSTED') NOT NULL,
    quantity INT NOT NULL,
    notes VARCHAR(255) NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (laundry_item_id) REFERENCES laundry_items(id) ON DELETE CASCADE
);
