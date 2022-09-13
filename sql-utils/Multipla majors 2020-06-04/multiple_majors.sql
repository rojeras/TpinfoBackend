SELECT
       consumer.value,
       consumer.description,
       contract1.contractName,
       contract1.major,
       contract2.major,
       la.value,
       producer.value,
       producer.description
FROM
     TakIntegration integration1,
     TakIntegration integration2,
     TakServiceComponent consumer,
     TakCallAuthorization auth1,
     TakCallAuthorization auth2,
     TakLogicalAddress la,
     TakServiceContract contract1,
     TakServiceContract contract2,
     TakServiceComponent producer
WHERE
    contract1.serviceDomainId = contract2.serviceDomainId
    AND contract1.contractName = contract2.contractName
    AND contract1.major != contract2.major
    AND integration1.id != integration2.id
    AND integration1.contractId = contract1.id
    AND integration2.contractId = contract2.id
    AND integration1.consumerId = consumer.id
    AND integration2.consumerId = consumer.id
    AND integration1.producerid = producer.id
    AND integration2.producerid = producer.id
    AND integration1.logicalAddressId = la.id
    AND integration2.logicalAddressId = la.id
    AND integration1.firstPlattformId = 4
    AND integration1.lastPlattformId = 4
    AND integration2.firstPlattformId = 4
    AND integration2.lastPlattformId = 4
    AND integration1.dateEnd >= '2020-05-20'
    AND integration2.dateEnd >= '2020-05-20'
;

