-- Segment auto-assignment rules (issue #154): "when a member is assigned to
-- segment X, also assign them to segment Y". Single-hop only, applied by
-- Contact::assignSegment() — deliberately NOT a generic rule engine (see
-- issue discussion: no second use case beyond this cascade yet).
CREATE TABLE IF NOT EXISTS `segment_cascade_rule` (
  `id`                int(11)   NOT NULL AUTO_INCREMENT,
  `source_segment_id` int(11)   NOT NULL,
  `target_segment_id` int(11)   NOT NULL,
  `created_at`         datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_segment_cascade_rule_pair` (`source_segment_id`, `target_segment_id`),
  KEY `idx_segment_cascade_rule_target` (`target_segment_id`),
  CONSTRAINT `fk_segment_cascade_rule_source` FOREIGN KEY (`source_segment_id`) REFERENCES `segment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_segment_cascade_rule_target` FOREIGN KEY (`target_segment_id`) REFERENCES `segment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
