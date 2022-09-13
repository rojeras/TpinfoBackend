-- Leta fram anropsbehörigheter där det inte finns något motsvarande vägval i en TP

SELECT DISTINCT
                component.value AS consumerHsa,
                CONCAT(plattform.name, ' ', plattform.environment) AS plattform,
                contract.namespace AS namespace,
                la.value AS logicalAddress
FROM TakCallAuthorization auth,
     TakPlattform plattform,
     TakServiceContract contract,
     TakLogicalAddress la,
    TakServiceComponent component
WHERE auth.plattformId = plattform.id
  AND auth.serviceContractId = contract.id
  AND auth.logicalAddressId = la.id
  AND auth.serviceComponentId = component.id
  AND '2019-01-17' BETWEEN auth.dateEffective AND auth.dateEnd
  AND auth.logicalAddressId NOT IN (
  SELECT DISTINCT rout.logicalAddressId
  FROM TakRouting rout
  WHERE rout.serviceContractId = auth.serviceContractId
    AND rout.plattformId = auth.plattformId
)
ORDER BY plattformId, logicalAddress, contract.id
