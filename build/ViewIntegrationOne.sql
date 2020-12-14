DROP VIEW IF EXISTS ViewIntegrationOne;
CREATE VIEW ViewIntegrationOne AS
SELECT DISTINCT auth.plattformId                                    AS firstPlattformId,
                NULL                                                AS middlePlattformId,
                auth.plattformId                                    AS lastPlattformId,
                routing.logicalAddressId                            AS logicalAddressId,

                auth.serviceContractId                              AS contractId,
                contract.serviceDomainId                            AS domainId,
                GREATEST(auth.dateEffective, routing.dateEffective) as dateEffective,
                LEAST(auth.dateEnd, routing.dateEnd)                AS dateEnd,
                auth.serviceComponentId                             AS consumerId,
                routing.serviceComponentId                          AS producerId

FROM TakCallAuthorization auth,
     TakRouting routing,
     TakServiceContract contract
WHERE auth.plattformId = routing.plattformId
  AND auth.logicalAddressId = routing.logicalAddressId -- The default case, where both LA are the same
  AND auth.serviceContractId = routing.serviceContractId
  AND auth.serviceContractId = contract.id
  AND auth.dateEffective <= routing.dateEnd
  AND auth.dateEnd >= routing.dateEffective

UNION

-- This SELECT below manage authorization via 'SE' and '*'
SELECT DISTINCT auth.plattformId                                    AS firstPlattformId,
                NULL                                                AS middlePlattformId,
                auth.plattformId                                    AS lastPlattformId,
                routing.logicalAddressId                            AS logicalAddressId,

                auth.serviceContractId                              AS contractId,
                contract.serviceDomainId                            AS domainId,
                GREATEST(auth.dateEffective, routing.dateEffective) as dateEffective,
                LEAST(auth.dateEnd, routing.dateEnd)                AS dateEnd,
                auth.serviceComponentId                             AS consumerId,
                routing.serviceComponentId                          AS producerId

FROM TakCallAuthorization auth,
     TakRouting routing,
     TakServiceContract contract
WHERE auth.plattformId = routing.plattformId
  AND auth.logicalAddressId <> routing.logicalAddressId -- The case where both addresses are 'SE' is managed in the first SELECT
  AND auth.logicalAddressId IN (SELECT DISTINCT id FROM TakLogicalAddress WHERE value = 'SE' OR value = '*')
  AND auth.serviceContractId = routing.serviceContractId
  AND auth.serviceContractId = contract.id
  AND auth.dateEffective <= routing.dateEnd
  AND auth.dateEnd >= routing.dateEffective
;
