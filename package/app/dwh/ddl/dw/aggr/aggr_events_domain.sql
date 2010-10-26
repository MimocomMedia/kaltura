CREATE TABLE kalturadw.`dwh_aggr_events_domain` (
  `partner_id` INT DEFAULT NULL,
  `date_id` INT DEFAULT NULL,
  `domain_id` INT DEFAULT NULL,
  `sum_time_viewed` DECIMAL(20,3) DEFAULT NULL,
  `count_time_viewed` INT DEFAULT NULL,
  `count_plays` INT DEFAULT NULL,
  `count_loads` INT DEFAULT NULL,
  `count_plays_25` INT DEFAULT NULL,
  `count_plays_50` INT DEFAULT NULL,
  `count_plays_75` INT DEFAULT NULL,
  `count_plays_100` INT DEFAULT NULL,
  `count_edit` INT DEFAULT NULL,
  `count_viral` INT DEFAULT NULL,
  `count_download` INT DEFAULT NULL,
  `count_report` INT DEFAULT NULL,
  `count_buf_start` INT DEFAULT NULL,
  `count_buf_end` INT DEFAULT NULL,
  `count_open_full_screen` int DEFAULT NULL,
  `count_close_full_screen` int DEFAULT NULL,
  `count_replay` int DEFAULT NULL,
  `count_seek` int DEFAULT NULL,
  `count_open_upload` int DEFAULT NULL,
  `count_save_publish` int DEFAULT NULL,
  `count_close_editor` int DEFAULT NULL,    
  `count_pre_bumper_played` INT DEFAULT NULL,
  `count_post_bumper_played` INT DEFAULT NULL,
  `count_bumper_clicked` INT DEFAULT NULL,
  `count_preroll_started` INT DEFAULT NULL,
  `count_midroll_started` INT DEFAULT NULL,
  `count_postroll_started` INT DEFAULT NULL,
  `count_overlay_started` INT DEFAULT NULL,
  `count_preroll_clicked` INT DEFAULT NULL,
  `count_midroll_clicked` INT DEFAULT NULL,
  `count_postroll_clicked` INT DEFAULT NULL,
  `count_overlay_clicked` INT DEFAULT NULL,
  `count_preroll_25` INT DEFAULT NULL,
  `count_preroll_50` INT DEFAULT NULL,
  `count_preroll_75` INT DEFAULT NULL,
  `count_midroll_25` INT DEFAULT NULL,
  `count_midroll_50` INT DEFAULT NULL,
  `count_midroll_75` INT DEFAULT NULL,
  `count_postroll_25` INT DEFAULT NULL,
  `count_postroll_50` INT DEFAULT NULL,
  `count_postroll_75` INT DEFAULT NULL,
  PRIMARY KEY `partner_id` (`partner_id`,`date_id`,`domain_id`),
  KEY `domain_id` (`domain_id`,`partner_id`,`date_id`),
  KEY `date_id` (`date_id`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8
	PARTITION BY RANGE (date_id)
	(PARTITION p_201001 VALUES LESS THAN (20100201));