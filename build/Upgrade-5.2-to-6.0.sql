-- run with: mysql -u <user> -p <db> < Upgrade-5.2-to-6.0.sql

-- Make sure the views exist and are updated
DROP VIEW IF EXISTS viewintegrationmulti;
DROP VIEW IF EXISTS viewintegrationtwo;
DROP VIEW IF EXISTS viewintegrationone;
SOURCE ViewIntegrationOne.sql;
SOURCE ViewIntegrationTwo.sql;
SOURCE ViewIntegrationMulti.sql;

-- Delete tables not to be used anymore
DROP TABLE IF EXISTS TakMinor;
DROP TABLE IF EXISTS TakUrl;

-- Update the TPDB versions
UPDATE MetaVersion SET version = '6.0', deployDate = '2018-09-15' WHERE id=1;

