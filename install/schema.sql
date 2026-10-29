-- ProjectHub schema (MariaDB / MySQL, utf8mb4)

CREATE TABLE IF NOT EXISTS departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  code VARCHAR(30) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS majors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_major (department_id, name),
  CONSTRAINT fk_major_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classrooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  level VARCHAR(20) NOT NULL DEFAULT '',
  department VARCHAR(150) NOT NULL DEFAULT '',
  major VARCHAR(150) NOT NULL DEFAULT '',
  academic_year SMALLINT NULL,
  advisor_name VARCHAR(150) NOT NULL DEFAULT '',
  advisor_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  prefix VARCHAR(30) NOT NULL DEFAULT '',
  full_name VARCHAR(150) NOT NULL,
  role ENUM('admin','research','academic','strategy','teacher','student') NOT NULL,
  position VARCHAR(150) NOT NULL DEFAULT '',
  department VARCHAR(150) NOT NULL DEFAULT '',
  major VARCHAR(150) NOT NULL DEFAULT '',
  education VARCHAR(100) NOT NULL DEFAULT '',
  duty VARCHAR(255) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(150) NOT NULL DEFAULT '',
  student_code VARCHAR(20) NULL UNIQUE,
  classroom_id INT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_role (role),
  CONSTRAINT fk_user_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS semesters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  term TINYINT NOT NULL,
  academic_year SMALLINT NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_term (term, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  semester_id INT NOT NULL,
  seq TINYINT NOT NULL DEFAULT 1,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  open_date DATE NULL,
  due_date DATE NULL,
  max_score DECIMAL(5,2) NOT NULL DEFAULT 10,
  CONSTRAINT fk_period_sem FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  semester_id INT NOT NULL,
  classroom_id INT NOT NULL,
  teacher_id INT NOT NULL,
  subject_code VARCHAR(30) NOT NULL DEFAULT '',
  subject_name VARCHAR(200) NOT NULL DEFAULT 'โครงงาน',
  UNIQUE KEY uq_section (semester_id, classroom_id, subject_code),
  CONSTRAINT fk_sec_sem FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
  CONSTRAINT fk_sec_class FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_sec_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  title_en VARCHAR(255) NOT NULL DEFAULT '',
  category VARCHAR(80) NOT NULL DEFAULT '',
  objective TEXT NULL,
  scope TEXT NULL,
  expected TEXT NULL,
  advisor_id INT NULL,
  status ENUM('proposed','revise','approved','completed','cancelled') NOT NULL DEFAULT 'proposed',
  status_note TEXT NULL,
  progress TINYINT NOT NULL DEFAULT 0,
  final_score DECIMAL(5,2) NULL,
  final_grade VARCHAR(5) NULL,
  final_comment TEXT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  CONSTRAINT fk_proj_sec FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
  CONSTRAINT fk_proj_adv FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_members (
  project_id INT NOT NULL,
  student_id INT NOT NULL,
  is_leader TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (project_id, student_id),
  CONSTRAINT fk_mem_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_mem_user FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progress_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  period_id INT NOT NULL,
  submitted_by INT NULL,
  summary TEXT NOT NULL,
  progress TINYINT NOT NULL DEFAULT 0,
  problems TEXT NULL,
  next_plan TEXT NULL,
  link_url VARCHAR(500) NOT NULL DEFAULT '',
  file_name VARCHAR(255) NOT NULL DEFAULT '',
  file_path VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('submitted','approved','revise') NOT NULL DEFAULT 'submitted',
  score DECIMAL(5,2) NULL,
  teacher_comment TEXT NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_report (project_id, period_id),
  CONSTRAINT fk_rep_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_rep_period FOREIGN KEY (period_id) REFERENCES report_periods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS oversight_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NOT NULL,
  reviewer_id INT NOT NULL,
  unit ENUM('admin','research','academic','strategy') NOT NULL,
  result ENUM('pass','improve','fail') NOT NULL DEFAULT 'pass',
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ov_proj FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_ov_user FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  audience ENUM('all','teacher','student') NOT NULL DEFAULT 'all',
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(50) PRIMARY KEY,
  value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL,
  detail TEXT NULL,
  page_url VARCHAR(500) NOT NULL DEFAULT '',
  file_name VARCHAR(255) NOT NULL DEFAULT '',
  file_path VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  admin_reply TEXT NULL,
  replied_by INT NULL,
  replied_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_issue_status (status),
  CONSTRAINT fk_issue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT '',
  description TEXT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size INT NOT NULL DEFAULT 0,
  audience ENUM('all','teacher','student') NOT NULL DEFAULT 'all',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  download_count INT NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS semester_summaries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  semester_id INT NOT NULL,
  department VARCHAR(150) NOT NULL DEFAULT '',
  summary TEXT NULL,
  problems TEXT NULL,
  suggestions TEXT NULL,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  written_by INT NULL,
  submitted_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_summary (semester_id, department),
  CONSTRAINT fk_summary_sem FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_declarations (
  teacher_id INT NOT NULL,
  semester_id INT NOT NULL,
  teaches_project TINYINT(1) NOT NULL DEFAULT 0,
  declared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (teacher_id, semester_id),
  CONSTRAINT fk_decl_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_decl_sem FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
