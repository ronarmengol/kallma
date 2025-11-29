-- Add user_id column to masseuses table
-- This links masseuses to their user accounts for login

ALTER TABLE masseuses 
ADD COLUMN user_id INT NULL,
ADD CONSTRAINT fk_masseuse_user 
    FOREIGN KEY (user_id) 
    REFERENCES users(id) 
    ON DELETE CASCADE;

-- Add index for better query performance
CREATE INDEX idx_masseuse_user_id ON masseuses(user_id);

-- Optional: Add mobile column to masseuses table if it doesn't exist
-- (This is for backward compatibility with existing data)
ALTER TABLE masseuses 
ADD COLUMN mobile VARCHAR(20) NULL;
