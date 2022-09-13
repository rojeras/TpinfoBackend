-- Leta fram integrationer i en första TP som leder till en andra där integration inte är TAK-ad

SELECT DISTINCT
                consLeft.value AS consumerHSA,
                CONCAT(tpLeft.name, ' ', tpLeft.environment) AS FirstTP,
                contract.namespace AS namespace,
                la.value AS logicalAddress,
                CONCAT(tpRight.name, ' ', tpRight.environment) AS SecondTP
FROM
     TakPlattform tpLeft,
     TakPlattform tpRight,
     TakIntegration integLeft,
     TakServiceContract contract,
     TakLogicalAddress la,
     TakServiceComponent consLeft,
     TakServiceComponent prodLeft,
     MetaPlattformHsaId metaLeft
WHERE
      -- Join with base tables to get name and values used in output
      integLeft.firstPlattformId = tpLeft.id
  AND integLeft.contractId = contract.id
  AND integLeft.logicalAddressId = la.id
  AND integLeft.consumerId = consLeft.id

  -- We should look for integLeft where first and last plattform is the same - that is ensure we look in only one TAK
  AND integLeft.firstPlattformId = integLeft.lastPlattformId

  -- Ensure we only look att "today" (latest date in tpdb)
  AND '2019-01-17' BETWEEN integLeft.dateEffective AND integLeft.dateEnd

  -- Join the producer in integLeft with a the right plattform
  AND integLeft.producerid = prodLeft.id
  AND prodLeft.value = metaLeft.hsaId
  AND metaLeft.takPlattformId = tpRight.id

  -- tpLeft and tpRight should be different
  AND NOT (tpLeft.id = tpRight.id)

  -- Exclude the Dalarna and NMT plattforms
  AND tpLeft.id < 7
  AND tpRight.id < 7

  -- Use logical address in check
  AND la.id NOT IN (
  SELECT DISTINCT integRight.logicalAddressId
  FROM TakIntegration integRight,
       TakServiceComponent consRight,
       MetaPlattformHsaId metaRight

  -- The TP in integRight should be same for first and last, and different from integLeft, and the same as the producer above
  WHERE integRight.contractId = integLeft.contractId
    AND integRight.firstPlattformId = integRight.lastPlattformId
    AND integRight.firstPlattformId = tpRight.id

    -- Ensure we only look att "today" (latest date in tpdb)
    AND '2019-01-17' BETWEEN integRight.dateEffective AND integRight.dateEnd

    -- Join the consumer in integRight with a the left plattform
    AND integRight.consumerId = consRight.id
    AND consRight.value = metaRight.hsaId
    AND metaRight.takPlattformId = tpLeft.id

)
ORDER BY FirstTP, SecondTP, logicalAddress, namespace
;
