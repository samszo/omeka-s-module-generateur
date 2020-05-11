ALTER TABLE generation DROP FOREIGN KEY FK_D3266C3BBF396750;
ALTER TABLE generation DROP FOREIGN KEY FK_D3266C3B89329D25;
DELETE value
    FROM value LEFT JOIN resource ON resource.id = value.resource_id
    WHERE resource_type = "Generateur\\Entity\\Generation";
DELETE FROM resource WHERE resource_type = "Generateur\\Entity\\Generation";
DROP TABLE generation;
