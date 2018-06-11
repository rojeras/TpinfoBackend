-- Create a MetaVersion table and set TPDB version to 5.2
-- run with: mysql -u skoview -p skoview2 < Upgrade-5.1-to-5.2.sql

-- Make sure the views exist and are updated
DROP VIEW IF EXISTS viewintegrationmulti;
DROP VIEW IF EXISTS viewintegrationtwo;
DROP VIEW IF EXISTS viewintegrationone;
SOURCE ViewIntegrationOne.sql;
SOURCE ViewIntegrationTwo.sql;
SOURCE ViewIntegrationMulti.sql;

-- Change the precedence span and insert new TAKs in TakPlattform
UPDATE TakPlattform SET precedence = precedence * 10 WHERE precedence < 10;
INSERT INTO TakPlattform (name, environment, lastSnapshot, precedence) VALUES ('LD', 'PROD', '2000-01-01', 25) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);
INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId) SELECT 'Sjunet', 'SE2321000180-1000', tp.id FROM TakPlattform tp WHERE name = 'LTD' ON DUPLICATE KEY UPDATE network='Sjunet';

-- Create a MetaVersion table
DROP TABLE IF EXISTS MetaVersion;
CREATE TABLE MetaVersion
(
  id mediumint PRIMARY KEY AUTO_INCREMENT,
  version varchar(64) NOT NULL,
  deployDate date NOT NULL
);
CREATE UNIQUE INDEX MetaVersion_version_uindex ON MetaVersion (version);
CREATE UNIQUE INDEX MetaVersion_deployDate_uindex ON MetaVersion (deployDate);
INSERT INTO MetaVersion (version, deployDate) VALUES ('5.2', CURDATE());

-- Remove the duplicates we got during the read problem from the TAK-api
DELETE auth
FROM TakCallAuthorization AS auth
WHERE id IN
      (
        SELECT ID FROM
          (
            SELECT DISTINCT
              (c2.id)
            FROM
              TakCallAuthorization c1,
              TakCallAuthorization c2
            WHERE
              c1.serviceContractId = c2.serviceContractId
              AND c1.logicalAddressId = c2.logicalAddressId
              AND c1.serviceComponentId = c2.serviceComponentId
              AND c1.plattformId = c2.plattformId
              AND c1.dateEffective < c2.dateEffective
              AND c1.dateEnd >= c2.dateEnd
          ) x
      )
;

-- Empty the statistic and the integration tables. This is done due to DB errors following problems with TAK-api
-- It implies the all statistics data must be reloaded
TRUNCATE StatData;
ALTER TABLE StatData AUTO_INCREMENT = 0;

DELETE FROM TakIntegration;
ALTER TABLE TakIntegration AUTO_INCREMENT = 0;
