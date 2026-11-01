UPDATE appointment_board_entries SET appointment_type = 'treatment' WHERE appointment_type = 'surgery';

ALTER TABLE appointment_board_entries
    MODIFY appointment_type ENUM('room', 'treatment', 'anesthesia') NOT NULL DEFAULT 'room';
