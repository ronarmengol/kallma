-- Add walk-in client fields to bookings table
ALTER TABLE bookings 
ADD COLUMN walk_in_client_name VARCHAR(100) NULL AFTER status,
ADD COLUMN walk_in_client_mobile VARCHAR(20) NULL AFTER walk_in_client_name;
