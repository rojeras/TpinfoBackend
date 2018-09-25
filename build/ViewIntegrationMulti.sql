CREATE VIEW ViewIntegrationMulti AS
  SELECT
    firstPlattformId,
    middlePlattformId,
    lastPlattformId,
    logicalAddressId,
    contractId,
    domainId,
    dateEffective,
    dateEnd,
    consumerId,
    producerId
  FROM ViewIntegrationOne
  UNION
  SELECT
    firstPlattformId,
    middlePlattformId,
    lastPlattformId,
    logicalAddressId,
    contractId,
    domainId,
    dateEffective,
    dateEnd,
    consumerId,
    producerId
  FROM ViewIntegrationTwo;