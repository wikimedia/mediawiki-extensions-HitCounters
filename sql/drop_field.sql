INSERT INTO /*_*/hit_counter (page_id, page_counter) SELECT /*_*/page.page_id, /*_*/page.page_counter FROM /*_*/page;
ALTER TABLE /*_*/page DROP page_counter;
