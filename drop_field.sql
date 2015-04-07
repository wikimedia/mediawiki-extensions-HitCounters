INSERT INTO /*$wgDBprefix*/hit_counter (page_id, page_counter)
       SELECT /*$wgDBprefix*/page.page_id, /*$wgDBprefix*/page.page_counter FROM /*$wgDBprefix*/page;
ALTER TABLE /*$wgDBprefix*/page DROP page_counter;
