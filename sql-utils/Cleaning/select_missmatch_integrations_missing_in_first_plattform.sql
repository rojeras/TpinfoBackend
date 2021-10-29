-- Leta fram integration i en andra TP där motsvarande inte finns i en första i kedjan

SELECT DISTINCT
                -- consLeft.value AS consumerHSA,
                CONCAT(tpLeft.name, ' ', tpLeft.environment) AS FirstTP,
                contract.namespace AS namespace,
                la.value AS logicalAddress,
                CONCAT(tpRight.name, ' ', tpRight.environment) AS SecondTP,
                prodRight.value AS producerHSA
FROM
     TakPlattform tpLeft,
     TakPlattform tpRight,
     -- TakIntegration integLeft,
     TakIntegration integRight,
     TakServiceContract contract,
     TakLogicalAddress la,
     -- TakServiceComponent consLeft,
     -- TakServiceComponent prodLeft,
     TakServiceComponent consRight,
     TakServiceComponent prodRight,
     MetaPlattformHsaId metaRight
WHERE

  -- We should look for integLeft where first and last plattform is the same - that is ensure we look in only one TAK
      integRight.firstPlattformId = integRight.lastPlattformId

  -- Join with base tables to get name and values used in output
  AND integRight.firstPlattformId = tpRight.id
  AND integRight.contractId = contract.id
  AND integRight.logicalAddressId = la.id
  AND integRight.producerid = prodRight.id


  -- Ensure we only look att "today" (latest date in tpdb)
  AND '2019-01-17' BETWEEN integRight.dateEffective AND integRight.dateEnd

  -- Join the consumer in integRight with a the left plattform
  AND integRight.consumerId = consRight.id
  AND consRight.value = metaRight.hsaId
  AND metaRight.takPlattformId = tpLeft.id

  -- tpLeft and tpRight should be different
  AND NOT (tpLeft.id = tpRight.id)

  -- Exclude the Dalarna and NMT plattforms
  AND tpLeft.id < 7
  AND tpRight.id < 7

  -- Use logical address in check
  AND la.id NOT IN (
  SELECT DISTINCT integLeft.logicalAddressId
  FROM TakIntegration integLeft,
       TakServiceComponent prodLeft,
       MetaPlattformHsaId metaLeft

  -- The TP in integLeft should be same for first and last, and different from integRight, and the same as the consumer above
  WHERE integLeft.contractId = integRight.contractId
    AND integLeft.firstPlattformId = integLeft.lastPlattformId
    AND integLeft.firstPlattformId = tpLeft.id

    -- Ensure we only look att "today" (latest date in tpdb)
    AND '2019-01-17' BETWEEN integLeft.dateEffective AND integLeft.dateEnd

    -- Join the producer in integLeft with a the right plattform
    AND integLeft.producerid = prodLeft.id
    AND prodLeft.value = metaLeft.hsaId
    AND metaLeft.takPlattformId = tpRight.id

)

ORDER BY SecondTP, FirstTP, logicalAddress, namespace
;
