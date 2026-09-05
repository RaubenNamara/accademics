-- Create events table for school events
CREATE TABLE IF NOT EXISTS school_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_name VARCHAR(100) NOT NULL,
  event_type VARCHAR(50) NOT NULL, -- breakfast, assembly, tea_break, lunch_break, evening_prep, sports, clubs, guidance, examination, meeting, custom
  event_color VARCHAR(20) DEFAULT '#FF6B6B', -- Hex color code
  description TEXT NULL,
  duration_minutes INT DEFAULT 40,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default school events
INSERT INTO school_events (event_name, event_type, event_color, description, duration_minutes) VALUES
('Breakfast', 'breakfast', '#4ECDC4', 'Morning breakfast break', 30),
('Morning Assembly', 'assembly', '#45B7D1', 'Daily morning assembly', 20),
('Tea Break', 'tea_break', '#96CEB4', 'Mid-morning tea break', 15),
('Lunch Break', 'lunch_break', '#FFEAA7', 'Lunch break', 60),
('Evening Prep', 'evening_prep', '#DDA0DD', 'Evening study/prep time', 60),
('Sports', 'sports', '#98D8C8', 'Sports and physical education', 60),
('Clubs & Societies', 'clubs', '#F7DC6F', 'Clubs and societies activities', 60),
('Guidance & Counselling', 'guidance', '#BB8FCE', 'Guidance and counselling sessions', 45),
('Examinations', 'examination', '#F1948A', 'Examination periods', 60),
('Meetings', 'meeting', '#85C1E9', 'Staff or student meetings', 30)
ON DUPLICATE KEY UPDATE event_name=VALUES(event_name), event_color=VALUES(event_color), description=VALUES(description), duration_minutes=VALUES(duration_minutes);
