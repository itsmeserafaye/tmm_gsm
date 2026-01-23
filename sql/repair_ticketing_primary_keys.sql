-- Repairs missing primary keys/unique keys for ticketing + treasury payment tables.
-- Safe to run multiple times.

SET @db := DATABASE();

-- tickets
SET @has_tickets := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='tickets');
SET @has_pk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=@db AND table_name='tickets' AND constraint_type='PRIMARY KEY');
SET @has_tid := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='tickets' AND column_name='ticket_id');
SET @has_ticket_number := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='tickets' AND column_name='ticket_number');

SET @sql := IF(@has_tickets=0, 'SELECT \"tickets table missing\"', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_tickets=1 AND @has_tid=1, 'ALTER TABLE tickets MODIFY ticket_id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_tickets=1 AND @has_pk=0 AND @has_tid=1, 'ALTER TABLE tickets ADD PRIMARY KEY (ticket_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_uniq_tn := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=@db AND table_name='tickets' AND constraint_type='UNIQUE' AND constraint_name='uniq_ticket_number');
SET @sql := IF(@has_tickets=1 AND @has_ticket_number=1 AND @has_uniq_tn=0, 'ALTER TABLE tickets ADD UNIQUE KEY uniq_ticket_number (ticket_number)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payment_records
SET @has_pr := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='payment_records');
SET @has_pr_pk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=@db AND table_name='payment_records' AND constraint_type='PRIMARY KEY');
SET @has_pr_pid := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='payment_records' AND column_name='payment_id');
SET @has_pr_tid := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='payment_records' AND column_name='ticket_id');

SET @sql := IF(@has_pr=0, 'SELECT \"payment_records table missing\"', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_pr=1 AND @has_pr_pid=1, 'ALTER TABLE payment_records MODIFY payment_id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_pr=1 AND @has_pr_pk=0 AND @has_pr_pid=1, 'ALTER TABLE payment_records ADD PRIMARY KEY (payment_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_pr_idx_tid := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='payment_records' AND index_name='idx_ticket_id');
SET @sql := IF(@has_pr=1 AND @has_pr_tid=1 AND @has_pr_idx_tid=0, 'ALTER TABLE payment_records ADD INDEX idx_ticket_id (ticket_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ticket_payments
SET @has_tp := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='ticket_payments');
SET @has_tp_pk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=@db AND table_name='ticket_payments' AND constraint_type='PRIMARY KEY');
SET @has_tp_pid := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='ticket_payments' AND column_name='payment_id');
SET @has_tp_tid := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='ticket_payments' AND column_name='ticket_id');

SET @sql := IF(@has_tp=0, 'SELECT \"ticket_payments table missing\"', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_tp=1 AND @has_tp_pid=1, 'ALTER TABLE ticket_payments MODIFY payment_id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_tp=1 AND @has_tp_pk=0 AND @has_tp_pid=1, 'ALTER TABLE ticket_payments ADD PRIMARY KEY (payment_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_tp_idx_tid := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=@db AND table_name='ticket_payments' AND index_name='idx_ticket_id');
SET @sql := IF(@has_tp=1 AND @has_tp_tid=1 AND @has_tp_idx_tid=0, 'ALTER TABLE ticket_payments ADD INDEX idx_ticket_id (ticket_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- treasury_payment_requests
SET @has_tpr := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=@db AND table_name='treasury_payment_requests');
SET @has_tpr_pk := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=@db AND table_name='treasury_payment_requests' AND constraint_type='PRIMARY KEY');
SET @has_tpr_id := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='treasury_payment_requests' AND column_name='id');
SET @has_tpr_ref := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=@db AND table_name='treasury_payment_requests' AND column_name='ref');

SET @sql := IF(@has_tpr=0, 'SELECT \"treasury_payment_requests table missing\"', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_tpr=1 AND @has_tpr_id=1, 'ALTER TABLE treasury_payment_requests MODIFY id INT NOT NULL AUTO_INCREMENT', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_tpr=1 AND @has_tpr_pk=0 AND @has_tpr_id=1, 'ALTER TABLE treasury_payment_requests ADD PRIMARY KEY (id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_tpr_uniq_ref := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema=@db AND table_name='treasury_payment_requests' AND constraint_type='UNIQUE' AND constraint_name='uniq_ref');
SET @sql := IF(@has_tpr=1 AND @has_tpr_ref=1 AND @has_tpr_uniq_ref=0, 'ALTER TABLE treasury_payment_requests ADD UNIQUE KEY uniq_ref (ref)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

