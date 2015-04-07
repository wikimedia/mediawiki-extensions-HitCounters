CREATE TABLE /*_*/hit_counter (
  page_id INT(8) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  page_counter BIGINT(20) UNSIGNED NOT NULL DEFAULT '0'
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/page_counter ON /*_*/hit_counter (page_counter);