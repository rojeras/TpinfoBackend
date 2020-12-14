DROP VIEW IF EXISTS ViewIntegrationTwo;
CREATE VIEW ViewIntegrationTwo AS
SELECT DISTINCT leftMeta.takPlattformId                                                 AS firstPlattformId,
                -- leftIntegration.lastPlattformId AS firstPlattformId,
                NULL                                                                    AS middlePlattformId,
                rightMeta.takPlattformId                                                AS lastPlattformId,
                -- rightIntegration.lastPlattformId AS lastPlattformId,
                leftIntegration.logicalAddressId                                        AS logicalAddressId,
                leftIntegration.contractId                                              AS contractId,
                contract.serviceDomainId                                                AS domainId,
                GREATEST(leftIntegration.dateEffective, rightIntegration.dateEffective) as dateEffective,
                LEAST(leftIntegration.dateEnd, rightIntegration.dateEnd)                AS dateEnd,
                leftIntegration.consumerId                                              AS consumerId,
                rightIntegration.producerId                                             AS producerId

FROM ViewIntegrationOne leftIntegration,
     ViewIntegrationOne rightIntegration,
     TakServiceComponent leftTpComponent,
     TakServiceComponent rightTpComponent,
     MetaPlattformHsaId leftMeta,
     MetaPlattformHsaId rightMeta,
     TakServiceContract contract
WHERE leftIntegration.lastPlattformId <> rightIntegration.lastPlattformId

  AND leftIntegration.logicalAddressId = rightIntegration.logicalAddressId
  AND leftIntegration.contractId = rightIntegration.contractId

  AND leftIntegration.producerId = rightTpComponent.id
  AND rightTpComponent.value = rightMeta.hsaId
  AND rightMeta.takPlattformId = rightIntegration.lastPlattformId

  AND rightIntegration.consumerId = leftTpComponent.id
  AND leftTpComponent.value = leftMeta.hsaId
  ANd leftMeta.takPlattformId = leftIntegration.lastPlattformId

  AND leftIntegration.contractId = contract.id

  -- AND leftIntegration.dateEffective < rightIntegration.dateEnd
  AND leftIntegration.dateEffective <= rightIntegration.dateEnd
  -- AND leftIntegration.dateEnd > rightIntegration.dateEffective
  AND leftIntegration.dateEnd >= rightIntegration.dateEffective
;

