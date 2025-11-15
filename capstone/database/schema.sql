-- PostgreSQL schema for CareFlow auth
CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('doctor','nurse','supervisor','pharmacist','lab_staff','admin')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS profile (
  id BIGSERIAL PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  phone VARCHAR(20),
  address TEXT,
  birth_date DATE,
  gender VARCHAR(10),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS prescription (
  id BIGSERIAL PRIMARY KEY,
  doctor_name VARCHAR(255) NOT NULL,
  patient_name VARCHAR(255) NOT NULL,
  medicine VARCHAR(255) NOT NULL,
  quantity VARCHAR(100) NOT NULL,
  dosage_strength VARCHAR(100) NOT NULL,
  description TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS appointments (
  id BIGSERIAL PRIMARY KEY,
  patient VARCHAR(255) NOT NULL,
  "date" DATE NOT NULL,
  "time" TIME NOT NULL,
  notes TEXT,
  done BOOLEAN NOT NULL DEFAULT false,
  created_by_name VARCHAR(255),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Helpful indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);
CREATE INDEX IF NOT EXISTS idx_profile_user_id ON profile (user_id);
CREATE INDEX IF NOT EXISTS idx_prescription_status ON prescription (status);
CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments ("date");
