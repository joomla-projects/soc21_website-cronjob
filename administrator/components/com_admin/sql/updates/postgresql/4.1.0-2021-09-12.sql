INSERT INTO "#__mail_templates" ("template_id", "language", "subject", "body", "htmlbody", "attachments", "params") VALUES
('plg_system_tasknotification.failure_mail', '', 'PLG_SYSTEM_TASK_NOTIFICATION_FAILURE_MAIL_SUBJECT', 'PLG_SYSTEM_TASK_NOTIFICATION_FAILURE_MAIL_BODY', '', '', '{"tags": ["task_id", "task_title", "exit_code", "exec_data_time"]}'),
('plg_system_tasknotification.fatal_recovery_mail', '', 'PLG_SYSTEM_TASK_NOTIFICATION_FATAL_MAIL_SUBJECT', 'PLG_SYSTEM_TASK_NOTIFICATION_FATAL_MAIL_BODY', '', '', '{"tags": ["task_id", "task_title"]}'),
('plg_system_tasknotification.orphan_mail', '', 'PLG_SYSTEM_TASK_NOTIFICATION_ORPHAN_MAIL_SUBJECT', 'PLG_SYSTEM_TASK_NOTIFICATION_ORPHAN_MAIL_BODY', '', '', '{"tags": ["task_id", "task_title", ""]}'),
('plg_system_tasknotification.success_mail', '', 'PLG_SYSTEM_TASK_NOTIFICATION_SUCCESS_MAIL_SUBJECT', 'PLG_SYSTEM_TASK_NOTIFICATION_SUCCESS_MAIL_BODY', '', '', '{"tags":["task_id", "task_title", "exec_data_time"]}');
