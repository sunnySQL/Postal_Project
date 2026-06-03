-- Run this on an existing database if you did not recreate from dumpdemo.sql
-- Adds Support_Ticket.chat_log and Shop_Transaction 'Picked Up' status.
-- Run once. If chat_log already exists, comment out the first ALTER.

-- 1. Support ticket chat log (for ticket creation and comments)
ALTER TABLE Support_Ticket ADD COLUMN chat_log LONGTEXT DEFAULT NULL AFTER resolution_notes;

-- 2. Shop_Transaction: add 'Picked Up' so we don't use 'Failed' for pickups
ALTER TABLE Shop_Transaction MODIFY transaction_status enum('Pending','Completed','Picked Up','Failed') DEFAULT 'Pending';

-- 3. Support_Ticket: allow ticket_id to auto-increment so new tickets can be inserted
ALTER TABLE Support_Ticket MODIFY ticket_id int(11) NOT NULL AUTO_INCREMENT;

-- 4. Admin_Messages: allow message_id to auto-increment so new messages can be inserted
--    (If you get "Duplicate key" on ADD PRIMARY KEY, the table already has a primary key; run only the MODIFY line.)
ALTER TABLE Admin_Messages ADD PRIMARY KEY (message_id);
ALTER TABLE Admin_Messages MODIFY message_id int(11) NOT NULL AUTO_INCREMENT;

-- 5. Message_Replies: allow reply_id to auto-increment so new replies can be inserted
--    (If you get "Duplicate key" on ADD PRIMARY KEY, run only the MODIFY line.)
ALTER TABLE Message_Replies ADD PRIMARY KEY (reply_id);
ALTER TABLE Message_Replies MODIFY reply_id int(11) NOT NULL AUTO_INCREMENT;
