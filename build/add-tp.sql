-- Script to add new HSA-id for RTP to TPDB
-- LEO 2021-09-08

USE TPDB;

INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId)
VALUES ('Internet', 'SE2321000016-AMCV', 4);

INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId)
VALUES ('Internet', 'SE2321000016-FH3P', 3);

INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId)
VALUES ('SLLNET', 'SE2321000016-AMCS', 4);

INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId)
VALUES ('SLLNET', 'SE2321000016-FH3R', 3);

INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId)
VALUES ('SJUNET', 'SE2321000016-AMCT', 4);

INSERT INTO MetaPlattformHsaId (network, hsaId, takPlattformId)
VALUES ('SJUNET', 'SE2321000016-FH3Q', 3);

SELECT
    CONCAT(tp.name, '-', tp.environment),
    tp.id,
    mphi.network,
    mphi.hsaId
FROM
     MetaPlattformHsaId mphi,
     TakPlattform tp
WHERE
    mphi.takPlattformId = tp.id
ORDER BY
    mphi.id
;
