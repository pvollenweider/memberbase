-- Rename contact tables (users → contact, user_segment → contact_segment, user_properties → contact_properties)
RENAME TABLE `users` TO `contact`;
RENAME TABLE `user_segment` TO `contact_segment`;
RENAME TABLE `user_properties` TO `contact_properties`;
