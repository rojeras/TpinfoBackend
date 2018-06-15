CREATE VIEW ViewIntegrationOne AS
  SELECT DISTINCT
    auth.plattformId           AS firstPlattformId,
    NULL                       AS middlePlattformId,
    auth.plattformId           AS lastPlattformId,
    auth.logicalAddressId      AS logicalAddressId,
    auth.serviceContractId     AS contractId,
    contract.serviceDomainId   AS domainId,
    GREATEST(auth.dateEffective, routing.dateEffective) as dateEffective,
    LEAST(auth.dateEnd, routing.dateEnd) AS dateEnd,
    auth.serviceComponentId    AS consumerId,
    routing.serviceComponentId AS producerId

  FROM
    TakCallAuthorization auth,
    TakRouting routing,
    TakServiceContract contract
  WHERE
    auth.plattformId = routing.plattformId
    AND auth.logicalAddressId = routing.logicalAddressId
    AND auth.serviceContractId = routing.serviceContractId
    AND auth.serviceContractId = contract.id
    -- AND auth.dateEffective < routing.dateEnd
    AND auth.dateEffective <= routing.dateEnd
    -- AND auth.dateEnd > routing.dateEffective
    AND auth.dateEnd >= routing.dateEffective
;
