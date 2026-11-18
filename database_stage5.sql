USE hotel_laundry_db;

-- Stage 5: add damaged-item tracking.
-- Run this ONCE. If damaged_quantity already exists, do not run this statement again.
ALTER TABLE laundry_items
ADD COLUMN damaged_quantity INT NOT NULL DEFAULT 0 AFTER returned_quantity;

-- Make sure the transaction table exists.
CREATE TABLE IF NOT EXISTS laundry_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    laundry_item_id INT NOT NULL,
    transaction_type ENUM('SENT','RETURNED','DAMAGED','ADDED','ADJUSTED') NOT NULL,
    quantity INT NOT NULL,
    notes VARCHAR(255) NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (laundry_item_id) REFERENCES laundry_items(id) ON DELETE CASCADE
);
