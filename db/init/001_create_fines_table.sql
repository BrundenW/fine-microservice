DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'fine_status') THEN
        CREATE TYPE fine_status AS ENUM ('unpaid', 'paid', 'overdue');
    END IF;
END$$;

CREATE TABLE IF NOT EXISTS fines (
    fine_id       BIGSERIAL PRIMARY KEY,
    offender_name TEXT        NOT NULL,
    offence_type  TEXT        NOT NULL,
    fine_amount   NUMERIC(10,2) NOT NULL CHECK (fine_amount >= 0),
    date_issued   DATE        NOT NULL,
    status        fine_status NOT NULL DEFAULT 'unpaid'
);
