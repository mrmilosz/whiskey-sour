CREATE TABLE pack (
   id          INT       AUTOINCREMENT
  ,name        TEXT
  ,UNIQUE(name)
)

CREATE TABLE map (
   id          INT       AUTOINCREMENT
  ,name        TEXT
  ,pack_id     INT
  ,author      TEXT
  ,size        INT
  ,description TEXT
  ,release     TIMESTAMP
  ,mode        TEXT
  ,UNIQUE(name)
  ,FOREIGN KEY(pack_id) REFERENCES 
  ,FOREIGN KEY(mode)    REFERENCES mode(name)
)

CREATE TABLE mode (
   name        TEXT
  ,PRIMARY KEY(name)
);

 INSERT INTO mode
      SELECT 'vq3' AS 'name'
UNION SELECT 'cpm'
             ;
