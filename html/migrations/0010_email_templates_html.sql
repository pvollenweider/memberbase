-- Add body_html column to email_templates for multipart HTML emails.
ALTER TABLE `email_templates`
  ADD COLUMN IF NOT EXISTS `body_html` LONGTEXT NOT NULL DEFAULT '' AFTER `body_text`;
