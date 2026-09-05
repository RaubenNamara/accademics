-- Add detailed score fields to duty_performance table
ALTER TABLE duty_performance
ADD COLUMN punctuality DECIMAL(5,2) DEFAULT 20 AFTER week_number,
ADD COLUMN supervision DECIMAL(5,2) DEFAULT 20 AFTER punctuality,
ADD COLUMN cleanliness DECIMAL(5,2) DEFAULT 20 AFTER supervision,
ADD COLUMN time_keeping DECIMAL(5,2) DEFAULT 20 AFTER cleanliness,
ADD COLUMN participation DECIMAL(5,2) DEFAULT 20 AFTER time_keeping,
ADD COLUMN comment TEXT DEFAULT '' AFTER percentage;
