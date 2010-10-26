DELIMITER $$

DROP VIEW IF EXISTS `kaltura_op_mon`.`ds_events_partitions`$$

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `kaltura_op_mon`.`ds_events_partitions` AS (SELECT `partitions`.`TABLE_SCHEMA` AS `TABLE_SCHEMA`,`partitions`.`TABLE_NAME` AS `TABLE_NAME`,`partitions`.`PARTITION_NAME` AS `PARTITION_NAME`,
SUBSTR(`partitions`.`PARTITION_NAME`,3) AS `partition_number`,table_rows,`partitions`.`CREATE_TIME` AS `CREATE_TIME` 
FROM `information_schema`.`partitions` WHERE ((`partitions`.`TABLE_NAME` = 'ds_events')
 AND (`partitions`.`PARTITION_NAME` <> 'p_0')))$$

DELIMITER ;