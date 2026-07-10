-- Reset compta recap email template so the improved default (mailer.php) takes effect.
-- Sites that had customised this template must re-apply their changes after this migration.
DELETE FROM email_templates WHERE `key` = 'tpl_compta_recap';
