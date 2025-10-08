DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM fines LIMIT 1) THEN
        INSERT INTO fines (offender_name, offence_type, fine_amount, date_issued, status) VALUES
            -- A fine older than 30 days to demonstrate automatic overdue rule
            ('Alice Johnson', 'Parking', 50.00, (CURRENT_DATE - INTERVAL '40 days')::date, 'unpaid'),
            -- A recent fine to demonstrate early payment discount window
            ('Bob Smith', 'Speeding', 120.00, (CURRENT_DATE - INTERVAL '5 days')::date, 'unpaid'),
            -- A paid fine example
            ('Carlos Gomez', 'Red Light', 200.00, (CURRENT_DATE - INTERVAL '20 days')::date, 'paid'),
            -- Three existing unpaid fines for the same offender to trigger surcharge when creating a new one
            ('Repeat Offender', 'Speeding', 90.00, (CURRENT_DATE - INTERVAL '10 days')::date, 'unpaid'),
            ('Repeat Offender', 'Parking', 60.00, (CURRENT_DATE - INTERVAL '15 days')::date, 'unpaid'),
            ('Repeat Offender', 'Seatbelt', 40.00, (CURRENT_DATE - INTERVAL '25 days')::date, 'unpaid');
    END IF;
END$$;