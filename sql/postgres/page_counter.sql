CREATE TABLE hit_counter (
  page_id SERIAL PRIMARY KEY,
  page_counter BIGINT NOT NULL DEFAULT 0
);

CREATE INDEX page_counter ON hit_counter (page_counter);
