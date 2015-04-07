CREATE TABLE /*$wgDBprefix*/hit_counter (
  page_id INT(8) UNSIGNED NOT NULL,
  page_counter BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (page_id),
  INDEX (page_counter) 
);
